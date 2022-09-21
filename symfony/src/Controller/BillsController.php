<?php
// src/Controller/BillsController.php
namespace App\Controller;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security as SecurityCore;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Applications;
use App\Entity\ApplicationsStatuses;
use App\Entity\Bills;
use App\Entity\BillsMaterials;
use App\Entity\BillsStatuses;
use App\Entity\Documents;
use App\Entity\Logistics;
use App\Entity\LogisticsMaterials;
use App\Entity\Materials;
use App\Entity\Offices;
use App\Entity\Photos;
use App\Entity\Providers;
use App\Entity\StatusesOfApplications;
use App\Entity\StatusesOfBills;
use App\Repository\ApplicationsRepository;
use App\Repository\BillsRepository;
use App\Repository\BillsMaterialsRepository;
use App\Repository\BillsStatusesRepository;
use App\Repository\DocumentsRepository;
use App\Repository\LogisticsRepository;
use App\Repository\MaterialsRepository;
use App\Repository\OfficesRepository;
use App\Repository\PhotosRepository;
use App\Repository\ProvidersRepository;
use App\Repository\StatusesOfApplicationsRepository;
use App\Repository\StatusesOfBillsRepository;
use App\Repository\UsersRepository;

class BillsController extends AbstractController
{
    private $security;
    private $entityManager;

    public function __construct(SecurityCore $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    /**
     * Форма загрузки счета
     * @Route("/applications/bills/upload", methods={"GET"}))
     * @IsGranted("ROLE_EXECUTOR")
     */
    public function showUploadBillForm(ApplicationsRepository $applicationsRepository, MaterialsRepository $materialsRepository): Response
    {
        //Получаем список заявок, в которые есть права на загрузку счета
        $subQuery = $materialsRepository->createQueryBuilder('m')
        ->select('IDENTITY(m.application)')
        ->andWhere('m.isDeleted = FALSE')
        ->andWhere('m.responsible = :uid')
        ->getQuery()
        ;

        $applications_ = $applicationsRepository->createQueryBuilder('a')
        ->where('a.isBillsLoaded = FALSE')
        ->andWhere(
            $this->entityManager->createQueryBuilder()->expr()->in('a.id', $subQuery->getDQL())
        )
        ->orderBy('a.id', 'ASC')
        ->getQuery()
        ->setParameter('uid', $this->security->getUser()->getId())
        ->getResult()
        ;

        $applications = [];
        foreach ($applications_ as $application) {
            $materials_ = $materialsRepository->createQueryBuilder('m')
            ->where('m.application = :app')
            ->setParameter('app', $application->getId())
            ->leftJoin('App\Entity\BillsMaterials', 'bm', 'WITH' ,'bm.material=m.id')
            ->leftJoin('App\Entity\Users', 'u', 'WITH' ,'u.id=m.responsible')
            ->select(['m', 'SUM(bm.amount) AS amount', 'u.username', 'u.id'])
            ->groupBy('m, u.username, u.id')
            ->orderBy('m.num', 'ASC')
            ->getQuery()
            ->getResult()
            ;

            $materials = [];
            foreach ($materials_ as $material) {
                $cnt = $material['amount']; if ($cnt === null) {$cnt = 0;}
                $toe = ''; if ($material[0]->getTypeOfEquipment() !== null) {$toe = $material[0]->getTypeOfEquipment()->getTitle();}
                $rest = $material[0]->getAmount() - $cnt; if ($rest < 0) {$rest = 0;}
                if (!$material[0]->getIsDeleted() && 
                    $rest > 0 && 
                    $material[0]->getResponsible() !== null &&
                    $this->security->getUser()->getId() == $material[0]->getResponsible()->getId() && 
                    !$material[0]->getCash() && 
                    !$material[0]->getImpossible()) 
                {
                    $materials[] = $material;
                }
            }

            if (sizeof($materials) > 0) {
                $application->materials = $materials;
                $application->urgency = $applicationsRepository->getUrgency($application->getId());
                $application->responsibles = $applicationsRepository->getResponsibles($application->getId());
                $application->show = true;
                $applications[] = $application;
            }
        }

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications';
        $breadcrumbs[0]->title = 'Активные заявки';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/applications/bills/upload';
        $breadcrumbs[1]->title = 'Загрузка счета/спецификации';

        return $this->render('bills/upload.html.twig', [
            'title' => 'Загрузка счета/спецификации',
            'breadcrumbs' => $breadcrumbs,
            'applications' => $applications
        ]);
    }

    /**
     * Загрузка файла
     * @Route("/applications/bills/upload-bill", methods={"POST"})
     * @IsGranted("ROLE_EXECUTOR")
     */
    public function uploadFile(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $result = new \stdClass;

        $file = $request->files->get('billFile');

        $violations = $validator->validate(
            $file,
            new File([
                'maxSize' => '10M',
                'mimeTypes' => [
                    'application/pdf',
                    'application/x-pdf',
                    'image/png',
                    'image/jpeg'
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
            $dirname = uniqid(); while (file_exists($this->getParameter('bills_directory').'/'.$dirname)) {$dirname = uniqid();} 
            mkdir($this->getParameter('bills_directory').'/'.$dirname, 0755);

            try {
                $file->move(
                    $this->getParameter('bills_directory').'/'.$dirname,
                    $file->getClientOriginalName()
                );

                $result->path = $dirname.'/'.$file->getClientOriginalName();

                return new JsonResponse($result);
            } catch (FileException $e) {
                $result->error = 'Ошибка при перемещении файла';
    
                return new JsonResponse($result);
            }
        }

        return new JsonResponse();
    }

    /**
     * Загрузка счета (принимает данные из формы)
     * @Route("/applications/bills/upload", methods={"POST"}))
     * @IsGranted("ROLE_EXECUTOR")
     */
    public function uploadBillForm(
        Request $request, 
        BillsMaterialsRepository $billsMaterialsRepository,
        DocumentsRepository $documentsRepository,
        MaterialsRepository $materialsRepository, 
        StatusesOfBillsRepository $statusesOfBillsRepository, 
        UsersRepository $usersRepository
    ): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('upload-bill', $submittedToken)) {
            //Готовим данные
            $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

            try {
                //Получаем файл
                $objBill = new Bills;
                $objBill->setSum($request->request->get('billSumInput'));
                $objBill->setNum($request->request->get('billNumInput'));
                $objBill->setInn($request->request->get('innInput'));                
                $objBill->setPath($request->request->get('billFilePath'));
                $objBill->setUser($usersRepository->findBy(array('id' => $this->security->getUser()->getId()))[0]);
                $objBill->setCurrency($request->request->get('billCurrency'));
                $dateClose = new \DateTime();
                $dateClose->setTimestamp(strtotime($request->request->get('billDateInput')));
                $objBill->setDateClose($dateClose);

                if (!empty($request->request->get('billNoteInput'))) {
                    $objBill->setNote($request->request->get('billNoteInput'));
                }

                if (!empty($request->request->get('billCommentInput'))) {
                    $objBill->setComment($request->request->get('billCommentInput'));
                }

                $this->entityManager->persist($objBill);

                //Добавляем статус к счету
                $status = new BillsStatuses;
                $status->setBill($objBill);
                $status->setStatus( $statusesOfBillsRepository->findBy(array('id' => 1))[0] );
                $this->entityManager->persist($status);

                $this->entityManager->flush();

                //Привязываем счет к материалам
                $materials = $request->request->get('material');
                $amounts = $request->request->get('amount');

                //Массив заявок
                $applications = [];

                for ($i=0; $i<sizeof($materials); $i++) {
                    $objBillMaterial = new BillsMaterials;
                    $objBillMaterial->setBill($objBill);
                    $objBillMaterial->setAmount($amounts[$i]);
                    $material = $materialsRepository->findBy(array('id' => (int)$materials[$i]))[0];
                    $objBillMaterial->setMaterial($material);

                    $flag = false;
                    foreach ($applications as $application) {
                        if ($application->getId() == $material->getApplication()->getId()) {$flag = true; break;}
                    }

                    if (!$flag) {$applications[] = $material->getApplication();}

                    //Записываем
                    $this->entityManager->persist($objBillMaterial);
                    $this->entityManager->flush();
                }

                //Возможно стоит обновить дату закрытия заявки и/или скрыть заявку из формы загрузки счета
                foreach ($applications as $application) {
                    $isChanged = false;

                    //Проверяем, стоит ли обновить дату закрытия заявки
                    $appDateClose = new \DateTime();
                    $appDateClose->setTimestamp(strtotime('1970-01-01 00:00:01'));
                    if ($application->getDateClose() !== null) {$appDateClose = $application->getDateClose();}

                    if ($dateClose > $appDateClose) {
                        $application->setDateClose($dateClose);
                        $isChanged = true;
                    }

                    //Проверяем, все ли позиции в заявке закрыты
                    $materials_ = $materialsRepository->findBy(array('application' => (int)$application->getId(), 'isDeleted' => false, 'impossible' => false, 'cash' => false));
                    $flag = false;
                    foreach ($materials_ as $material_) {
                        //Получаем количество материала в заявке
                        $applicationAmount = (int)$material_->getAmount();

                        //Получаем количество материала в загруженных счетах
                        $billsAmount = $billsMaterialsRepository->createQueryBuilder('bm')
                        ->select('SUM(bm.amount) as amount')
                        ->where('bm.material = :material')
                        ->setParameter('material', $material_->getId())
                        ->getQuery()->getResult();
                        $billsAmount = (int)$billsAmount[0]['amount'];

                        if ($applicationAmount != $billsAmount) {
                            $flag = true; break;
                        }
                    }

                    if (!$flag) {
                        $application->setIsBillsLoaded(true);
                        $isChanged = true; 
                    }

                    if ($isChanged) {
                        //Записываем
                        $this->entityManager->persist($application);
                        $this->entityManager->flush();
                    }
                }

                //Добавляем файлы
                $arrFiles = json_decode($request->request->get('files'));
                if ($arrFiles !== null ) {
                    foreach ($arrFiles as $file) {
                        $objDocument = $documentsRepository->findBy( array('id' => $file) );
                        if (is_array($objDocument)) {$objDocument = array_shift($objDocument);}
                        $objDocument->setBill($objBill);
                        $this->entityManager->persist($objDocument);
                    }
                }
                $this->entityManager->flush();

                $this->entityManager->getConnection()->commit();

                $result[] = 1;
                $result[] = $objBill->getId();
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
     * Печать счетов
     * @Route("/applications/bills/print", methods={"GET"}))
     * @IsGranted("ROLE_SUPERVISOR")
     */
    public function printBillsForm(
        ApplicationsRepository $applicationsRepository,
        BillsStatusesRepository $billsStatusesRepository, 
        BillsRepository $billsRepository, 
        BillsMaterialsRepository $billsMaterialsRepository,
        StatusesOfBillsRepository $statusesOfBillsRepository
    ): Response
    {
        //Получаем список счетов на оплату
        $bills = [];
        $bills_ = [];
        
        $sql = "SELECT res.bid AS id FROM (SELECT bs.bill AS bid, (SELECT bs2.status FROM bills_statuses bs2 WHERE bs2.id = MAX(bs.id)) FROM bills_statuses bs GROUP BY bs.bill) res WHERE res.status = 1;";
        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $stmt->execute();
        $bills_ = $stmt->fetchAllAssociative();

        for ($i=0; $i<sizeof($bills_); $i++) {
            $objBill = $billsRepository->findBy(array('id' => $bills_[$i]['id']));
            if (is_array($objBill)) {$objBill = array_shift($objBill);}

            //Получаем массив заявок по этому счету
            $applications = []; $urgency = false;
            $billMaterials = $billsMaterialsRepository->findBy(array('bill' => $bills_[$i]['id']));
            foreach ($billMaterials as $billMaterial) {
                $exist = false;
                foreach ($applications as $application) {
                    if ($application->getId() == $billMaterial->getMaterial()->getApplication()->getId()) {$exist = true; break;}
                }
                if (!$exist) {$applications[] = $billMaterial->getMaterial()->getApplication();}
                $urgency = $urgency || $billMaterial->getMaterial()->getUrgency();
            }

            $bills_[$i]['obj'] = $objBill;
            $bills_[$i]['applications'] = $applications;
            $bills_[$i]['urgency'] = $urgency;

            //Проверяем статусы заявок
            $stopFlag = false;
            foreach ($applications as $application) {
                if (in_array($applicationsRepository->getStatus($application->getId()), [3,4,5])) {
                    $stopFlag = true; break;
                }
            }

            if (!$stopFlag) {
                $bills[] = $bills_[$i];
            }

            unset($objBill, $billMaterials);
        }

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications';
        $breadcrumbs[0]->title = 'Активные заявки';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/applications/bills/print';
        $breadcrumbs[1]->title = 'Печать счетов';

        return $this->render('bills/print.html.twig', [
            'title' => 'Печать счетов',
            'breadcrumbs' => $breadcrumbs,
            'bills' => $bills,
            'statuses' => $statusesOfBillsRepository->findAll()
        ]);
    }

    /**
     * Печать счетов (принимает данные из формы или из URL)
     * @Route("/applications/bills/download", methods={"GET", "POST"}))
     * @IsGranted("ROLE_SUPERVISOR")
     */
    public function printBillForm(Request $request, BillsRepository $billsRepository, BillsMaterialsRepository $billsMaterialsRepository, BillsStatusesRepository $billsStatusesRepository, StatusesOfBillsRepository $statusesOfBillsRepository): \Mpdf\Mpdf
    {
        $id = $request->query->get('id');
        if ($id === null) {
            //Получаем данные из POST
            $submittedToken = $request->request->get('token');

            if ($this->isCsrfTokenValid('download-bills', $submittedToken)) {
                try {
                    //Получаем массив наименований
                    $bills = [];
                    $billsArr = $request->request->get('bills');
                    foreach ($billsArr as $bill) {
                        $bills[] = (int)$bill;
                    }
                } catch (Exception $e) {
                    throw new Exception($e);
                }
            } else {
                throw new Exception('Недействительный токен CSRF.');
            }
        } else {
            $bills = [(int)$id];    
        }

        require_once $this->getParameter('kernel.project_dir').'/../vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf();
        // $mpdf->debug = true;

        //Получаем объект статуса "Передан на оплату"
        // $nextStatus = $statusesOfBillsRepository->findBy(array('id' => 2));
        // if (is_array($nextStatus)) {$nextStatus = array_shift($nextStatus);}

        foreach ($bills as $billId) {
            //Получаем счет
            $bill = $billsRepository->findBy(array('id' => $billId));
            if (is_array($bill)) {$bill = array_shift($bill);}

            //Получаем массив заявок по этому счету
            $applications = []; $urgency = false;
            $billMaterials = $billsMaterialsRepository->findBy(array('bill' => $bill->getId()));
            foreach ($billMaterials as $billMaterial) {
                $exist = false;
                foreach ($applications as $application) {
                    if ($application->getId() == $billMaterial->getMaterial()->getApplication()->getId()) {$exist = true; break;}
                }
                if (!$exist) {$applications[] = $billMaterial->getMaterial()->getApplication();}
                $urgency = $urgency || $billMaterial->getMaterial()->getUrgency();
            }
            
            $titles = []; $title = '';
            foreach ($applications as $application) {
                $titles[] = '<span style="font-weight: bold;">'.$application->getId().( !empty($application->getNumber()) ? ' ['.$application->getNumber().']' : '' ).'</span> ('.$application->getAuthor()->getShortUsername().')';
                $title = $application->getId().( !empty($application->getNumber()) ? ' ['.$application->getNumber().']' : '' ).' ('.$application->getAuthor()->getShortUsername().')'; //Используется для формирования имени файла, если счет один
            }

            ob_start();

            echo '<div style="border: 1px solid #f00; font-family: Tahoma, Serif; font-size: 12px; padding: 5px;">'."\n";

            echo 'Заявка: '.implode(', ', $titles)."\n";
            if ($urgency) {echo '<img style="float: right; height: 30px; margin-right: 3px; width: 30px;" src="img/exclamation-triangle.svg" alt="" />'."\n";}
            echo '<br />Ответственный: '.$bill->getUser()->getShortUsername()."\n";
            if (!empty($bill->getNote())) {echo '<br />Комментарий: <span style="color: #f00;">'.$bill->getNote().'</span>'."\n";}

            echo '</div>'."\n";

            $header = ob_get_contents();
            ob_end_clean();
            
            $mpdf->SetHTMLHeader($header);

            //Определяем расширение
            $tmp = explode('.', basename($bill->getPath())); $ext = end($tmp); unset($tmp);

            if ($ext == 'pdf') {
                //Понижаем версию pdf до 1.4 для того чтобы mPDF могла его обработать
                $filename = basename($this->getParameter('bills_directory').'/'.$bill->getPath());
                $dirname = dirname($this->getParameter('bills_directory').'/'.$bill->getPath());

                rename($dirname.'/'.$filename, $dirname.'/old.pdf');
                $cmd = 'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -o '.$dirname.'/new.pdf '.$dirname.'/old.pdf';
                $result = shell_exec($cmd);
                rename($dirname.'/old.pdf', $dirname.'/'.str_replace('.pdf', '.old.pdf', $filename));
                rename($dirname.'/new.pdf', $dirname.'/'.$filename);

                $pagesCount = $mpdf->SetSourceFile($this->getParameter('bills_directory').'/'.$bill->getPath());

                for ($i=1; $i<=$pagesCount; $i++) {
                    $tplId = $mpdf->ImportPage($i);
                    $size = $mpdf->getTemplateSize($tplId);
                    $mpdf->AddPage($size['orientation']);
                    $k = ($size['height'] - 25) / $size['height'];
                    $mpdf->useTemplate($tplId, 5, 25, ceil($size['width'] * $k), ceil($size['height'] * $k));
                }
            } else {
                $mpdf->AddPage();
                $mpdf->WriteHTML('<img src="'.$this->getParameter('bills_directory').'/'.$bill->getPath().'" alt="'.$bill->getPath().'" style="margin-top: 50px;" />');
            }

            //Получаем текущий статус
            // $status = $billsStatusesRepository->createQueryBuilder('bs')
            // ->select('IDENTITY(bs.status) AS status')
            // ->where('bs.bill = :bill')
            // ->setParameter('bill', $billId)
            // ->orderBy('bs.datetime', 'DESC')
            // ->setMaxResults(1)
            // ->getQuery()
            // ->getResult();
            // if (is_array($status)) {$status = array_shift($status);}

            // if ($status['status'] == 1) {
            //     //Добавляем статус "Передан на оплату к счету"
            //     $billStatus = new BillsStatuses();
            //     $billStatus->setBill($bill);
            //     $billStatus->setStatus($nextStatus);
            //     $this->entityManager->persist($billStatus);
            //     $this->entityManager->flush();
            // }
        }

        if (sizeof($bills) > 1) {
            $mpdf->Output('Счета.pdf', 'I');
        } else {
            $mpdf->Output('Счет заявке №'.$title.'.pdf', 'I');
        }
    }

    /**
     * Установка статуса нескольких заявок
     * @Route("/applications/bills/set-bill-status", methods={"POST"}))
     * @IsGranted("ROLE_SUPERVISOR")
     */
    public function setStatuses(
        Request $request,
        BillsRepository $billsRepository,
        StatusesOfBillsRepository $statusesOfBillsRepository
    ): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('download-bills', $submittedToken)) {
            //Получаем переменные
            $billsIDs = json_decode($request->request->get('bills')); if (!is_array($billsIDs)) {$billsIDs = [$billsIDs];}
            $statusID = $request->request->get('status');

            $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

            try {
                //Получаем статус
                $status = $statusesOfBillsRepository->findBy( array('id' => $statusID) );
                if (is_array($status)) {$status = array_shift($status);}
                if ($status === null) {
                    $result[] = 0;
                    $result[] = 'Неверный идентификатор статуса';
                    return new JsonResponse($result);
                }

                //Ставим статусы счетам
                if (sizeof($billsIDs) == 0) {
                    $result[] = 1;
                    $result[] = '';

                    return new JsonResponse($result);
                }

                foreach ($billsIDs as $billID) {
                    //Получаем счет
                    $bill = $billsRepository->findBy( array('id' => $billID) );
                    if (is_array($bill)) {$bill = array_shift($bill);}
                    if ($bill !== null) {
                        //Проверяем, изменился ли статус
                        if ($billsRepository->getStatus($billID) != $statusID) {
                            //Вносим информацию в базу
                            $billStatus = new BillsStatuses();
                            $billStatus->setBill($bill);
                            $billStatus->setStatus($status);
                            $this->entityManager->persist($billStatus);
                            $this->entityManager->flush();
                        }
                    }
                }

                $this->entityManager->getConnection()->commit();
        
                $result[] = 1;
                $result[] = '';
            } catch (Exception $e) {
                $this->entityManager->getConnection()->rollBack();

                $result[] = 0;
                $result[] = $e;
                //throw $e;
            }

            return new JsonResponse($result);
        } else {
            $result[] = 0;
            $result[] = 'Недействительный токен CSRF.';
            return new JsonResponse($result);
        }
    }


    /**
     * История печати счетов
     * @Route("/applications/bills/history", methods={"GET", "POST"}))
     * @IsGranted("ROLE_SUPERVISOR")
     */
    public function historyBills(Request $request, StatusesOfBillsRepository $statusesOfBillsRepository, BillsStatusesRepository $billsStatusesRepository, BillsRepository $billsRepository, BillsMaterialsRepository $billsMaterialsRepository): Response
    {
        //Получаем диапазон дат
        if ($request->request->get('filterDateFrom') !== null) {
            $timestamp1 = strtotime($request->request->get('filterDateFrom').' 00:00:00');
        } else {
            $timestamp1 = strtotime(date('Y-m-d').' 00:00:00');
        }

        if ($request->request->get('filterDateTo') !== null) {
            $timestamp2 = strtotime($request->request->get('filterDateTo').' 23:59:59');
        } else {
            $timestamp2 = strtotime(date('Y-m-d').' 23:59:59');
        }

        $dt1 = new \DateTime();
        $dt1->setTimestamp($timestamp1);
        $dt2 = new \DateTime();
        $dt2->setTimestamp($timestamp2);

        //Получаем список счетов на оплату
        $bills = [];
        $bills = $billsStatusesRepository->createQueryBuilder('bs')
        ->select('IDENTITY(bs.bill) AS id, IDENTITY(bs.status) AS status, bs.datetime AS datetime')
        ->groupBy('bs.bill, bs.status, bs.datetime')
        ->having('bs.status=2')
        ->andHaving('bs.datetime >= :dt1')
        ->andHaving('bs.datetime <= :dt2')
        ->setParameter('dt1', $dt1)
        ->setParameter('dt2', $dt2)
        ->getQuery()
        ->getResult(); 

        for ($i=0; $i<sizeof($bills); $i++) {
            $objBill = $billsRepository->findBy(array('id' => $bills[$i]['id']));
            if (is_array($objBill)) {$objBill = array_shift($objBill);}

            //Получаем массив заявок по этому счету
            $applications = []; $urgency = false;
            $billMaterials = $billsMaterialsRepository->findBy(array('bill' => $bills[$i]['id']));
            foreach ($billMaterials as $billMaterial) {
                $exist = false;
                foreach ($applications as $application) {
                    if ($application->getId() == $billMaterial->getMaterial()->getApplication()->getId()) {$exist = true; break;}
                }
                if (!$exist) {$applications[] = $billMaterial->getMaterial()->getApplication();}
                $urgency = $urgency || $billMaterial->getMaterial()->getUrgency();
            }

            //Получаем статус
            $objStatus = $statusesOfBillsRepository->findBy(array('id' => $bills[$i]['status']));
            if (is_array($objStatus)) {$objStatus = array_shift($objStatus);}

            $bills[$i]['obj'] = $objBill;
            $bills[$i]['applications'] = $applications;
            $bills[$i]['urgency'] = $urgency;
            $bills[$i]['status'] = $objStatus;
            unset($objBill, $billMaterials);
        }

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications';
        $breadcrumbs[0]->title = 'Активные заявки';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/applications/bills/print';
        $breadcrumbs[1]->title = 'Печать счетов';
        $breadcrumbs[2] = new \stdClass();
        $breadcrumbs[2]->href = '/applications/bills/history';
        $breadcrumbs[2]->title = 'История печати счетов';

        return $this->render('bills/history.html.twig', [
            'title' => 'История печати счетов',
            'breadcrumbs' => $breadcrumbs,
            'bills' => $bills,
            'dates' => [$dt1, $dt2]
        ]);
    }

    /**
     * Удаление счета
     * @Route("/applications/bills/remove", methods={"POST"}))
     * @IsGranted("ROLE_SUPERVISOR")
     */
    public function removeBill(Request $request, BillsRepository $billsRepository, ApplicationsRepository $applicationsRepository): Response
    {
        $id = $request->request->get('id');
        $bid = $request->request->get('bid');

        if ($bid === null || $id === null) {
            return new RedirectResponse('/applications');
        }

        $id = (int)$id; $bid = (int)$bid;

        try {
            //Удаляем счет
            $bill = $billsRepository->findBy( array('id' => $bid) );
            if (is_array($bill)) {$bill = array_shift($bill);}

            $this->entityManager->remove($bill);
            $this->entityManager->flush();

            //Убираем флаг из заявки о том что все счета загружены
            $application = $applicationsRepository->findBy( array('id' => $id) );
            if (is_array($application)) {$application = array_shift($application);}

            $application->setIsBillsLoaded(false);
            $this->entityManager->persist($application);
            $this->entityManager->flush();


            if (file_exists($this->getParameter('bills_directory').'/'.$bill->getPath())) {
                unlink($this->getParameter('bills_directory').'/'.$bill->getPath());
                rmdir(dirname($this->getParameter('bills_directory').'/'.$bill->getPath()));
            }

            return new RedirectResponse('/applications/view?number='.$id);
        } catch (FileException $e) {
            return new RedirectResponse('/applications/view?number='.$id);
        }
    }

    /**
     * Просмотр активных счетов
     * @Route("/applications/bills/in-work", methods={"GET"}))
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_EXECUTOR')")
     */
    public function inWorkBillsForm(
        ApplicationsRepository $applicationsRepository,
        BillsStatusesRepository $billsStatusesRepository, 
        BillsRepository $billsRepository, 
        BillsMaterialsRepository $billsMaterialsRepository, 
        ProvidersRepository $providersRepository,
        UsersRepository $usersRepository
    ): Response
    {
        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        //Получаем список счетов на оплату
        $bills = [];
        $bills_ = [];
        
        if (in_array('ROLE_SUPERVISOR', $roles)) {
            $sql = "SELECT res.bid AS id FROM (SELECT bs.bill AS bid, (SELECT bs2.status FROM bills_statuses bs2 WHERE bs2.id = MAX(bs.id)) FROM bills_statuses bs GROUP BY bs.bill) res, bills b WHERE res.bid = b.id AND res.status <> 5 ORDER BY b.inn;";
        } else {
            $sql = "SELECT res.bid AS id FROM (SELECT bs.bill AS bid, (SELECT bs2.status FROM bills_statuses bs2 WHERE bs2.id = MAX(bs.id)) FROM bills_statuses bs GROUP BY bs.bill) res, bills b WHERE res.bid = b.id AND b.user = ".(int)$this->security->getUser()->getId()." AND res.status <> 5 ORDER BY b.inn;"; 
        }
        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $stmt->execute();
        $bills_ = $stmt->fetchAllAssociative();

        for ($i=0; $i<sizeof($bills_); $i++) {
            $objBill = $billsRepository->findBy(array('id' => $bills_[$i]['id']));
            if (is_array($objBill)) {$objBill = array_shift($objBill);}

            //Получаем массив заявок по этому счету
            $applications = []; $urgency = false;
            $billMaterials = $billsMaterialsRepository->findBy(array('bill' => $bills_[$i]['id']));
            foreach ($billMaterials as $billMaterial) {
                $exist = false;
                foreach ($applications as $application) {
                    if ($application->getId() == $billMaterial->getMaterial()->getApplication()->getId()) {$exist = true; break;}
                }
                if (!$exist) {$applications[] = $billMaterial->getMaterial()->getApplication();}
                $urgency = $urgency || $billMaterial->getMaterial()->getUrgency();
            }

            //Подгружаем информацию о поставщике
            $provider = $providersRepository->findBy(array('inn' => $objBill->getInn()));
            if (is_array($provider)) {$provider = array_shift($provider);}

            $bills_[$i]['obj'] = $objBill;
            $bills_[$i]['applications'] = $applications;
            $bills_[$i]['urgency'] = $urgency;
            $bills_[$i]['provider'] = $provider;

            //Проверяем статусы заявок
            $stopFlag = false;
            foreach ($applications as $application) {
                if (in_array($applicationsRepository->getStatus($application->getId()), [3,4,5])) {
                    $stopFlag = true; break;
                }
            }

            if (!$stopFlag) {
                $bills[] = $bills_[$i];
            }

            unset($objBill, $billMaterials);
        }

        //Список пользователей
        $users = $usersRepository->findByRole('ROLE_EXECUTOR');

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications';
        $breadcrumbs[0]->title = 'Активные заявки';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/applications/bills/in-work';
        $breadcrumbs[1]->title = 'Счета в работе';

        return $this->render('bills/in-work.html.twig', [
            'title' => 'Счета в работе',
            'breadcrumbs' => $breadcrumbs,
            'bills' => $bills,
            'users' => $users
        ]);
    }

    /**
     * Вспомогательная функция для построения дерева логистики
     */
    private function putInTree($element, &$tree) {

        if ($element->parent !== null) {
            for ($i=0; $i<sizeof($tree); $i++) {
                if ($tree[$i]->element->logistics->getId() == $element->parent->getId()) {
                    //Добавляем в $tree[$i]->children
                    $tmp = new \stdClass;
                    $tmp->element = $element;
                    $tmp->id = $element->logistics->getId();
                    $tmp->parent = null; if ($element->parent !== null) {$tmp->parent = $element->parent->getId();}
                    $tmp->children = [];
                    $tmp->rows = 0;
                    array_push($tree[$i]->children, $tmp); unset($tmp);

                    return true;
                } else {
                    if ($this->putInTree($element, $tree[$i]->children)) {
                        return true;
                    }
                }
            }

            return false;
        } else {
            //Добавляем в корень
            $tmp = new \stdClass;
            $tmp->element = $element;
            $tmp->id = $element->logistics->getId();
            $tmp->parent = null; if ($element->parent !== null) {$tmp->parent = $element->parent->getId();}
            $tmp->children = [];
            $tmp->rows = 0;
            $tree[] = $tmp; unset($tmp);

            return true;
        }
    }

    /**
     * Вспомогательная функция для установки rowspan для дерева логистики
     */
    private function setRows(&$element) {
        if (sizeof($element->children) == 0) {
            $element->rows = 1;
        } else {
            for ($i=0; $i<sizeof($element->children); $i++) {
                $element->rows += $this->setRows($element->children[$i]);
            }
        }

        return $element->rows;
    }

    /**
     * Вспомогательная функция для конвертации дерева в матрицу
     */
    private function treeToMatrix($node, &$matrix, $index = 0) {
        $cell = new \stdClass;
        $cell->value = $node->element;
        $cell->children = sizeof($node->children);
        $cell->rowspan = $node->rows;
        $cell->colspan = 1;

        if (!isset($matrix[$index]) || !array($matrix[$index])) {$matrix[$index] = [];}
        array_push($matrix[$index], $cell); unset($cell);
        
        for ($i=0; $i<sizeof($node->children); $i++) {
            $index = $this->treeToMatrix($node->children[$i], $matrix, $index);
        }
        $index++;

        $matrix = array_values($matrix);

        return $index;
    }

    /**
     * Просмотр активных счетов
     * @Route("/applications/bills/in-work/view", methods={"GET"})
     * @IsGranted("ROLE_USER")
     */
    public function recieveBillForm(
        Request $request, 
        BillsRepository $billsRepository, 
        BillsMaterialsRepository $billsMaterialsRepository, 
        BillsStatusesRepository $billsStatusesRepository, 
        DocumentsRepository $documentsRepository,
        LogisticsRepository $logisticsRepository,
        OfficesRepository $officesRepository,
        ProvidersRepository $providersRepository,
        StatusesOfBillsRepository $statusesOfBillsRepository,
    ): Response
    {
        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        if (in_array('ROLE_THIEF', $roles)) {
            return new RedirectResponse('/applications');
        }

        $id = $request->query->get('id');
        if ($id === null || !is_numeric($id)) {
            return new RedirectResponse('/applications');
        }

        //Получаем счет
        $bill = $billsRepository->findBy(array('id' => $id));
        if (is_array($bill)) {$bill = array_shift($bill);}

        if ($bill === null) {
            return new RedirectResponse('/applications');
        }

        //Подгружаем метаданные по счету
        $tmp = explode('.', basename($bill->getPath())); $ext = end($tmp); unset($tmp);

        $params = [
            'path' => $bill->getPath(),
            'name' => basename($bill->getPath()),
            'title' => pathinfo($bill->getPath())['filename'],
            'ext' => $ext,
            'type' => $bill->getFileType(),
            'sum' => $bill->getSum(),
            'currency' => $bill->getCurrency(),
            'id' => $bill->getId()
        ];

        //Определяем класс кнопки и иконку
        //Получаем статус счета
        $status = $this->entityManager->getRepository(BillsStatuses::class)->findBy(array('bill' => $bill->getId()), array('datetime' => 'DESC'));
        if (is_array($status)) {$status = array_shift($status);}

        $params['class'] = $status->getStatus()->getClassBtn(); 
        $params['icon'] = $status->getStatus()->getIcon();
        $params['status'] = $status->getStatus()->getTitle();
        $params['style'] = $status->getStatus()->getClassText();

        $billObj = $params; unset($params);

        //Получаем поставщика
        $provider = $providersRepository->findBy(array('inn' => $bill->getInn()));
        if (is_array($provider)) {$provider = array_shift($provider);} else {$provider = null;}

        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        //Получаем список материалов по счету
        $materials = $billsMaterialsRepository->findBy(array('bill' => $id), array('id' => 'ASC'));

        //Статусы
        $statuses = $billsStatusesRepository->findBy( array('bill' => $id), array('datetime' => 'DESC') );

        //Получаем массив заявок
        $materialsIDs = []; //Массив ID заявок (пригодится для выборки по логистике)
        $applications = [];
        foreach ($materials as $material) {
            $materialsIDs[] = $material->getMaterial()->getId();
            $exists = false;
            for ($i=0; $i<sizeof($applications); $i++) {
                if ($material->getMaterial()->getApplication()->getId() == $applications[$i]->getId()) {
                    $exists = true; break;
                }
            }

            if (!$exists) {
                $applications[] = $material->getMaterial()->getApplication();
            }
        }

        //Проверяем может ли пользователь смотреть содержимое этого счета
        $canSee = false;
        if (in_array('ROLE_SUPERVISOR', $roles) || in_array('ROLE_LOGISTICS', $roles) || in_array('ROLE_WATCHER', $roles)) {
            $canSee = true;
        }

        if (in_array('ROLE_EXECUTOR', $roles)) {
            //Если счет в заявке где пользователь ответственный
    
            if ($bill->getUser()->getId() == $this->security->getUser()->getId()) {
                $canSee = true;
            }
        }

        if (in_array('ROLE_CREATOR', $roles)) {
            //Если пользователь создатель заявки
            foreach ($applications as $application) {
                if ($application->getAuthor()->getId() == $this->security->getUser()->getId()) {
                    $canSee = true;
                    break;
                }
            }
        }

        if (!$canSee) {
            return new RedirectResponse('/applications');
        }
    
        //Получаем документы к счету
        $documents = [];
        $objDocuments = $documentsRepository->findBy( array('bill' => $id) );
        foreach ($objDocuments as $file) {                
            $tmp = explode('.', basename($file->getPath())); $ext = end($tmp); unset($tmp);

            $params = [
                'path' => $file->getPath(),
                'name' => basename($file->getPath()),
                'title' => pathinfo($file->getPath())['filename'],
                'ext' => $ext,
                'type' => $file->getFileType(),
                'id' => $file->getId(),
                'user' => $file->getUser()->getId()
            ];

            //Определяем класс кнопки и иконку
            $params['class'] = 'btn-outline-secondary'; $params['icon'] = 'bi-file-image';
            if (in_array($ext, ['doc', 'docx'])) {$params['class'] = 'btn-outline-primary'; $params['bi-file-richtext'] = '';}
            if (in_array($ext, ['xls', 'xlsx'])) {$params['class'] = 'btn-outline-success'; $params['icon'] = 'bi-file-bar-graph';}
            if (in_array($ext, ['pdf'])) {$params['class'] = 'btn-outline-danger'; $params['icon'] = 'bi-file-pdf';}
            if (in_array($ext, ['txt'])) {$params['class'] = 'btn-outline-secondary'; $params['icon'] = 'bi-file-text';}
            if (in_array($ext, ['htm', 'html'])) {$params['class'] = 'btn-outline-secondary'; $params['icon'] = 'bi-file-code';}

            $documents[] = $params; unset($params);
        }
    
        //Добавляем информацию по логистике
        $arrLogistics = [];
        $logistics = $this->entityManager->getRepository(LogisticsMaterials::class)->createQueryBuilder('lm')
        ->select('IDENTITY(lm.logistic) AS logistic, COUNT(lm.material) AS cnt')
        ->where('lm.material IN (:materials)')
        ->join('App\Entity\Logistics', 'l', 'WITH' ,'lm.logistic=l.id')
        ->andWhere('(l.bill = :bid) OR l.bill IS NULL')
        ->groupBy('lm.logistic')
        ->setParameter('materials', $materialsIDs)
        ->setParameter('bid', $id)
        ->distinct()
        ->getQuery()
        ->getResult();

        $tmpLogisticsDates = [];
        $tmpLogisticsIDs = [];
        $colors = ['alert-primary', 'alert-secondary', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info', 'alert-light', 'alert-dark'];
        $colorIndex = -1;
        for ($i=0; $i<sizeof($logistics); $i++) {
            $log = $logisticsRepository->findBy( array('id' => $logistics[$i]['logistic']) );
            if (is_array($log)) {$log = array_shift($log);}
            $tmp = new \stdClass;
            $tmp->logistics = $log;
            $tmp->materials = $logistics[$i]['cnt'];
            $tmp->color = '';

            //Получаем родителя
            if (is_numeric($log->getParent())) {
                $logistics_ = $logisticsRepository->findBy( array('id' => $log->getParent()) );
                if (is_array($logistics_)) {$logistics_ = array_shift($logistics_);}
                $tmp->parent = $logistics_;
            } else {
                $tmp->parent = null;    
            }
            
            $arrLogistics[] = $tmp;
            $tmpLogisticsDates[] = $log->getDate()->getTimestamp();
            $tmpLogisticsIDs[] = $log->getId();
        }

        //Сортируем массив $arrLogistics
        for ($i=0; $i<sizeof($tmpLogisticsDates); $i++) {
            for ($j=$i; $j<sizeof($tmpLogisticsDates); $j++) {
                if ($tmpLogisticsDates[$i] > $tmpLogisticsDates[$j] || ($tmpLogisticsDates[$i] == $tmpLogisticsDates[$j] && $tmpLogisticsIDs[$i] > $tmpLogisticsIDs[$j])) {
                    $tmp = $tmpLogisticsDates[$i];
                    $tmpLogisticsDates[$i] = $tmpLogisticsDates[$j];
                    $tmpLogisticsDates[$j] = $tmp;

                    $tmp = $tmpLogisticsIDs[$i];
                    $tmpLogisticsIDs[$i] = $tmpLogisticsIDs[$j];
                    $tmpLogisticsIDs[$j] = $tmp;

                    $tmp = $arrLogistics[$i];
                    $arrLogistics[$i] = $arrLogistics[$j];
                    $arrLogistics[$j] = $tmp;
                }
            }    
        }

        //Получаем массив для дерева
        $logTree = [];
        $logStack = $arrLogistics;

        $n = 0; // Защита от переполнения
        while (sizeof($logStack) > 0) {
            $n++;
            $element = array_shift($logStack);
            if (!$this->putInTree($element, $logTree)) {
                array_push($logStack, $element);
            }
            if ($n > 1000) {break;}
        }

        //Получаем значение rowspan
        for ($i=0; $i<sizeof($logTree); $i++) {
            $this->setRows($logTree[$i]);
        }

        //Преобразовываем дерево в матрицу, на основании которой будем рисовать таблицу
        $logMatrix = []; $index = 0;
        for ($i=0; $i<sizeof($logTree); $i++) {
            $index = $this->treeToMatrix($logTree[$i], $logMatrix, $index);
        }

        unset($tmp, $tmpLogisticsDates, $tmpLogisticsIDs, $logStack);

        //Получаем всевозможные статусы счетов для списка
        $arrStatuses = $statusesOfBillsRepository->findBy(array(), array('id' => 'ASC'));

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications';
        $breadcrumbs[0]->title = 'Активные заявки';

        if (in_array('ROLE_SUPERVISOR', $roles) || in_array('ROLE_EXECUTOR', $roles)) {
            $breadcrumbs[1] = new \stdClass();
            $breadcrumbs[1]->href = '/applications/bills/in-work';
            $breadcrumbs[1]->title = 'Счета в работе';
        }

        $breadcrumbs[2] = new \stdClass();
        $breadcrumbs[2]->href = '/applications/bills/in-work/view/?id='.$id;
        $breadcrumbs[2]->title = 'Просмотр счета №'.$bill->getNum().' на сумму '.number_format($bill->getSum(), 2, ',', ' ').' '.$bill->getCurrency();

        return $this->render('bills/view.html.twig', [
            'title' => 'Просмотр счета №'.$bill->getNum().' на сумму '.number_format($bill->getSum(), 2, ',', ' ').' '.$bill->getCurrency(),
            'breadcrumbs' => $breadcrumbs,
            'bill' => $bill,
            'billobj' => $billObj,
            'materials' => $materials,
            'statuses' => $statuses,
            'applications' => $applications,
            'provider' => $provider,
            'documents' => $documents,
            'offices' => $officesRepository->findAll(),
            'matrix' => $logMatrix,
            'billsstatuses' => $arrStatuses,
            'logistics' => $arrLogistics
        ]);
    }

    /**
     * Сохранение счета (принимает данные из формы)
     * @Route("/applications/bills/in-work/view", methods={"POST"}))
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_EXECUTOR')")
     */
    public function saveBill(
        BillsMaterialsRepository $billsMaterialsRepository, 
        BillsStatusesRepository $billsStatusesRepository,
        BillsRepository $billsRepository, 
        DocumentsRepository $documentsRepository,
        MaterialsRepository $materialsRepository, 
        OfficesRepository $officesRepository,
        PhotosRepository $photosRepository,
        Request $request, 
        StatusesOfBillsRepository $statusesOfBillsRepository, 
        StatusesOfApplicationsRepository $statusesOfApplicationsRepository
    ): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('view-bill', $submittedToken)) {
            try {
                $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

                //Получаем общие переменные
                $materials = $request->request->get('material');
                $amounts = $request->request->get('amount');

                $params['docinfo'] = $request->request->get('docinfo'); //Дополнительная информация
                $params['sum'] = $request->request->get('sum'); //Сумма
                $params['dateReciept'] = $request->request->get('dateReciept'); //Дата операции
                $params['dateShip'] = $request->request->get('dateShip'); //Дата операции
                $params['userOfficeShip'] = $request->request->get('userOfficeShip'); //Подразделение куда производится отправка
                $params['way'] = $request->request->get('way'); //Сопсоб отправки
                $params['track'] = $request->request->get('track'); //Номер для отслеживания
                $params['photos'] = $request->request->get('photos'); //Фотографии
                $params['files'] = $request->request->get('files'); //Документы

                //Проверяем по параметрам откуда был вызов, из формы просмотра счета или из формы выбора материалов при приходе
                if ($request->request->get('bid') === null) {
                    //Вызов из формы выбора материалов при приходе
                    //Ожидаем массив id счетов
                    $resultArray = [];
                    $materials = $request->request->get('material');
                    $amounts = $request->request->get('amount');

                    $billsIds = $request->request->get('bill');
                    foreach ($billsIds as $billId) {
                        $exist = false;
                        foreach ($resultArray as $row) {if ($row['billId'] == $billId) {$exist = true; break;}}

                        if (!$exist) {
                            //Добавляем
                            $tmp = [];
                            $tmp['billId'] = $billId;
                            $tmp['materials'] = [];
                            $tmp['amounts'] = [];

                            for ($i=0; $i < sizeof($billsIds); $i++) {
                                if ($billsIds[$i] == $billId) {
                                    $tmp['materials'][] = $materials[$i];
                                    $tmp['amounts'][] = $amounts[$i];
                                }
                            }

                            $resultArray[] = $tmp; unset($tmp);
                        }
                    }

                    $type = 0; //Получение
                    $logistics = []; //Возвращаемое значение при успешном выполнении
                    foreach ($resultArray as $bill) {
                        $logistics[] = $this->recieveMaterials($type, $bill['billId'], $bill['materials'], $bill['amounts'], $params);
                    }
                    $logistics = json_encode($logistics);

                    //Дополняем результат отправителем по заявке
                    $applications = [];
                    for ($i=0; $i<sizeof($bill['materials']); $i++) {
                        //Получаем строку в BillsMaterials
                        $billMaterial = $billsMaterialsRepository->findBy(array('id' => $materials[$i]));
                        if (is_array($billMaterial)) {$billMaterial = array_shift($billMaterial);}
         
                        $exist = false;
                        foreach ($applications as $application) {
                            if ($application->getId() == $billMaterial->getMaterial()->getApplication()->getId()) {$exist = true; break;}
                        }
                        if (!$exist) {$applications[] = $billMaterial->getMaterial()->getApplication();}
                    }

                    $appinfo = '';
                    foreach ($applications as $application) {
                        if (!empty($appinfo)) {$appinfo .= ', ';}
                        $appinfo .= 'Заявка '.$application->getId().' ('.$application->getAuthor()->getShortUsername().')';
                    }
                    $result[2] = $appinfo;
                } else {
                    //Вызов из формы просмотра счета
                    $billId = $request->request->get('bid');
                    $type = $request->request->get('type');

                    if ($billId === null || ($materials !== null && $amounts !== null && sizeof($materials) != sizeof($amounts))) {
                        $result[0] = 0;
                        $result[1] = 'Ошибка во входных данных.';
                        return new JsonResponse($result);
                    }

                    $logistics = ''; //Возвращаемое значение при успешном выполнении
                    $this->recieveMaterials($type, $billId, $materials, $amounts, $params);
                }
    
                $this->entityManager->getConnection()->commit();

                $result[0] = 1;
                $result[1] = $logistics;
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
     * Отгрузка/получение материалов
     */
    private function recieveMaterials($type, $billId, $materials, $amounts, $params) {
        //Получаем необходимые репозитории
        $billsRepository = $this->entityManager->getRepository(Bills::class);
        $billsMaterialsRepository = $this->entityManager->getRepository(BillsMaterials::class);
        $statusesOfBillsRepository = $this->entityManager->getRepository(StatusesOfBills::class);
        $materialsRepository = $this->entityManager->getRepository(Materials::class);
        $billsStatusesRepository = $this->entityManager->getRepository(BillsStatuses::class);
        $officesRepository = $this->entityManager->getRepository(Offices::class);
        $statusesOfApplicationsRepository = $this->entityManager->getRepository(StatusesOfApplications::class);
        $photosRepository = $this->entityManager->getRepository(Photos::class);
        $documentsRepository = $this->entityManager->getRepository(Documents::class);

        //Получаем счет
        $objBill = $billsRepository->findBy(array('id' => $billId));
        if (is_array($objBill)) {$objBill = array_shift($objBill);}

        if ($materials !== null) {
            $objMaterials = [];

            for ($i=0; $i<sizeof($materials); $i++) {
                //Получаем строку в BillsMaterials
                $billMaterial = $billsMaterialsRepository->findBy(array('id' => $materials[$i]));
                if (is_array($billMaterial)) {$billMaterial = array_shift($billMaterial);}

                $objMaterials[] = $billMaterial->getMaterial();

                $billMaterial->setRecieved((float)$billMaterial->getRecieved() + (float)$amounts[$i]);

                $this->entityManager->persist($billMaterial);
                $this->entityManager->flush();

                unset($billMaterial);
            }

            //Добавляем статус "Получен" или "Частично получен" к счету

            //Проверяем, есть ли в счете незакрытые позиции
            $notReadyMaterials = $billsMaterialsRepository->createQueryBuilder('bm')
            ->select('bm.id')
            ->where('bm.bill = :bill')
            ->andWhere('bm.amount <> bm.recieved')
            ->setParameter('bill', $billId)
            ->join('App\Entity\Materials', 'm', 'WITH' ,'bm.material=m.id')
            ->groupBy('bm.id, m.isDeleted, m.cash, m.impossible')
            ->having('m.isDeleted = FALSE AND m.cash = FALSE AND m.impossible = FALSE')
            ->getQuery()
            ->getResult();

            $statusId = 6; //Частично получен
            if (sizeof($notReadyMaterials) == 0) {$statusId = 5;} //Получен
            $objStatus = $statusesOfBillsRepository->findby(array('id' => $statusId));
            if (is_array($objStatus)) {$objStatus = array_shift($objStatus);}

            //Добавляем статус к счету
            $billStatus = new BillsStatuses;
            $billStatus->setBill($objBill);
            $billStatus->setStatus($objStatus);
            $this->entityManager->persist($billStatus);
            $this->entityManager->flush();

            //Проверяем, можем ли поставить заявке статус "Выполнена"
            //Получаем список заявок
            $billMaterials = $billsMaterialsRepository->findBy(array('bill' => $billId));
            $applications = [];
            foreach ($billMaterials as $billMaterial) {
                $exists = false;
                for ($i=0; $i<sizeof($applications); $i++) {
                    if ($applications[$i]->getId() == $billMaterial->getMaterial()->getApplication()->getId()) {
                        $exists = true; break;
                    }
                }

                if (!$exists) {
                    $applications[] = $billMaterial->getMaterial()->getApplication();
                }
            }

            foreach ($applications as $application) {
                //Работаем с конкретной заявкой
                //Получаем материалы в заявке
                $applicationMaterials = $materialsRepository->createQueryBuilder('m')
                ->where('m.application = :application')
                ->andWhere('m.isDeleted = FALSE')
                ->andWhere('m.cash = FALSE')
                ->andWhere('m.impossible = FALSE')
                ->setParameter('application', $application->getId())
                ->getQuery()
                ->getResult();

                $mIds = [];
                foreach ($applicationMaterials as $material) {$mIds[] = $material->getId();}

                $closed = true;

                //Получаем список счетов
                $bills = [];
                foreach ($mIds as $material) {
                    $arrBillsMaterials = $billsMaterialsRepository->findBy(array('material' => $material));

                    if (sizeof($arrBillsMaterials) == 0) {
                        $closed = false; break;
                    }

                    foreach ($arrBillsMaterials as $billMaterial) {
                        $bills[] = $billMaterial->getBill()->getId();
                    }
                }
                $bills = array_unique($bills);

                foreach ($bills as $bill) {
                    if ($billsStatusesRepository->findBy(array('bill' => $bill), array('datetime' => 'DESC'))[0]->getStatus()->getId() != 5) { //Получен
                        $closed = false; break;
                    }
                }

                if ($closed) {
                    //Можем закрывать заявку

                    //Обновляем дату закрытия
                    $dateClose = new \DateTime();
                    $application->setDateClose($dateClose);
                    $this->entityManager->persist($application);
                    $this->entityManager->flush();

                    //Получаем статус
                    $objStatus = $statusesOfApplicationsRepository->findBy( array('id' => 3) );
                    if (is_array($objStatus)) {$objStatus = array_shift($objStatus);}

                    //Добавляем статус в базу
                    $applicationStatus = new ApplicationsStatuses;
                    $applicationStatus->setApplication($application);
                    $applicationStatus->setStatus($objStatus);

                    $this->entityManager->persist($applicationStatus);
                    $this->entityManager->flush();
                }
            }

            //Получаем информацию об отгрузке/получению
            if ($type !== null && is_numeric($type)) {
                $objLogistics = new Logistics;

                //Добавляем информацию о счете
                $objLogistics->setBill($objBill);

                //Если нужно, добавляем информацию о документе и сумме
                if (!empty($params['docinfo'])) {$objLogistics->setDocInfo(trim($params['docinfo']));}
                if (!empty($params['sum'])) {$objLogistics->setSum(trim($params['sum']));}

                if ($type == 0) {
                    //Получение
                    $dateOp = new \DateTime();
                    $dateOp->setTimestamp(strtotime($params['dateReciept'].' 00:00:01')); //Дата операции
                    $objOffice = $this->security->getUser()->getOffice(); //Объект - локация пользователя

                    $objLogistics->setDate($dateOp);
                    $objLogistics->setType(0); //Получение
                    $objLogistics->setOffice($objOffice);
                    $objLogistics->setUser($this->security->getUser());
                    $this->entityManager->persist($objLogistics);
                } else {
                    //Отгрузка
                    $dateOp = new \DateTime();
                    $dateOp->setTimestamp(strtotime($params['dateShip'].' 00:00:01')); //Дата операции
                    $objOffice = $officesRepository->findBy( array('id' => $params['userOfficeShip']) );
                    if (is_array($objOffice)) {$objOffice = array_shift($objOffice);}
                    $way = $params['way']; //Сопсоб отправки
                    $track = $params['track']; //Номер для отслеживания

                    $objLogistics->setDate($dateOp);
                    $objLogistics->setType(1); //Отгрузка
                    $objLogistics->setOffice($objOffice);
                    $objLogistics->setWay($way);
                    $objLogistics->setTrack($track);
                    $objLogistics->setUser($this->security->getUser());
                    $this->entityManager->persist($objLogistics);
                }
                $this->entityManager->flush();

                //Добавляем связи в LogisticsMaterials
                for ($i=0; $i<sizeof($objMaterials); $i++) {
                    $objLogisticsMaterials = new LogisticsMaterials;
                    $objLogisticsMaterials->setMaterial($objMaterials[$i]);
                    $objLogisticsMaterials->setLogistics($objLogistics);
                    $objLogisticsMaterials->setAmount((float)$amounts[$i]);
                    $this->entityManager->persist($objLogisticsMaterials);
                }
                $this->entityManager->flush();

                //Добавляем фотографии
                $arrPhotos = json_decode($params['photos']);
                if ($arrPhotos !== null ) {
                    foreach ($arrPhotos as $photo) {
                        $objPhoto = $photosRepository->findBy( array('id' => $photo) );
                        if (is_array($objPhoto)) {$objPhoto = array_shift($objPhoto);}
                        $objPhoto->setLogistics($objLogistics);
                        $this->entityManager->persist($objPhoto);
                    }
                }
                $this->entityManager->flush();
            }
        }

        //Добавляем файлы
        $arrFiles = json_decode($params['files']);
        if ($arrFiles !== null ) {
            foreach ($arrFiles as $file) {
                $objDocument = $documentsRepository->findBy( array('id' => $file) );
                if (is_array($objDocument)) {$objDocument = array_shift($objDocument);}
                $objDocument->setBill($objBill);
                $this->entityManager->persist($objDocument);
            }
        }
        $this->entityManager->flush();

        if (isset($objLogistics)) {
            return $objLogistics->getId();
        } else {
            return true;
        }
    }

    /**
     * Сохранение дополнительной информации по счету (принимает данные из формы)
     * @Route("/applications/bills/in-work/save-additional-data", methods={"POST"}))
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_EXECUTOR')")
     */
    public function saveBillAdditionalData(
        BillsMaterialsRepository $billsMaterialsRepository,
        BillsRepository $billsRepository, 
        BillsStatusesRepository $billsStatusesRepository,
        MaterialsRepository $materialsRepository,
        Request $request, 
        StatusesOfBillsRepository $statusesOfBillsRepository,
    ): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('save-additional-bill', $submittedToken)) {
            $billId = $request->request->get('id');

            if ($billId === null) {
                $result[] = 0;
                $result[] = 'Ошибка во входных данных.';
                return new JsonResponse($result);
            }
    
            $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

            try {
                //Получаем счет
                $objBill = $billsRepository->findBy(array('id' => $billId));
                if (is_array($objBill)) {$objBill = array_shift($objBill);}

                //Получаем ИНН
                $billInn = $request->request->get('billInn');
                if ($billInn !== null) {$objBill->setInn($billInn);} unset($billInn);

                //Получаем номер счета
                $billNum = $request->request->get('billNum');
                if ($billNum !== null) {$objBill->setNum($billNum);} unset($billNum);

                //Получаем сумму и валюту
                $billSum = $request->request->get('billSum');
                if ($billSum !== null) {$objBill->setSum($billSum);} unset($billSum);
                $billCurrency = $request->request->get('billCurrency');
                if ($billCurrency !== null) {$objBill->setCurrency($billCurrency);} unset($billCurrency);

                //Получаем комментарий
                $billNote = $request->request->get('billNote');
                if ($billNote !== null) {$objBill->setNote($billNote);} unset($billNote);

                //Получаем доп информацию
                $billComment = $request->request->get('billComment');
                if ($billComment !== null) {$objBill->setComment($billComment);} unset($billComment);

                //Получаем дату закрытия
                $dateClose = new \DateTime();
                $dateClose->setTimestamp(strtotime($request->request->get('billDate')));
                if ($request->request->get('billDate') !== null) {$objBill->setDateClose($dateClose);}

                $this->entityManager->persist($objBill);
                $this->entityManager->flush();

                //Разбираемся со статусами
                $statusId = $request->request->get('billStatus');
                $objStatus = $statusesOfBillsRepository->findby(array('id' => $statusId));
                if (is_array($objStatus)) {$objStatus = array_shift($objStatus);}

                //Проверяем текущий статус
                $currentStatus = $request->request->get('currentStatus');
                if ($currentStatus === null || empty($currentStatus) || (int)$currentStatus != $objStatus->getId()) {
                    //Добавляем статус к счету
                    $billStatus = new BillsStatuses;
                    $billStatus->setBill($objBill);
                    $billStatus->setStatus($objStatus);
                    $this->entityManager->persist($billStatus);
                    $this->entityManager->flush();
                }

                //Возможно стоит обновить дату закрытия заявки
                if ($request->request->get('billDate') !== null) {
                    //Получаем массив заявок
                    $applications = [];

                    //Для начала надо получить материалы, привязанные к данному счету
                    $materials = [];
                    $billMaterials = $billsMaterialsRepository->findBy(array('bill' => $objBill->getId()));
                    foreach ($billMaterials as $billMaterial) {
                        $materials[] = $billMaterial->getMaterial();
                    }
                    
                    foreach ($materials as $material) {
                        $flag = false;
                        foreach ($applications as $application) {
                            if ($application->getId() == $material->getApplication()->getId()) {$flag = true; break;}
                        }

                        if (!$flag) {$applications[] = $material->getApplication();}
                    }

                    unset($billMaterials, $materials);

                    foreach ($applications as $application) {
                        //Проверяем, стоит ли обновить дату закрытия заявки
                        $appDateClose = new \DateTime();
                        $appDateClose->setTimestamp(strtotime('1970-01-01 00:00:01'));
                        if ($application->getDateClose() !== null) {$appDateClose = $application->getDateClose();}

                        if ($dateClose < $appDateClose) {
                            //Если дата сдвинулась назад
                            //Получаем все счета по заявке, берем максимальную дату и прописываем ее как дату закрытия
                            $bills = [];
                            $materials = $materialsRepository->findBy(array('application' => (int)$application->getId()));
                            foreach ($materials as $material) {
                                $billsMaterial = $billsMaterialsRepository->findBy(array('material' => $material->getId()));
                                foreach ($billsMaterial as $billMaterial) {
                                    $flag = false;
                                    foreach ($bills as $bill) {
                                        if ($bill->getId() == $billMaterial->getBill()->getId()) {$flag = true; break;}
                                    }
                
                                    if (!$flag) {$bills[] = $billMaterial->getBill();}
                                }
                            }

                            foreach ($bills as $bill) {
                                if ($bill->getDateClose() > $dateClose) {
                                    $dateClose = $bill->getDateClose();
                                }
                            }


                        }

                        $application->setDateClose($dateClose);

                        //Записываем
                        $this->entityManager->persist($application);
                        $this->entityManager->flush();
                    }

                    unset($materials, $billsMaterial, $dateClose);
                }

                $this->entityManager->getConnection()->commit();

                $result[] = 1;
                $result[] = '';
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
     * Сохранение информации по поставщику (принимает данные из формы)
     * @Route("/applications/bills/provider", methods={"POST"}))
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_EXECUTOR')")
     */
    public function provider(
        Request $request, 
        ProvidersRepository $providersRepository
    ): JsonResponse
    {
        $result = [];

        $title = $request->request->get('title');
        $inn = $request->request->get('inn');
        $address = $request->request->get('address');
        $phone = $request->request->get('phone');
        $comment = $request->request->get('comment');

        if ($inn === null) {
            $result[] = 0;
            $result[] = 'Ошибка во входных данных.';
            return new JsonResponse($result);
        }

        try {
            //Проверяем, есть ли запись
            $objProvider = $providersRepository->findBy( array('inn' => $inn) );

            if (sizeof($objProvider) == 0) {
                //Добавляем
                $objProvider = new Providers;

                $objProvider->setTitle($title);
                $objProvider->setInn($inn);
                $objProvider->setAddress($address);
                $objProvider->setPhone($phone);
                $objProvider->setComment($comment);

                $this->entityManager->persist($objProvider);
                $this->entityManager->flush();
            } else {
                //Редактируем
                if (is_array($objProvider)) {$objProvider = array_shift($objProvider);}

                if (empty($title) && empty($address) && empty($phone) && empty($comment)) {
                    //Вся информация стерта, удаляем
                    $this->entityManager->remove($objProvider);
                    $this->entityManager->flush();
                } else {
                    $objProvider->setTitle($title);
                    $objProvider->setInn($inn);
                    $objProvider->setAddress($address);
                    $objProvider->setPhone($phone);
                    $objProvider->setComment($comment);
    
                    $this->entityManager->persist($objProvider);
                    $this->entityManager->flush();
                }
            }

            $result[] = 1;
            $result[] = '';
        } catch (Exception $e) {
            $result[] = 0;
            $result[] = $e;
            //throw $e;
        }

        return new JsonResponse($result);
    }

    /**
     * Загрузка документов
     * @Route("/applications/bills/upload-file", methods={"POST"})
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_EXECUTOR')")
     */
    public function uploadDocument(Request $request, ValidatorInterface $validator, UsersRepository $usersRepository): JsonResponse
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
            $dirname = uniqid(); while (file_exists($this->getParameter('documents_directory').'/'.$dirname)) {$dirname = uniqid();} 
            mkdir($this->getParameter('documents_directory').'/'.$dirname, 0755);

            try {
                $file->move(
                    $this->getParameter('documents_directory').'/'.$dirname,
                    $file->getClientOriginalName()
                );

                //Добавляем информацию в базу
                $dbFile = new Documents;
                $dbFile->setPath($dirname.'/'.$file->getClientOriginalName());
                $dbFile->setUser($usersRepository->findBy(array('id' => $this->security->getUser()->getId()))[0]);

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
     * Удаление документов
     * @Route("/applications/bills/delete-file", methods={"POST"})
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_EXECUTOR')")
     */
    public function deleteDocument(Request $request, DocumentsRepository $documentsRepository): JsonResponse
    {
        try {
            $file = $documentsRepository->findBy( array('id' => $request->request->get('id')) );
            if (is_array($file)) {$file = array_shift($file);}

            //Получаем роли текущего пользователя
            $roles = $this->security->getUser()->getRoles();

            //Проверяем, может ли пользователь удалить этот файл
            if ($this->security->getUser()->getId() != $file->getUser()->getId() && !in_array('ROLE_SUPERVISOR', $roles)) {
                return new JsonResponse(false);    
            }

            $this->entityManager->remove($file);
            $this->entityManager->flush();

            if (file_exists($this->getParameter('documents_directory').'/'.$file->getPath())) {
                unlink($this->getParameter('documents_directory').'/'.$file->getPath());
                rmdir(dirname($this->getParameter('documents_directory').'/'.$file->getPath()));
            }

            return new JsonResponse(true);
        } catch (FileException $e) {
            return new JsonResponse(false);
        }
    }

    /**
     * Поиск по cчетам
     * @Route("/applications/bills/search", methods={"GET"}))
     * @IsGranted("ROLE_USER")
     */
    public function searchBills(Request $request, BillsRepository $billsRepository, BillsMaterialsRepository $billsMaterialsRepository): Response
    {
        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        $q = $request->query->get('q');
        if ($q === null || empty($q)) {
            $title = 'Поиск по счетам и спецификациям';
        } else {
            $title = 'Результаты поиска по счетам и спецификациям';
        }

        $results = [];

        if (mb_strlen($q) > 2) {
            //Выполняем поиск по номерам
            $bills = $billsRepository->findLikeNum($q);

            foreach ($bills as $bill) {
                $result = new \stdClass;
                $result->bill = $bill;
                $result->num = $bill->getNum();
                $result->inn = '';
                $result->comment = '';
                $result->note = '';
                $result->filename = basename($bill->getPath());
                $results[] = $result;
                unset($result);
            }

            unset($bills);

            //Выполняем поиск по ИНН
            $bills = $billsRepository->findLikeInn($q);

            foreach ($bills as $bill) {
                $exists = false;
                for ($i=0; $i<sizeof($results); $i++) {
                    if ($results[$i]->bill->getId() == $bill->getId()) {
                        $exists = true;
                        //Счет уже существует в результатах, добавляем в него ИНН
                        $results[$i]->inn = $bill->getInn();
                    }
                }

                if (!$exists) {
                    //Добавляем заявку в результаты
                    $result = new \stdClass;
                    $result->bill = $bill;
                    $result->num = '';
                    $result->inn = $bill->getInn();
                    $result->comment = '';
                    $result->note = '';
                    $result->filename = basename($bill->getPath());
                    $results[] = $result;
                    unset($result);
                }
            }

            unset($bills);

            //Выполняем поиск по доп. информации
            $bills = $billsRepository->findLikeComment($q);

            foreach ($bills as $bill) {
                $exists = false;
                for ($i=0; $i<sizeof($results); $i++) {
                    if ($results[$i]->bill->getId() == $bill->getId()) {
                        $exists = true;
                        //Счет уже существует в результатах, добавляем в него доп. информацию
                        $results[$i]->comment = $bill->getComment();
                    }
                }

                if (!$exists) {
                    //Добавляем заявку в результаты
                    $result = new \stdClass;
                    $result->bill = $bill;
                    $result->num = '';
                    $result->inn = '';
                    $result->comment = $bill->getComment();
                    $result->note = '';
                    $result->filename = basename($bill->getPath());
                    $results[] = $result;
                    unset($result);
                }
            }

            unset($bills);

            //Выполняем поиск по комментарию
            $bills = $billsRepository->findLikeNote($q);

            foreach ($bills as $bill) {
                $exists = false;
                for ($i=0; $i<sizeof($results); $i++) {
                    if ($results[$i]->bill->getId() == $bill->getId()) {
                        $exists = true;
                        //Счет уже существует в результатах, добавляем в него комментарий
                        $results[$i]->note = $bill->getNote();
                    }
                }

                if (!$exists) {
                    //Добавляем заявку в результаты
                    $result = new \stdClass;
                    $result->bill = $bill;
                    $result->num = '';
                    $result->inn = '';
                    $result->comment = '';
                    $result->note = $bill->getNote();
                    $result->filename = basename($bill->getPath());
                    $results[] = $result;
                    unset($result);
                }
            }

            unset($bills);

            //Выполняем поиск по имени файла
            $bills = $billsRepository->findLikeNote($q);

            foreach ($bills as $bill) {
                $exists = false;
                for ($i=0; $i<sizeof($results); $i++) {
                    if ($results[$i]->bill->getId() == $bill->getId()) {
                        $exists = true;
                        //Счет уже существует в результатах, добавляем в него имя файла
                        $results[$i]->filename = basename($bill->getPath());
                    }
                }

                if (!$exists) {
                    //Добавляем заявку в результаты
                    $result = new \stdClass;
                    $result->bill = $bill;
                    $result->num = '';
                    $result->inn = '';
                    $result->comment = '';
                    $result->note = '';
                    $result->filename = basename($bill->getPath());
                    $results[] = $result;
                    unset($result);
                }
            }

            unset($bills);

            //Убираем из результатов те заявки которые пользователь не может видеть
            $results_ = [];

            foreach ($results as $result) {
                //Проверяем наличие прав просмотра
                $canSee = false;
                
                if (in_array('ROLE_SUPERVISOR', $roles)) {$canSee = true;}
                if (in_array('ROLE_CREATOR', $roles)) {
                    //Получаем список материалов
                    $arrBillsMaterials = $billsMaterialsRepository->findBy(array('bill' => $result->bill->getId()));
                    foreach ($arrBillsMaterials as $billMaterial) {
                        $material = $billMaterial->getMaterial();
                        if ($material->getApplication() !== null) {
                            if ($material->getApplication()->getAuthor()->getId() == $this->security->getUser()->getId()) {
                                $canSee = true;
                                break;
                            }
                        }
                    }
                }
                if (in_array('ROLE_EXECUTOR', $roles)) {
                    $arrBillsMaterials = $billsMaterialsRepository->findBy(array('bill' => $result->bill->getId()));
                    foreach ($arrBillsMaterials as $billMaterial) {
                        $material = $billMaterial->getMaterial();
                        if ($material->getResponsible() !== null) {
                            if ($material->getResponsible()->getId() == $this->security->getUser()->getId()) {
                                $canSee = true;
                                break;
                            }
                        }
                    }
                }

                if ($canSee) { 
                    $results_[] = $result;
                }
            }

            $results = $results_;
        }

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications';
        $breadcrumbs[0]->title = 'Активные заявки';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/applications/bills/in-work';
        $breadcrumbs[1]->title = 'Счета в работе';
        $breadcrumbs[2] = new \stdClass();
        $breadcrumbs[2]->href = '/applications/bills/search'.(!empty($q) ? '?q='.$q : '');
        $breadcrumbs[2]->title = $title;

        return $this->render('bills/search.html.twig', [
            'title' => $title,
            'breadcrumbs' => $breadcrumbs,
            'q' => $q,
            'results' => $results
        ]);
    }

}