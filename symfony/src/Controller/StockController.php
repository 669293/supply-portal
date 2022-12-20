<?php
// src/Controller/StockController.php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Security as SecurityCore;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Stock;
use App\Entity\StockFiles;
use App\Entity\StockMaterials;
use App\Repository\ApplicationsRepository;
use App\Repository\BillsRepository;
use App\Repository\BillsMaterialsRepository;
use App\Repository\BillsStatusesRepository;
use App\Repository\LogisticsRepository;
use App\Repository\LogisticsMaterialsRepository;
use App\Repository\MaterialsRepository;
use App\Repository\OfficesRepository;
use App\Repository\ProvidersRepository;
use App\Repository\StockRepository;
use App\Repository\StockFilesRepository;
use App\Repository\StockMaterialsRepository;
use App\Repository\UnitsRepository;
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
        $sql = "SELECT res.bid AS id FROM (SELECT bs.bill AS bid, (SELECT bs2.status FROM bills_statuses bs2 WHERE bs2.id = MAX(bs.id)) FROM bills_statuses bs GROUP BY bs.bill) res, bills b WHERE res.bid = b.id AND b.user IN (SELECT u.id FROM users u WHERE u.office = (SELECT office FROM users WHERE id = ".(int)$this->security->getUser()->getId().")) AND res.status NOT IN (5,9,10) ORDER BY b.inn;";
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

            if (sizeof($applications) > 0) {
                $bills[] = $objBill;
            }
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

    /**
     * @Route("/stock/add/pm", methods={"GET"})
     * @IsGranted("ROLE_STOCK")
     */
    public function addPMForm(
        Request $request,
        UnitsRepository $unitsRepository
    ): Response
    {
        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/';
        $breadcrumbs[0]->title = 'Склад';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/stock/add/pm';
        $breadcrumbs[1]->title = 'Создание приходного ордера';

        $params['title'] = 'Создание приходного ордера';
        $params['breadcrumbs'] = $breadcrumbs;
        $params['units'] = $unitsRepository->findAll();

        return $this->render('stock/add-pm.html.twig', $params);
    }

    /**
     * @Route("/stock/getbills", methods={"GET"})
     * @IsGranted("ROLE_STOCK")
     */
    public function getBills(
        Request $request,
        BillsRepository $billsRepository,
        BillsMaterialsRepository $billsMaterialsRepository,
        MaterialsRepository $materialsRepository, 
        ProvidersRepository $providersRepository
    ): Response
    {
        $inn = $request->query->get('inn'); //ИНН, если указан

        // Получаем список счетов в работе подразделения текущего пользователя
        $needToFilterByInn = ''; if ($inn && !empty($inn)) {$needToFilterByInn = " AND b.inn = '".pg_escape_string($inn)."'";}
        $sql = "SELECT res.bid AS id FROM (SELECT bs.bill AS bid, (SELECT bs2.status FROM bills_statuses bs2 WHERE bs2.id = MAX(bs.id)) FROM bills_statuses bs GROUP BY bs.bill) res, bills b WHERE res.bid = b.id".$needToFilterByInn." AND b.user IN (SELECT u.id FROM users u WHERE u.office = (SELECT office FROM users WHERE id = ".(int)$this->security->getUser()->getId().")) AND res.status NOT IN (5,9,10) ORDER BY b.inn;";
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

                //Проверяем если материал еще не закрыт
                if ($tmpObj->count > $tmpObj->done) {
                    $materials[] = $tmpObj;
                }

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

            if (sizeof($objBill->applications) > 0) {
                $bills[] = $objBill;
            }
        }

        // Получаем поставщика
        $provider = '';
        if ($inn && !empty($inn)) {
            $provider = 'ИНН: '.$inn;
            $objProvider = $providersRepository->findBy(array('inn' => $inn));
            if ($objProvider) {
                if (is_array($objProvider)) {$objProvider = array_shift($objProvider);}
                $provider = $objProvider->getTitle().'('.$provider .')';
            }
        }
        
        return $this->render('fragments/_bills-to-pick.html.twig', ['bills' => $bills, 'provider' => $provider]);
    }

    /**
     * @Route("/stock/add/pm", methods={"POST"})
     * @IsGranted("ROLE_STOCK")
     */
    public function addPM(
        Request $request,
        StockFilesRepository $stockFilesRepository,
        UnitsRepository $unitsRepository,
        UsersRepository $usersRepository
    ): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('add-pm', $submittedToken)) {
            //Готовим данные
            $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

            try {
                //Получаем массив наименований
                $arrTitles = $request->request->get('add-pm-materials');
                $rowsCount = sizeof($arrTitles); //Определяем полезное количество строк
                while ($rowsCount > 0) {if (empty($arrTitles[$rowsCount - 1])) {$rowsCount--;} else {break;}}
                $arrTitles = array_slice($arrTitles, 0, $rowsCount);

                //Получаем массив единиц измерения
                $unitsRaw = array_slice($request->request->get('add-pm-units'), 0, $rowsCount);

                //Обрабатываем массив единиц измерения
                $arrUnits = [];
                foreach ($unitsRaw as $unit) {
                    //Смотрим, есть ли такая единица измерения в базе
                    $tmp = $unitsRepository->findBy(array('id' => $unit));
                    if (sizeof($tmp) > 0) {
                        //Используем единицу измерения из базы
                        $arrUnits[] = $tmp[0];
                    } else {
                        $arrUnits[] = null;
                    }
                }

                $arrCount = array_slice($request->request->get('add-pm-count'), 0, $rowsCount); //Получаем массив количества
                $arrPrice = array_slice($request->request->get('add-pm-price'), 0, $rowsCount); //Получаем массив цен
                $arrSum = array_slice($request->request->get('add-pm-sum'), 0, $rowsCount); //Получаем массив сумм
                $arrTax = array_slice($request->request->get('add-pm-tax'), 0, $rowsCount); //Получаем массив НДС
                $arrTotal = array_slice($request->request->get('add-pm-total'), 0, $rowsCount); //Получаем массив итоговых сумм

                //Начинаем запись, пишем данные в таблицу stock
                $stock = new Stock;
                $stock->setProvider( $request->request->get('add-pm-comment') ); //Поставщик
                $stock->setComment( $request->request->get('add-pm-provider') ); //Наименование документа
                $stock->setDate( new \DateTime($request->request->get('add-pm-date').' 00:00:00') ); //Дата документа
                $stock->setDatetime( new \DateTime() ); //Реальная дата документа
                $stock->setInvoice( $request->request->get('add-pm-sf') ); //Номер документа
                if ($request->request->get('add-pm-note')) {$stock->setNote( $request->request->get('add-pm-note') );} //Дополнительный комментарий
                $stock->setTax( $request->request->get('add-pm-tax_') ); //Налоговая ставка
                $stock->setType( $request->request->get('add-pm-type') ); //Тип поступления

                $parameters = 0; //Дополнительные параметры
                if ( $request->request->get('add-pm-transit') !== null ) {$parameters += 1;}
                if ( $request->request->get('add-pm-direct') !== null ) {$parameters += 2;}
                $stock->setParams( $parameters ); //Дополнительные параметры

                //Определяем авторство
                $author = $usersRepository->findBy( array('id' => $this->security->getUser()->getId()) );
                if (is_array($author)) {$author = array_shift($author);}
                $stock->setUser($author);

                $this->entityManager->persist($stock);
                $this->entityManager->flush(); //ID документа в $stock->getId();

                //Добавляем материалы к заявке
                for ($i = 0; $i < sizeof($arrTitles); $i++) {
                    $material = new StockMaterials(
                        $arrTitles[$i],
                        $arrPrice[$i],
                        $arrCount[$i],
                        $arrSum[$i],
                        $arrTax[$i],
                        $arrTotal[$i],
                        $arrUnits[$i], //Units::class
                        $stock
                    );

                    $this->entityManager->persist($material);
                }

                //Добавляем файлы
                $arrFiles = json_decode($request->request->get('files'));
                if ($arrFiles !== null ) {
                    foreach ($arrFiles as $file) {
                        $objFile = $stockFilesRepository->findBy( array('id' => $file) );
                        if (is_array($objFile)) {$objFile = array_shift($objFile);}
                        $objFile->setStock($stock);
                        $this->entityManager->persist($objFile);
                    }
                }

                $this->entityManager->flush();
                $this->entityManager->getConnection()->commit();

                $result[] = 1;
                $result[] = $stock->getId();
            } catch (Exception $e) {
                $this->entityManager->getConnection()->rollBack();

                $result[] = 0;
                $result[] = $e;
                //throw $e;
            }

            $this->entityManager->clear();

            return new JsonResponse($result);
        } else {
            $result[] = 0;
            $result[] = 'Недействительный токен CSRF.';
            return new JsonResponse($result);
        }
    }

    /**
     * Загрузка данных из шаблона
     * @Route("/stock/add/pm/upload-template", methods={"POST"})
     * @IsGranted("ROLE_STOCK")
     */
    public function uploadTemplate(
        Request $request, 
        UnitsRepository $unitsRepository,
        ValidatorInterface $validator
    ): Response
    {
        $file = $request->files->get('template');

        $submittedToken = $request->request->get('token');

        $violations = $validator->validate(
            $file,
            new File([
                'maxSize' => '10M',
                'mimeTypes' => [
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ],
                'mimeTypesMessage' => 'Недопустимый тип файла'
            ])
        );

        $error = ''; foreach ($violations as $violation) {$error .= $violation->getMessage();}

        $units = $unitsRepository->findAll();

        ob_start();

        if ($error || $file === null || !$this->isCsrfTokenValid('upload-template', $submittedToken)) {
            //Возвращаем пустую форму
            $this->returnBlankData($units);
        } else {
            //Начинаем обработку файла
            $path = $file->getRealPath();
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            $objReader->setReadDataOnly(true);
            $objPHPExcel = $objReader->load($path);
            $objWorksheet = $objPHPExcel->getActiveSheet();

            $highestRow = (int)$objWorksheet->getHighestRow();
            $highestColumn = \PHPExcel_Cell::columnIndexFromString($objWorksheet->getHighestColumn());
            
            if ($highestColumn != 7) {
                //Возвращаем пустую форму
                $this->returnBlankData($units);
            }

            $data = [];
            for ($row=2; $row<=$highestRow; $row++) {
                $rowArray = [];
                for ($col=0; $col<$highestColumn; $col++) {
                    $val = $objWorksheet->getCellByColumnAndRow($col, $row)->getValue();

                    switch ($col) {
                        case 0: // Наименование
                            $rowArray['title'] = $val;
                            if ($val !== null) {
                                $rowArray['titleValid'] = true;
                            } else {
                                $rowArray['titleValid'] = false;
                            }
                        break;
                        case 1: // Единицы измерения
                            // Синонимы
                            $equals = [
                                ['шт.', 'шт'],
                                ['м2', 'м²'],
                                ['м3', 'м³'],
                                ['кв.м.', 'м²'],
                                ['куб.м.', 'м³'],
                                ['набор', 'наб.']
                            ];

                            foreach ($equals as $equal) {
                                if (mb_strtolower($val) == $equal[0]) {$val = $equal[1];}
                            }

                            //Ищем единицу измерения в базе
                            $objUnit = $unitsRepository->findBy(array('title' => $val));
                            if (is_array($objUnit) && sizeof($objUnit) > 0) {
                                $rowArray['unit'] = $objUnit[0];
                                $rowArray['unitValid'] = true;
                            } else {
                                // Устанавливаем дефолтную единицу измерения (шт)
                                $objUnit = $unitsRepository->findBy(array('title' => 'шт'));
                                if (is_array($objUnit) && sizeof($objUnit) > 0) {
                                    $rowArray['unit'] = $objUnit[0];
                                    $rowArray['unitValid'] = false;
                                } else {
                                    return new RedirectResponse('/applications/add');
                                }
                            }
                        break;
                        case 2: // Количество
                            if ($val !== null) {
                                $rowArray['count'] = (float)$val;
                                $rowArray['countValid'] = true;
                            } else {
                                $rowArray['count'] = 0.0;
                                $rowArray['countValid'] = false;
                            }
                        break;
                        case 3: // Цена
                            if ($val !== null) {
                                $rowArray['price'] = (float)$val;
                                $rowArray['priceValid'] = true;
                            } else {
                                $rowArray['price'] = 0.0;
                                $rowArray['priceValid'] = false;
                            }
                        break;
                        case 4: // Сумма
                            if ($val !== null) {
                                $rowArray['sum'] = (float)$val;
                                $rowArray['sumValid'] = true;
                            } else {
                                $rowArray['sum'] = 0.0;
                                $rowArray['sumValid'] = false;
                            }
                        break;
                        case 5: // НДС
                            if ($val !== null) {
                                $rowArray['tax'] = (float)$val;
                                $rowArray['taxValid'] = true;
                            } else {
                                $rowArray['tax'] = 0.0;
                                $rowArray['taxValid'] = false;
                            }
                        break;
                        case 6: // Всего
                            if ($val !== null) {
                                $rowArray['total'] = (float)$val;
                                $rowArray['totalValid'] = true;
                            } else {
                                $rowArray['total'] = 0.0;
                                $rowArray['totalValid'] = false;
                            }
                        break;
                    }
                }

                $data[] = $rowArray;
            }

            //Формируем таблицу на основании данных
            for ($i=0; $i<sizeof($data); $i++) {
                echo '<tr>'."\n";
                echo '    <td class="align-middle text-center text-xsmall">'.($i + 1).'</td>'."\n";
                echo '    <td class="align-middle"><input class="form-control form-control-sm material-autocomplete'.(!$data[$i]['titleValid'] ? ' is-invalid' : '').'" type="text" value="'.$data[$i]['title'].'" name="add-pm-materials[]" /></td>'."\n";
                echo '    <td class="align-middle text-center">'."\n";
                echo '        <select class="form-select form-select-sm'.(!$data[$i]['unitValid'] ? ' is-invalid' : '').'" name="add-pm-units[]">'."\n";
                
                foreach ($units as $unit) {
                    if ($unit->getTitle() == $data[$i]['unit']->getTitle()) {
                        echo '<option value="'.$unit->getId().'" selected="selected">'.$unit->getTitle().'</option>';
                    } else {
                        echo '<option value="'.$unit->getId().'">'.$unit->getTitle().'</option>';
                    }
                }
                
                echo '        </select>'."\n";
                echo '    </td>'."\n";
                echo '    <td class="align-middle"><input class="form-control form-control-sm'.(!$data[$i]['countValid'] ? ' is-invalid' : '').'" type="number" value="'.$data[$i]['count'].'" name="add-pm-count[]" /></td>'."\n";
                echo '    <td class="align-middle"><input class="form-control form-control-sm numbersOnly'.(!$data[$i]['priceValid'] ? ' is-invalid' : '').'" type="text" value="'.$data[$i]['price'].'" name="add-pm-price[]" /></td>'."\n";
                echo '    <td class="align-middle"><input class="form-control form-control-sm numbersOnly'.(!$data[$i]['sumValid'] ? ' is-invalid' : '').'" type="text" value="'.$data[$i]['sum'].'" name="add-pm-sum[]" /></td>'."\n";
                echo '    <td class="align-middle"><input class="form-control form-control-sm numbersOnly'.(!$data[$i]['taxValid'] ? ' is-invalid' : '').'" type="text" value="'.$data[$i]['tax'].'" name="add-pm-tax[]" /></td>'."\n";
                echo '    <td class="align-middle"><input class="form-control form-control-sm numbersOnly'.(!$data[$i]['totalValid'] ? ' is-invalid' : '').'" type="text" value="'.$data[$i]['total'].'" name="add-pm-total[]" /></td>'."\n";
                echo '    <td class="text-center align-middle"><i class="bi bi-trash text-danger fs-6 delete-row"></i></td>'."\n";
                echo '</tr>'."\n";
            }
        }

        $content = ob_get_contents();
        ob_end_clean();

        return new Response($content);
    }

    private function returnBlankData($units) {
        //Возвращаем пустую форму
        for ($i=1; $i<=10; $i++) {
            echo '<tr>'."\n";
            echo '    <td class="align-middle text-center text-xsmall">'.$i.'</td>'."\n";
            echo '    <td class="align-middle"><input class="form-control form-control-sm material-autocomplete" type="text" value="" name="add-pm-materials[]" /></td>'."\n";
            echo '    <td class="align-middle text-center">'."\n";
            echo '        <select class="form-select form-select-sm" name="add-pm-units[]">'."\n";
            
            foreach ($units as $unit) {
                if ($unit->getTitle() == 'шт') {
                    echo '<option value="'.$unit->getId().'" selected="selected">'.$unit->getTitle().'</option>';
                } else {
                    echo '<option value="'.$unit->getId().'">'.$unit->getTitle().'</option>';
                }
            }
            
            echo '        </select>'."\n";
            echo '    </td>'."\n";
            echo '    <td class="align-middle"><input class="form-control form-control-sm" type="number" value="" name="add-pm-count[]" /></td>'."\n";
            echo '    <td class="align-middle"><input class="form-control form-control-sm numbersOnly" type="text" value="" name="add-pm-price[]" /></td>'."\n";
            echo '    <td class="align-middle"><input class="form-control form-control-sm numbersOnly" type="text" value="" name="add-pm-sum[]" /></td>'."\n";
            echo '    <td class="align-middle"><input class="form-control form-control-sm numbersOnly" type="text" value="" name="add-pm-tax[]" /></td>'."\n";
            echo '    <td class="align-middle"><input class="form-control form-control-sm numbersOnly" type="text" value="" name="add-pm-total[]" /></td>'."\n";
            echo '    <td class="text-center align-middle"><i class="bi bi-trash text-danger fs-6 delete-row"></i></td>'."\n";
            echo '</tr>'."\n";
        }
    }

    /**
     * Удаление файлов при создании или редактировании документа
     * @Route("/stock/delete-file", methods={"POST"})
     * @IsGranted("ROLE_STOCK")
     */
    public function deleteFile(Request $request, StockFilesRepository $stockFilesRepository): JsonResponse
    {
        try {
            $file = $stockFilesRepository->findBy( array('id' => $request->request->get('key')) );
            if (sizeof($file) > 0) {$file = array_shift($file);}

            //Проверяем, может ли пользователь удалить этот файл
            if ($this->security->getUser()->getId() != $file->getUser()->getId()) {
                return new JsonResponse(false);    
            }

            $this->entityManager->remove($file);
            $this->entityManager->flush();

            if (file_exists($this->getParameter('stock_directory').'/'.$file->getPath())) {
                unlink($this->getParameter('stock_directory').'/'.$file->getPath());
                rmdir(dirname($this->getParameter('stock_directory').'/'.$file->getPath()));
            }

            return new JsonResponse(true);
        } catch (FileException $e) {
            return new JsonResponse(false);
        }
    }

    /**
     * Загрузка файлов при создании или редактировании документа
     * @Route("/stock/upload-file", methods={"POST"})
     * @IsGranted("ROLE_STOCK")
     */
    public function uploadFile(Request $request, ValidatorInterface $validator, UsersRepository $usersRepository): JsonResponse
    {
        $result = new \stdClass;

        $file = $request->files->get('filesApp');
        //Берем только первый элемент, потому что загрузка нескольких файлов идет последовательно
        if (is_array($file)) {$file = array_shift($file);}

        $violations = $validator->validate(
            $file,
            new File([
                'maxSize' => '10M',
                'mimeTypes' => [
                    'application/pdf',
                    'application/x-pdf',
                    'image/png',
                    'image/jpeg',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'   
                ],
                'mimeTypesMessage' => 'Недопустимый тип файла'
            ])
        );

        $error = ''; foreach ($violations as $violation) {$error .= $violation->getMessage();}

        if ($error) {
            $result->error = $error;

            return new JsonResponse($result);
        } else {
            //Генерируем имя папки
            $dirname = uniqid(); while (file_exists($this->getParameter('stock_directory').'/'.$dirname)) {$dirname = uniqid();} 
            mkdir($this->getParameter('stock_directory').'/'.$dirname, 0755);

            try {
                $file->move(
                    $this->getParameter('stock_directory').'/'.$dirname,
                    $file->getClientOriginalName()
                );

                //Добавляем информацию в базу
                $dbFile = new StockFiles;
                $dbFile->setPath($dirname.'/'.$file->getClientOriginalName());
                $user = $usersRepository->findBy(array('id' => $this->security->getUser()->getId()));
                if (sizeof($user) > 0) {$user = array_shift($user);}
                $dbFile->setUser($user);

                $this->entityManager->persist($dbFile);
                $this->entityManager->flush();
                $result->id = $dbFile->getId();

                return new JsonResponse($result);
            } catch (FileException $e) {
                $result->error = 'Ошибка при перемещении файла';
    
                return new JsonResponse($result);
            }
        }

        return new JsonResponse();
    }

    /**
     * @Route("/stock/view/pm", methods={"GET"})
     * @Security("is_granted('ROLE_STOCK') or is_granted('ROLE_BUH')")
     */
    public function viewPM(
        Request $request,
        StockRepository $stockRepository,
        StockFilesRepository $stockFilesRepository,
        StockMaterialsRepository $stockMaterialsRepository
    ): Response
    {
        $id = $request->query->get('number');
        if ($id === null || empty($id) || !is_numeric($id)) {
            return new RedirectResponse('/applications');
        }

        //Проверяем наличие документа
        $objStock = $stockRepository->findBy( array('id' => (int)$id) );
        if (sizeof($objStock) == 0) {
            return new RedirectResponse('/applications');
        }
        if (is_array($objStock)) {$objStock = array_shift($objStock);}

        //Получаем материалы
        $objMaterials = $stockMaterialsRepository->findBy( array('stock' => (int)$id) );

        //Получаем файлы
        $objFiles = $stockFilesRepository->findBy( array('stock' => (int)$id) );
        
        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/';
        $breadcrumbs[0]->title = 'Склад';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/stock/view/pm/'.$id;
        $breadcrumbs[1]->title = 'Просмотр приходного ордера';

        $params['title'] = 'Просмотр приходного ордера';
        $params['breadcrumbs'] = $breadcrumbs;
        $params['stock'] = $objStock;
        $params['materials'] = $objMaterials;
        $params['files'] = $objFiles;

        return $this->render('stock/view-pm.html.twig', $params);
    }
}
