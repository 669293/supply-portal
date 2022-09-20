<?php
// src/Controller/StockController.php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Security as SecurityCore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ApplicationsRepository;
use App\Repository\BillsRepository;
use App\Repository\BillsMaterialsRepository;
use App\Repository\BillsStatusesRepository;
use App\Repository\LogisticsRepository;
use App\Repository\LogisticsMaterialsRepository;
use App\Repository\MaterialsRepository;
use App\Repository\OfficesRepository;
use App\Repository\ProvidersRepository;
use App\Repository\UsersRepository;

class StockController extends AbstractController
{
    private $security;
    private $entityManager;

    public function __construct(SecurityCore $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/pick-bill-and-materials", methods={"GET"})
     * @IsGranted("ROLE_STOCK")
     */
    public function pickBillAndMaterials(
        Request $request,
        BillsRepository $billsRepository,
        BillsMaterialsRepository $billsMaterialsRepository,
        MaterialsRepository $materialsRepository, 
        ProvidersRepository $providersRepository
    ): Response
    {
        $docID = $request->query->get('doc');
        if ($docID === null) {
            return new RedirectResponse('/applications');
        } else {
            $docID = (int)$docID;
        }

        // Получаем список счетов в работе подразделения текущего пользователя
        $sql = "SELECT res.bid AS id FROM (SELECT bs.bill AS bid, (SELECT bs2.status FROM bills_statuses bs2 WHERE bs2.id = MAX(bs.id)) FROM bills_statuses bs GROUP BY bs.bill) res, bills b WHERE res.bid = b.id AND b.user IN (SELECT u.id FROM users u WHERE u.office = (SELECT office FROM users WHERE id = ".(int)$this->security->getUser()->getId().")) AND res.status <> 5 ORDER BY b.inn;";
        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $stmt->execute();
        $bills_ = $stmt->fetchAllAssociative();

        $bills = [];
        for ($i=0; $i<sizeof($bills_); $i++) {
            $objBill = $billsRepository->findBy(array('id' => $bills_[$i]['id']));
            if (is_array($objBill)) {$objBill = array_shift($objBill);}

            //Получаем список материалов в счете, из него получаем заявки
            $objBillMaterials = $billsMaterialsRepository->findBy(array('bill' => $objBill->getId()), array('id' => 'ASC'));
            $applications = [];
            $materials = [];
            foreach ($objBillMaterials as $objBillMaterial) {
                $exist = false; foreach ($applications as $application) {if ($application->getId() == $objBillMaterial->getMaterial()->getApplication()->getId()) {$exist = true; break;}}
                if (!$exist) {$applications[] = $objBillMaterial->getMaterial()->getApplication();}

                $tmpObj = new \stdClass();
                $tmpObj->material = $objBillMaterial->getMaterial();
                $tmpObj->billmaterial = $objBillMaterial->getId();
                $tmpObj->count = $objBillMaterial->getAmount();
                $tmpObj->done = $objBillMaterial->getRecieved();

                $materials[] = $tmpObj;
                unset($tmpObj);
            }

            $objBill->applications = $applications;
            $objBill->materials = $materials;

            //Получаем поставщика
            $objProvider = $providersRepository->findBy(array('inn' => $objBill->getInn()));
            if ($objProvider) {
                if (is_array($objProvider)) {$objProvider = array_shift($objProvider);}
                $objBill->provider = $objProvider;
            }

            $bills[] = $objBill;
        }

        $params['title'] = 'Выбор материалов';
        $params['bills'] = $bills;
        $params['docid'] = $docID;

        return $this->render('stock/pick-bill.html.twig', $params);
    }

    /**
     * @Route("/pick-logistic", methods={"GET"})
     * @IsGranted("ROLE_STOCK")
     */
    public function pickLogistic(
        Request $request,
        LogisticsRepository $logisticsRepository,
        LogisticsMaterialsRepository $logisticsMaterialsRepository,
        OfficesRepository $officesRepository
    ): Response
    {
        $docID = $request->query->get('id'); //Возвращаемое значение
        if ($docID === null) {
            return new RedirectResponse('/applications');
        } else {
            $docID = (int)$docID;
        }

        $logisticsIDs = $request->query->get('logistic');
        if ($logisticsIDs === null) {
            return new RedirectResponse('/applications');
        }

        if (!is_array($logisticsIDs)) {
            if (!is_numeric($logisticsIDs)) {
                return new RedirectResponse('/applications');
            }

            $logisticsIDs = [$logisticsIDs];
        }

        $arrLogistics = [];
        foreach ($logisticsIDs as $id) {
            $objLogistic = $logisticsRepository->findBy(array('id' => $id));
            if (is_array($objLogistic)) {$objLogistic = array_shift($objLogistic);}
            if ($objLogistic) {
                $tmpObj = new \stdClass();
                $tmpObj->logistic = $objLogistic;

                // Получаем материалы
                $materials = $logisticsMaterialsRepository->findBy(array('logistic' => $id));
                $arrMaterials = [];
                $avalibleMaterials = false; //Есть ли вообще материалы к отгрузке
                foreach ($materials as $material) {
                    $tmp = [];
                    $tmp['material'] = $material->getMaterial();
                    $tmp['amount'] = $material->getAmount();
                    $tmp['sent'] = 0; //Количество отправленных материалов

                    // Определяем количество отправленных материалов
                    // Получаем список дочерних отгрузок
                    $children = $logisticsRepository->findBy( array('parent' => $objLogistic->getId()) );
                    $childIds = []; foreach ($children as $child) {$childIds[] = $child->getId();} unset($children);

                    $arrSentMaterials = $logisticsMaterialsRepository->createQueryBuilder('lm')
                    ->where('lm.logistic IN (:ids)')
                    ->setParameter('ids', $childIds)
                    ->getQuery()
                    ->getResult();

                    $sentMaterials = [];
                    foreach ($arrSentMaterials as $material) {
                        // Смотрим, есть ли такой материал в массиве
                        $exist = false;
                        for ($i=0; $i<sizeof($sentMaterials); $i++) {
                            if ($sentMaterials[$i]->id == $material->getMaterial()->getId()) {
                                $sentMaterials[$i]->amount += $material->getAmount();
                                $exist = true;
                                break;
                            }
                        }


                        if (!$exist) {
                            $tmp_ = new \stdClass;
                            $tmp_->id = $material->getMaterial()->getId();
                            $tmp_->amount = $material->getAmount();
                            $sentMaterials[] = $tmp_;
                            unset($tmp_);
                        }
                    }
                    unset($arrSentMaterials);

                    // Определяем количество отправленных материалов
                    foreach ($sentMaterials as $sentMaterial) {
                        if ($material->getMaterial()->getId() == $sentMaterial->id) {
                            $tmp['sent'] += (float)$sentMaterial->amount; break;
                        }
                    }

                    if ((float)$tmp['amount'] - (float)$tmp['sent'] > 0) {
                        $avalibleMaterials = true;
                    }

                    $arrMaterials[] = $tmp; unset($tmp);
                }

                // Добавляем новую логистику в массив
                if ($avalibleMaterials) {
                    $tmpObj->materials = $arrMaterials;
                    $arrLogistics[] = $tmpObj;
                }

                unset($tmpObj);
            }
        }

        $params['title'] = 'Выбор получения материалов';
        $params['logistics'] = $arrLogistics;
        $params['offices'] = $officesRepository->findAll();
        $params['docid'] = $docID;

        return $this->render('stock/pick-log.html.twig', $params);
    }
}
