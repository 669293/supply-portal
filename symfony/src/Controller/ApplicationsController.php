<?php
// src/Controller/ApplicationsController.php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Security as SecurityCore;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Applications;
use App\Entity\ApplicationsStatuses;
use App\Entity\BillsMaterials;
use App\Entity\BillsStatuses;
use App\Entity\Files;
use App\Entity\TypesOfEquipment;
use App\Entity\Materials;
use App\Entity\MaterialsComments;
use App\Entity\ResponsibleLog;
use App\Repository\ApplicationsRepository;
use App\Repository\ApplicationsStatusesRepository;
use App\Repository\BillsRepository;
use App\Repository\BillsMaterialsRepository;
use App\Repository\BillsStatusesRepository;
use App\Repository\FilesRepository;
use App\Repository\LogisticsMaterialsRepository;
use App\Repository\MaterialsRepository;
use App\Repository\MaterialsCommentsRepository;
use App\Repository\OfficesRepository;
use App\Repository\ProvidersRepository;
use App\Repository\ResponsibleLogRepository;
use App\Repository\StatusesOfApplicationsRepository;
use App\Repository\StatusesOfBillsRepository;
use App\Repository\TypesOfEquipmentRepository;
use App\Repository\UnitsRepository;
use App\Repository\UsersRepository;

class ApplicationsController extends AbstractController
{
    private $security;
    private $entityManager;

    public function __construct(SecurityCore $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    /**
     * Получаем количество счетов к печати
     */
    public function getPrintBillsCount(): int
    {
        $sql = "SELECT res_.id AS id FROM (SELECT DISTINCT res.bid AS id, (SELECT aps.status FROM applications_statuses aps WHERE aps.application = a.id ORDER BY id DESC OFFSET 0 LIMIT 1) AS status FROM (SELECT bs.bill AS bid, (SELECT bs2.status FROM bills_statuses bs2 WHERE bs2.id = MAX(bs.id)) FROM bills_statuses bs GROUP BY bs.bill) res, applications a, bills_materials bm, materials m WHERE bm.bill = res.bid AND bm.material = m.id AND m.application = a.id AND res.status = 1) res_ WHERE res_.status NOT IN (3,4,5);";
        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $stmt->execute();
        $bills_ = $stmt->fetchAllAssociative();

        return sizeof($bills_);
    }

    /**
     * @Route("/", name="index")
     * @IsGranted("ROLE_USER")
     */
    public function index(
        ApplicationsRepository $applicationsRepository, 
        BillsRepository $billsRepository, 
        BillsMaterialsRepository $billsMaterialsRepository,
        MaterialsRepository $materialsRepository, 
        StatusesOfApplicationsRepository $statusesOfApplicationsRepository
    ): Response
    {
        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        //Если пользователь исполняет роль ответственного
        $supervisor = [];
        if (in_array('ROLE_SUPERVISOR', $roles)) {
            //Настраиваем фильтр
            $filter = new Filter;
            $filter->status = $statusesOfApplicationsRepository->findBy(array('id' => 1));
            if (is_array($filter->status)) {$filter->status = array_shift($filter->status);}
            $filter->isFiltered = true;

            $applications = $applicationsRepository->getList($filter);

            //Получаем заявки, ожидающие назначения ответственных
            // $applications = $materialsRepository->createQueryBuilder('m')
            // ->select('IDENTITY(m.application) AS aid')
            // ->where('m.responsible IS NULL')
            // ->andWhere('m.isDeleted = FALSE')
            // ->andWhere('m.impossible = FALSE')
            // ->andWhere('m.cash = FALSE')
            // ->groupBy('m.application')
            // ->getQuery()
            // ->getResult();

            for ($i=0; $i<sizeof($applications); $i++) {
                $row = new \stdClass;
                $row->application = $applications[$i][0];
                $row->applicationUrgency = $applications[$i]['urgency'];
                $supervisor[] = $row;
                unset($row);
            }
        }

        //Если пользователь исполняет роль исполнителя
        $materialsInWork = [];
        $expiredBills = [];
        $activeApplications = [];
        if (in_array('ROLE_EXECUTOR', $roles)) {
            //Получаем заявки и позиции, по которым не выставлены счета

            $sql = '
                SELECT
                    res.aid AS aid,
                    res.mid AS mid,
                    res.amount AS amount,
                    res.bills AS bills,
                    res.sid AS status
                FROM
                    (SELECT 
                        m.application AS aid, 
                        m.id AS mid, 
                        m.amount AS amount, 
                        SUM(bm.amount) AS bills,
                        (SELECT
                            aps.status
                        FROM
                            applications_statuses aps
                        WHERE
                            aps.application = m.application
                        ORDER BY
                            datetime DESC
                        OFFSET 0 LIMIT 1
                        ) as sid
                    FROM materials m 
                    LEFT JOIN bills_materials bm ON bm.material=m.id
                    WHERE 
                        m.responsible = '.$this->security->getUser()->getId().' AND 
                        m.is_deleted = FALSE AND
                        m.impossible = FALSE AND
                        m.cash = FALSE
                    GROUP BY m.application, m.id, m.amount 
                    ORDER BY m.application ASC, m.num ASC) res
                WHERE 
                    res.sid NOT IN (3,5) AND
                    (res.amount <> res.bills OR 
                    res.bills IS NULL);
            ';
            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAllAssociative();

            foreach ($results as $result) {
                $exists = false;
                foreach ($materialsInWork as $row) {
                    if ($row->application->getId() == $result['aid']) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $objApplication = $applicationsRepository->findBy( array('id' => $result['aid']) );
                    if (is_array($objApplication)) {$objApplication = array_shift($objApplication);}

                    $tmpObj = new \stdClass();
                    $tmpObj->application = $objApplication;
                    $tmpObj->urgency = $applicationsRepository->getUrgency($result['aid']);
                    $tmpObj->materials = [];
                    $materialsInWork[] = $tmpObj;
                    unset($tmpObj);
                }
            }

            foreach ($materialsInWork as $row) {
                foreach ($results as $result) {
                    if ($result['aid'] == $row->application->getId()) {
                        //Добавляем материал в объект
                        $objMaterial = $materialsRepository->findBy( array('id' => $result['mid']) );
                        if (sizeof($objMaterial) > 0) {$objMaterial = $objMaterial[0];}

                        $tmpObj = new \stdClass();
                        $tmpObj->material = $objMaterial;
                        $tmpObj->count = $result['amount'];
                        $tmpObj->done = $result['bills'];
                        if ($tmpObj->done == null) {$tmpObj->done = 0;}
                        $row->materials[] = $tmpObj;
                        unset($tmpObj);
                    }
                }
            }

            //Получаем список счетов по которым провалены сроки
            $sql = "SELECT res.bid AS id FROM (SELECT bs.bill AS bid, (SELECT bs2.status FROM bills_statuses bs2 WHERE bs2.id = MAX(bs.id)) FROM bills_statuses bs GROUP BY bs.bill) res, bills b WHERE res.bid = b.id AND res.status <> 5 AND b.date_close < '".date('Y-m-d')."';";
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
                    $expiredBills[] = $bills_[$i];
                }

                unset($objBill, $billMaterials);
            }

            //Получаем список активных заявок
            // $filter = new Filter;
            // $filter->responsible = $this->security->getUser(); //Текущий пользователь
            // $filter->done = FALSE; //Активные заявки

            // $activeApplications = $applicationsRepository->getList($filter);
        }

        //Если пользователь исполняет роль заказчика
        $notOrderedYet = [];
        if (in_array('ROLE_CREATOR', $roles)) {
            //Получаем заявки и позиции, по которым не выставлены счета
            $sql = '
                SELECT
                    res.aid AS aid,
                    res.mid AS mid,
                    res.amount AS amount,
                    res.bills AS bills                    
                FROM
                    (SELECT 
                        m.application AS aid, 
                        m.id AS mid, 
                        m.amount AS amount, 
                        SUM(bm.amount) AS bills,
                        (
                            SELECT
                                aps."status" AS status
                            FROM
                                applications_statuses aps
                            WHERE
                                aps.application = m.application
                            ORDER BY 
                                aps.datetime DESC
                            OFFSET 0 LIMIT 1
                        ) AS appstatus
                    FROM applications a, materials m
                    LEFT JOIN bills_materials bm ON bm.material=m.id
                    WHERE 
                        m.application = a.id AND
                        a.author = '.$this->security->getUser()->getId().' AND 
                        m.is_deleted = FALSE AND
                        m.impossible = FALSE AND
                        m.cash = FALSE
                    GROUP BY m.application, m.id, m.amount 
                    ORDER BY m.application ASC) res
                WHERE 
                (res.amount <> res.bills OR 
                res.bills IS NULL) AND
                res.appstatus NOT IN (3, 5);
            ';

            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAllAssociative();

            foreach ($results as $result) {
                $exists = false;
                foreach ($notOrderedYet as $row) {
                    if ($row->application->getId() == $result['aid']) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $objApplication = $applicationsRepository->findBy( array('id' => $result['aid']) );
                    if (sizeof($objApplication) > 0) {$objApplication = $objApplication[0];}

                    $tmpObj = new \stdClass();
                    $tmpObj->application = $objApplication;
                    $tmpObj->urgency = $applicationsRepository->getUrgency($result['aid']);
                    $tmpObj->materials = [];
                    $notOrderedYet[] = $tmpObj;
                    unset($tmpObj);
                }
            }

            foreach ($notOrderedYet as $row) {
                foreach ($results as $result) {
                    if ($result['aid'] == $row->application->getId()) {
                        //Добавляем материал в объект
                        $objMaterial = $materialsRepository->findBy( array('id' => $result['mid']) );
                        if (sizeof($objMaterial) > 0) {$objMaterial = $objMaterial[0];}

                        $tmpObj = new \stdClass();
                        $tmpObj->material = $objMaterial;
                        $tmpObj->count = $result['amount'];
                        $tmpObj->done = $result['bills'];
                        if ($tmpObj->done == null) {$tmpObj->done = 0;}
                        $row->materials[] = $tmpObj;
                        unset($tmpObj);
                    }
                }
            }
        }

        return $this->render('applications/index.html.twig', [
            'title' => 'Добро пожаловать',
            'supervisor' => $supervisor,
            'inwork' => $materialsInWork,
            'notordered' => $notOrderedYet,
            'expired' => $expiredBills,
            // 'active' => $activeApplications,
            'printcount' => $this->getPrintBillsCount()
        ]);
    }

    /**
     * Печать запроса к поставщикам
     * @Route("/applications/request", methods={"POST"})
     * @IsGranted("ROLE_EXECUTOR")
     */
    public function getRequest(Request $request, MaterialsRepository $materialsRepository): Response
    {
        $materials = $request->request->get('material');
        $amounts = $request->request->get('amount');
        $applications = $request->request->get('application');

        if ($materials === null || $amounts === null) {return new RedirectResponse('/');}
        if (!is_array($materials) || sizeof($materials) == 0 || !is_array($amounts) || sizeof($amounts) == 0 || sizeof($materials) != sizeof($amounts)) {return new RedirectResponse('/');}

        $materialsList = [];

        $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

        for ($i=0; $i<sizeof($materials); $i++) {
            $objMaterial = $materialsRepository->findBy( array('id' => $materials[$i]) );
            if (sizeof($objMaterial) > 0) {$objMaterial = $objMaterial[0];}

            $tmp = new \stdClass;
            $tmp->material = $objMaterial;
            $tmp->amount = $amounts[$i];
            $tmp->application = $applications[$i];
            $materialsList[] = $tmp;
            unset($tmp);

            $objMaterial->setRequested(true);
            $this->entityManager->persist($objMaterial);
            $this->entityManager->flush();
        }

        $this->entityManager->getConnection()->commit();

        return $this->render('applications/request.html.twig', [
            'materials' => $materialsList
        ]);
    }

    /**
     * @Route("/applications/set-results-count", methods={"POST"})
     * @IsGranted("ROLE_USER")
     */
    public function setResultsCount(Request $request): Response
    {
        if ($request->request->get('results') === null) {return new Response();}

        //Получаем фильтр
        if (isset($_SESSION['applicationsFilter'])) {
            $filter = unserialize($_SESSION['applicationsFilter']);
        } else {
            $filter = new Filter;
        }

        $filter->resultsPerPage = (int)$request->request->get('results');
        $_SESSION['applicationsFilter'] = serialize($filter);

        return new Response();
    }

    /**
     * Применение фильтра для активных заявок
     * @Route("/applications/apply-filter", methods={"POST"})
     * @IsGranted("ROLE_USER")
     */
    public function applicationsApplyFilter(Request $request, UsersRepository $usersRepository, StatusesOfApplicationsRepository $statusesOfApplicationsRepository, ApplicationsRepository $applicationsRepository, BillsStatusesRepository $billsStatusesRepository, OfficesRepository $officesRepository): Response
    {
        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        //Получаем фильтр
        $filter = new Filter;

        //Получаем параметры
        $results = 25; 
        if (isset($_SESSION['applicationsFilter'])) {
            $filterCurrent = unserialize($_SESSION['applicationsFilter']);
            if (is_numeric($filterCurrent->resultsPerPage) && $filterCurrent->resultsPerPage > 0) {$results = $filterCurrent->resultsPerPage;}
        }
        $filter->resultsPerPage = $results;
        
        //Фильтр по автору
        //Если у пользователя права ответственного, он может видеть только свои заявки
        if (in_array('ROLE_CREATOR', $roles) && !in_array('ROLE_SUPERVISOR', $roles)) {
            $filter->author = $usersRepository->findBy(array('id' => $this->security->getUser()->getId()));
            if (is_array($filter->author)) {$filter->author = array_shift($filter->author);}
            // $filter->isFiltered = true;
        } else {
            if ($request->request->get('filterAuthor') !== null && $request->request->get('filterAuthor') != -1) {
                //Получаем отправителя из запроса
                $filter->author = $usersRepository->findBy(array('active' => true, 'id' => (int)$request->request->get('filterAuthor')));
                if (is_array($filter->author)) {$filter->author = array_shift($filter->author);}
                $filter->isFiltered = true;
            }
        }

        //Фильтр по ответственному
        //Если у пользователя права ответственного, он может видеть только свои заявки
        if (in_array('ROLE_EXECUTOR', $roles) && !in_array('ROLE_SUPERVISOR', $roles)) {
            $filter->responsible = $usersRepository->findBy(array('id' => $this->security->getUser()->getId()));
            if (is_array($filter->responsible)) {$filter->responsible = array_shift($filter->responsible);}
            // $filter->isFiltered = true;
        } else {
            if ($request->request->get('filterResponsible') !== null && $request->request->get('filterResponsible') != -1) {
                //Получаем исполнителя из запроса
                $filter->responsible = $usersRepository->findBy(array('active' => true, 'id' => (int)$request->request->get('filterResponsible')));
                if (is_array($filter->responsible)) {$filter->responsible = array_shift($filter->responsible);}
                $filter->isFiltered = true;
            }
        }

        //Фильтр по подразделению
        //Если у пользователя права ответственного, он может видеть только свои заявки
        if (in_array('ROLE_EXECUTOR', $roles) && !in_array('ROLE_SUPERVISOR', $roles)) {
            $filter->office = $officesRepository->findBy(array('id' => $this->security->getUser()->getOffice()->getId()));
            if (is_array($filter->office)) {$filter->office = array_shift($filter->office);}
            // $filter->isFiltered = true;
        } else {
            if ($request->request->get('filterOffice') !== null && $request->request->get('filterOffice') != -1) {
                //Получаем исполнителя из запроса
                $filter->office = $officesRepository->findBy(array('id' => (int)$request->request->get('filterOffice')));
                if (is_array($filter->office)) {$filter->office = array_shift($filter->office);}
                $filter->isFiltered = true;
            }
        }

        if ($request->request->get('filterStatus') !== null && $request->request->get('filterStatus') != -1) {
            //Получаем статус из запроса
            $filter->status = $statusesOfApplicationsRepository->findBy(array('id' => (int)$request->request->get('filterStatus')));
            if (is_array($filter->status)) {$filter->status = array_shift($filter->status);}
            $filter->isFiltered = true;
        }

        if ($request->request->get('filterTitle') !== null && !empty($request->request->get('filterTitle'))) {
            //Получаем заголовок из запроса
            $filter->title = $request->request->get('filterTitle');
            $filter->isFiltered = true;
        }

        if ($request->request->get('filterDateFrom') !== null && !empty($request->request->get('filterDateFrom'))) {
            //Получаем дату начала диапазона из запроса
            $filter->dateFrom = new \DateTime($request->request->get('filterDateFrom').' 00:00:00');
            $filter->isFiltered = true;
        }

        if ($request->request->get('filterDateTo') !== null && !empty($request->request->get('filterDateTo'))) {
            //Получаем дату окончаниея диапазона из запроса
            $filter->dateTo = new \DateTime($request->request->get('filterDateTo').' 00:00:00');
            $filter->isFiltered = true;
        }

        //Фильтр по годовым заявкам
        if ($request->request->get('filterYear') !== null) {
            if ((int)$request->request->get('filterYear') == 1) {
                $filter->year = 1;
                $filter->isFiltered = true;
            } elseif ((int)$request->request->get('filterYear') == 0) {
                $filter->year = 0;
                $filter->isFiltered = true;
            } else {
                $filter->year = -1;
            }
        }

        //Сортировка
        if ($request->request->get('filterOrderBy') !== null && $request->request->get('filterSort') !== null) {
            $filter->sort = $request->request->get('filterSort');
            $filter->orderByIndex = $request->request->get('filterOrderBy');
            switch ($filter->orderByIndex) {
                case '0': $filter->orderBy = 'a.id'; break;
                case '1': $filter->orderBy = 'a.date_create'; break;
                case '2': $filter->orderBy = 'a.title'; break;
                case '3': $filter->orderBy = 'e.username'; break;
                case '4': $filter->orderBy = 'status_title'; break;
            }
        }

        //Страница
        if ($request->request->get('filterPage') !== null) {
            $filter->page = (int)$request->request->get('filterPage');
        }

        $_SESSION['applicationsFilter'] = serialize($filter);
        return new RedirectResponse('/applications');
    }

    /**
     * Снятие фильтра для активных заявок
     * @Route("/applications/delete-filter", methods={"GET"})
     * @IsGranted("ROLE_USER")
     */
    public function applicationsDeleteFilter(Request $request, UsersRepository $usersRepository): Response
    {
        $roles = $this->security->getUser()->getRoles();

        //Получаем фильтр
        $filter = new Filter;
        if (in_array('ROLE_EXECUTOR', $roles) && !in_array('ROLE_SUPERVISOR', $roles)) {
            $filter->responsible = $usersRepository->findBy(array('id' => $this->security->getUser()->getId()));
            if (is_array($filter->responsible)) {$filter->responsible = array_shift($filter->responsible);}
            $filter->resultsPerPage = 0;
        }

        if (in_array('ROLE_CREATOR', $roles) && !in_array('ROLE_SUPERVISOR', $roles)) {
            $filter->author = $usersRepository->findBy(array('id' => $this->security->getUser()->getId()));
            if (is_array($filter->author)) {$filter->author = array_shift($filter->author);}
            $filter->resultsPerPage = 0;
        }

        $_SESSION['applicationsFilter'] = serialize($filter);
        return new RedirectResponse('/applications');
    }

    /**
     * Список активных заявок
     * @Route("/applications", name="applications", methods={"GET", "POST"})
     * @IsGranted("ROLE_USER")
     */
    public function applications(Request $request, UsersRepository $usersRepository, StatusesOfApplicationsRepository $statusesOfApplicationsRepository, ApplicationsRepository $applicationsRepository, BillsStatusesRepository $billsStatusesRepository, OfficesRepository $officesRepository): Response
    {
        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        //Получаем фильтр
        if (isset($_SESSION['applicationsFilter'])) {
            $filter = unserialize($_SESSION['applicationsFilter']);
        } else {
            $filter = new Filter;
        }

        if (in_array('ROLE_EXECUTOR', $roles) && !in_array('ROLE_SUPERVISOR', $roles)) {
            $filter->responsible = $usersRepository->findBy(array('id' => $this->security->getUser()->getId()));
            if (is_array($filter->responsible)) {$filter->responsible = array_shift($filter->responsible);}
            $filter->resultsPerPage = 0;
        }

        if (in_array('ROLE_CREATOR', $roles) && !in_array('ROLE_SUPERVISOR', $roles)) {
            $filter->author = $usersRepository->findBy(array('id' => $this->security->getUser()->getId()));
            if (is_array($filter->author)) {$filter->author = array_shift($filter->author);}
            $filter->resultsPerPage = 0;
        }

        //Фильтр готов, выводим форму
        $applications = $applicationsRepository->getList($filter);

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications';
        $breadcrumbs[0]->title = 'Активные заявки';

        //Получаем отправителей и ответственных
        $users = $usersRepository->findBy(
            array('active' => true),
            array('username' => 'ASC', 'id' => 'ASC')
        );

        $usersSenders = [];
        $usersResponsibles = [];
        foreach ($users as $user) {
            if (in_array('ROLE_CREATOR', $user->getRoles())) {
                $usersSenders[] = $user;
            }

            if (in_array('ROLE_EXECUTOR', $user->getRoles())) {
                $usersResponsibles[] = $user;
            }
        }

        unset($users);

        $params = [
            'title' => 'Активные заявки',
            'breadcrumbs' => $breadcrumbs,
            'applications' => $applications,
            'usersResponsibles' => $usersResponsibles,
            'usersSenders' => $usersSenders,
            'offices' => $officesRepository->findAll(),
            'statuses' => $statusesOfApplicationsRepository->getStatusesForActiveFilter(),
            'filter' => $filter,
            'printcount' => $this->getPrintBillsCount()
        ];

        //Проверяем есть ли уведомление
        if ($request->request->get('msg') !== null && $request->request->get('bg-color') !== null && $request->request->get('text-color') !== null) {
            ob_start();

            echo '<script type="text/javascript">'."\n";
            echo '    $(document).ready(function() {'."\n";
            echo '        addToast(\''.$request->request->get('msg').'\', \''.$request->request->get('bg-color').'\', \''.$request->request->get('text-color').'\');'."\n";
            echo '        showToasts();'."\n";
            echo '    });'."\n";
            echo '</script>'."\n";

            $scripts = ob_get_contents();
            ob_end_clean();

            $params['scripts'] = $scripts;
        }

        return $this->render('applications/list.html.twig', $params);
    }

    /**
     * Форма добавления заявки
     * @Route("/applications/add", methods={"GET"}))
     * @IsGranted("ROLE_CREATOR")
     */
    public function showAddApplicationForm(UnitsRepository $unitsRepository, UsersRepository $usersRepository): Response
    {
        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications';
        $breadcrumbs[0]->title = 'Активные заявки';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/applications/add';
        $breadcrumbs[1]->title = 'Создание заявки';

        $params = [];
        $params['title'] = 'Создание заявки';
        $params['breadcrumbs'] = $breadcrumbs;
        $params['units'] = $unitsRepository->findAll();

        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            $params['users'] = $usersRepository->findBy(
                array('active' => TRUE),
                array('username' => 'ASC')
            );
        }

        return $this->render('applications/add.html.twig', $params);
    }

    /**
     * Форма добавления заявки (Загрузка из шаблона)
     * @Route("/applications/add-from-template", methods={"POST"}))
     * @IsGranted("ROLE_CREATOR")
     */
    public function showAddApplicationFromTemplateForm(Request $request, ValidatorInterface $validator, UnitsRepository $unitsRepository, UsersRepository $usersRepository): Response
    {
        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('upload-template', $submittedToken)) {
            try {
                //Загружаем файл
                $file = $request->files->get('template');
                //Берем только первый элемент, потому что загрузка нескольких файлов идет последовательно
                if (is_array($file)) {$file = array_shift($file);}

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
        
                if ($error) {
                    return new RedirectResponse('/applications/add');
                }

                //Начинаем обработку файла
                $path = $file->getRealPath();
                $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
                $objReader->setReadDataOnly(true);
                $objPHPExcel = $objReader->load($path);
                $objWorksheet = $objPHPExcel->getActiveSheet();

                $highestRow = (int)$objWorksheet->getHighestRow();
                $highestColumn = \PHPExcel_Cell::columnIndexFromString($objWorksheet->getHighestColumn());

                if ($highestColumn < 6) {
                    return new RedirectResponse('/applications/add');
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
                            case 3: // Вид техники
                                $rowArray['equipment'] = $val;
                            break;
                            case 4: // Уточнение
                                $rowArray['comment'] = $val;
                            break;
                            case 5: //Срочность
                                if ($val !== null) {$rowArray['hurry'] = true;} else {$rowArray['hurry'] = false;}
                            break;
                        }
                    }
                    if (sizeof($rowArray) == $highestColumn + 3) {$data[] = $rowArray;}
                }

                //Хлебные крошки
                $breadcrumbs = [];
                $breadcrumbs[0] = new \stdClass();
                $breadcrumbs[0]->href = '/applications';
                $breadcrumbs[0]->title = 'Активные заявки';
                $breadcrumbs[1] = new \stdClass();
                $breadcrumbs[1]->href = '/applications/add';
                $breadcrumbs[1]->title = 'Создание заявки из шаблона';

                $params = [];
                $params['title'] = 'Создание заявки из шаблона';
                $params['breadcrumbs'] = $breadcrumbs;
                $params['units'] = $unitsRepository->findAll();
                $params['data'] = $data;
        
                //Получаем роли текущего пользователя
                $roles = $this->security->getUser()->getRoles();
        
                if (in_array('ROLE_ADMIN', $roles)) {
                    $params['users'] = $usersRepository->findBy(
                        array('active' => TRUE),
                        array('username' => 'ASC')
                    );
                }
        
                return $this->render('applications/add.html.twig', $params);
            } catch (Exception $e) {
                return new RedirectResponse('/applications/add');
            }         
        } else {
            return new RedirectResponse('/applications/add');
        }
    }

    /**
     * Добавление заявки (принимает данные из формы)
     * @Route("/applications/add", methods={"POST"}))
     * @IsGranted("ROLE_CREATOR")
     */
    public function addNewApplicationForm(
        Request $request, 
        FilesRepository $filesRepository,
        TypesOfEquipmentRepository $typesOfEquipmentRepository, 
        UnitsRepository $unitsRepository, 
        UsersRepository $usersRepository, 
        StatusesOfApplicationsRepository $statusesOfApplicationsRepository
    ): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('add-application', $submittedToken)) {
            //Готовим данные
            $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

            try {
                //Получаем массив наименований
                $arrTitles = $request->request->get('titleContentApp');
                $rowsCount = sizeof($arrTitles); //Определяем полезное количество строк
                while ($rowsCount > 0) {if (empty($arrTitles[$rowsCount - 1])) {$rowsCount--;} else {break;}}
                $arrTitles = array_slice($arrTitles, 0, $rowsCount);

                $arrCounts = array_slice($request->request->get('amountContentApp'), 0, $rowsCount); //Получаем массив количества
                $arrComments = array_slice($request->request->get('commentContentApp'), 0, $rowsCount); //Получаем массив коментариев
                
                //Получаем массив срочности
                $arrUrgency = array_pad([], $rowsCount, false);
                $tmp = $request->request->get('urgentContentApp');
                if ($tmp !== null ) {
                    foreach ($tmp as $value) {if (isset($arrUrgency[$value - 1])) {$arrUrgency[$value - 1] = true;}} 
                }
                unset($tmp);

                //Получаем массив единиц измерения
                $unitsRaw = array_slice($request->request->get('unitContentApp'), 0, $rowsCount);

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

                //Получаем массив видов техники
                $toesRaw = array_slice($request->request->get('typeOfEquipmentContentApp'), 0, $rowsCount); 

                //Обрабатываем массив видов техники
                $arrTOE = [];
                foreach ($toesRaw as $toe) {
                    if (!empty($toe)) {
                        //Смотрим, может такой вид техники уже есть в базе
                        $tmp = $typesOfEquipmentRepository->findBy(array('title' => $toe));
                        if (sizeof($tmp) > 0) {
                            //Используем ID вида техники из базы
                            $arrTOE[] = $tmp[0];
                        } else {
                            //Добавляем новый вид техники
                            $dbTypeOfEquipment = new TypesOfEquipment;
                            $dbTypeOfEquipment->setTitle($toe);
            
                            $this->entityManager->persist($dbTypeOfEquipment);
                            $this->entityManager->flush();
            
                            $arrTOE[] = $dbTypeOfEquipment;
                        }
                        unset($tmp);
                    } else {
                        $arrTOE[] = null;
                    }
                }

                //Начинаем запись, пишем данные в таблицу applications
                $application = new Applications;
                $application->setTitle($request->request->get('titleApp'));
                if ($request->request->get('commentApp')) {$application->setComment($request->request->get('commentApp'));}
                if ($request->request->get('additionalNumApp')) {$application->setNumber($request->request->get('additionalNumApp'));}

                //Определяем авторство
                if ($request->request->get('user') === null) { 
                    $author = $usersRepository->findBy( array('id' => $this->security->getUser()->getId()) )[0];
                } else {
                    $author = $usersRepository->findBy( array('id' => (int)$request->request->get('user')) )[0];
                }
                $application->setAuthor($author);

                $this->entityManager->persist($application);
                $this->entityManager->flush(); //ID заявки в $application->getId();

                //Добавляем статус к заявке
                $status = new ApplicationsStatuses;
                $status->setApplication($application);
                $status->setStatus( $statusesOfApplicationsRepository->findBy(array('id' => 1))[0] );
                $this->entityManager->persist($status);
                
                //Добавляем материалы к заявке
                for ($i = 0; $i < sizeof($arrTitles); $i++) {
                    $material = new Materials(
                        $arrTitles[$i],
                        $arrCounts[$i], 
                        $arrUnits[$i], //Units::class
                        $arrUrgency[$i], 
                        $arrTOE[$i], //TypesOfEquipment::class
                        $arrComments[$i],
                        '',
                        $application, //Applications::class
                        $i + 1
                    );
                    $this->entityManager->persist($material);
                }

                //Добавляем файлы
                $arrFiles = json_decode($request->request->get('files'));
                if ($arrFiles !== null ) {
                    foreach ($arrFiles as $file) {
                        $objFile = $filesRepository->findBy( array('id' => $file) )[0];
                        $objFile->setApplication($application);
                        $this->entityManager->persist($objFile);
                    }
                }

                //Добавляем статус
                if ($request->request->get('yearApplication')) {$application->setIsYear(true);} else {$application->setIsYear(false);}

                $this->entityManager->flush();
                $this->entityManager->getConnection()->commit();

                $result[] = 1;
                $result[] = $application->getId();
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
     * Автозаполнение поля "Вид техники"
     * @Route("/autocomplete/toe", methods={"GET"})
     * @IsGranted("ROLE_CREATOR")
     */
    public function toeAutocomplete(Request $request, TypesOfEquipmentRepository $toeRepository): JsonResponse
    {
        $toes = $toeRepository->findLike($request->query->get('query'));
        $suggestions = [];
        foreach ($toes as $toe) {
            $exists = false;
            for ($i=0; $i<sizeof($suggestions); $i++) {
                if ($suggestions[$i] == $toe->getTitle()) {$exists = true; break;}
            }
            if (!$exists) {$suggestions[] = $toe->getTitle();}
        }

        $result = new \stdClass;
        $result->suggestions = $suggestions;

        return new JsonResponse($result);
    }

    /**
     * Автозаполнение поля "Наименование"
     * @Route("/autocomplete/material", methods={"GET"})
     * @IsGranted("ROLE_CREATOR")
     */
    public function materialAutocomplete(Request $request, MaterialsRepository $materialsRepository): JsonResponse
    {
        $materials = $materialsRepository->findLike($request->query->get('query'));
        $suggestions = [];
        foreach ($materials as $material) {
            $exists = false;
            for ($i=0; $i<sizeof($suggestions); $i++) {
                if ($suggestions[$i] == $material->getTitle()) {$exists = true; break;}
            }
            if (!$exists) {$suggestions[] = $material->getTitle();}
        }

        $result = new \stdClass;
        $result->suggestions = $suggestions;

        return new JsonResponse($result);
    }

    /**
     * Автозаполнение поля "Поставщик"
     * @Route("/autocomplete/provider", methods={"GET"})
     * @IsGranted("ROLE_BUH")
     */
    public function providerAutocomplete(Request $request, ProvidersRepository $providersRepository): JsonResponse
    {
        $providers = $providersRepository->findLike($request->query->get('q'));
        $suggestions = [];
        foreach ($providers as $provider) {
            $exists = false;
            for ($i=0; $i<sizeof($suggestions); $i++) {
                if ($suggestions[$i]['Inn'] == $provider->getInn()) {$exists = true; break;}
            }
            if (!$exists) {
                $tmp = [];
                $tmp['Title'] = $provider->getTitle();
                $tmp['Inn'] = $provider->getInn();
                $suggestions[] = $tmp;
                unset($tmp);
            }
        }

        return new JsonResponse($suggestions);
    }

    /**
     * Получение информации по поставщику
     * @Route("/autocomplete/provider", methods={"POST"})
     * @IsGranted("ROLE_BUH")
     */
    public function providerInfo(Request $request, ProvidersRepository $providersRepository): Response
    {
        $provider = $providersRepository->findBy(array('inn' => $request->request->get('inn')));
        if (is_array($provider)) {$provider = array_shift($provider);}

        ob_start();
        echo '<table>'."\n";
        echo '  <tbody>'."\n";
        
        if ($provider != null and $provider->getTitle() != '') {
            echo '        <tr>'."\n";
            echo '            <td class="text-muted pe-2">Наименование:</td>'."\n";
            echo '            <td>'.$provider->getTitle().'</td>'."\n";
            echo '        </tr>'."\n";
        }

        echo '        <tr>'."\n";
        echo '            <td class="text-muted pe-2">ИНН:</td>'."\n";
        echo '            <td>'.$request->request->get('inn').'</td>'."\n";
        echo '        </tr>'."\n";

        if ($provider != null and $provider->getAddress() != '') {
            echo '        <tr>'."\n";
            echo '            <td class="text-muted pe-2">Почтовый адрес:</td>'."\n";
            echo '            <td>'.$provider->getAddress().'</td>'."\n";
            echo '        </tr>'."\n";
        }

        if ($provider != null and $provider->getPhone() != '') {
            echo '        <tr>'."\n";
            echo '            <td class="text-muted pe-2">Телефон:</td>'."\n";
            echo '            <td>'.$provider->getPhone().'</td>'."\n";
            echo '        </tr>'."\n";
        }
                
        if ($provider != null and $provider->getComment() != '') {
            echo '        <tr>'."\n";
            echo '            <td class="text-muted pe-2 align-top">Комментарий:</td>'."\n";
            echo '            <td>'.nl2br($provider->getComment()).'</td>'."\n";
            echo '        </tr>'."\n";
        }

        echo '  </tbody>'."\n";
        echo '</table>'."\n";
        $content = ob_get_contents();
        ob_end_clean();

        return new Response($content);
    }

    /**
     * Загрузка файлов при создании или редактировании заявки
     * @Route("/applications/upload-file", methods={"POST"})
     * @IsGranted("ROLE_CREATOR")
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
            $dirname = uniqid(); while (file_exists($this->getParameter('applications_directory').'/'.$dirname)) {$dirname = uniqid();} 
            mkdir($this->getParameter('applications_directory').'/'.$dirname, 0755);

            try {
                $file->move(
                    $this->getParameter('applications_directory').'/'.$dirname,
                    $file->getClientOriginalName()
                );

                //Добавляем информацию в базу
                $dbFile = new Files;
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
     * Удаление файлов при создании или редактировании заявки
     * @Route("/applications/delete-file", methods={"POST"})
     * @IsGranted("ROLE_CREATOR")
     */
    public function deleteFile(Request $request, FilesRepository $filesRepository): JsonResponse
    {
        try {
            $file = $filesRepository->findBy( array('id' => $request->request->get('key')) );
            if (sizeof($file) > 0) {$file = array_shift($file);}

            //Проверяем, может ли пользователь удалить этот файл
            if ($this->security->getUser()->getId() != $file->getUser()->getId()) {
                return new JsonResponse(false);    
            }

            $this->entityManager->remove($file);
            $this->entityManager->flush();

            if (file_exists($this->getParameter('applications_directory').'/'.$file->getPath())) {
                unlink($this->getParameter('applications_directory').'/'.$file->getPath());
                rmdir(dirname($this->getParameter('applications_directory').'/'.$file->getPath()));
            }

            return new JsonResponse(true);
        } catch (FileException $e) {
            return new JsonResponse(false);
        }
    }

    /**
     * Удаление/восстановление материала в заявке
     * @Route("/applications/material-status", methods={"POST"})
     * @IsGranted("ROLE_CREATOR")
     */
    public function setMaterialStatus(Request $request, MaterialsRepository $materialsRepository, BillsMaterialsRepository $billsMaterialsRepository): JsonResponse
    {
        try {
            $material = $materialsRepository->findBy( array('id' => $request->request->get('id')) );
            if (sizeof($material) > 0) {$material = array_shift($material);}

            $status = $request->request->get('status');
            if ($status == 'true') {$status = true;} else {$status = false;}

            //Проверяем наличие прав на удаление файла
            if (
                $this->security->getUser()->getId() != $material->getApplication()->getAuthor()->getId() &&
                !in_array('ROLE_SUPERVISOR', $this->getUser()->getRoles())
            ) {
                return new JsonResponse(false);
            }

            $material->setIsDeleted($status);
            $this->entityManager->persist($material);
            $this->entityManager->flush();

            //Изменяем статус заполненности заявки счетами
            $application = $material->getApplication();
            if (!$status) {
                //Если восстановили позицию, сразу ставим статус
                $application->setIsBillsLoaded(false);
                $this->entityManager->persist($application);
                $this->entityManager->flush();
            } else {
                //Если удалили позицию, смотрим, полностью ли закрыта заявка
                $materials_ = $materialsRepository->findBy(array('application' => (int)$application->getId(), 'isDeleted' => false));
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
                    $this->entityManager->persist($application);
                    $this->entityManager->flush();
                }
            }

            unset($application, $material);

            return new JsonResponse(true);
        } catch (FileException $e) {
            return new JsonResponse(false);
        }
    }

    /**
     * Форма редактирования заявки
     * @Route("/applications/edit", methods={"GET"}))
     * @IsGranted("ROLE_SUPERVISOR")
     */
    public function showEditApplicationForm(Request $request, ApplicationsRepository $applicationsRepository, MaterialsRepository $materialsRepository, UnitsRepository $unitsRepository, UsersRepository $usersRepository, FilesRepository $filesRepository, ApplicationsStatusesRepository $applicationsStatusesRepository): Response
    {
        $id = $request->query->get('number');
        if ($id === null || empty($id) || !is_numeric($id)) {
            return new RedirectResponse('/applications');
        }

        //Проверяем наличие заявки
        $objApplication = $applicationsRepository->findBy( array('id' => $id) );
        if (sizeof($objApplication) == 0) {
            return new RedirectResponse('/applications');
        }
        if (is_array($objApplication)) {$objApplication = array_shift($objApplication);}

        //Проверяем наличие прав редактирования
        if ($this->security->getUser()->getId() != $objApplication->getAuthor()->getId()) {
            if (!in_array('ROLE_SUPERVISOR', $this->getUser()->getRoles())) { 
                return new RedirectResponse('/applications');
            }
        } 

        //Проверяем статус заявки
        $status = $applicationsStatusesRepository->findBy( array('application' => $id), array('datetime' => 'DESC') );
        if (is_array($status)) {$status = array_shift($status);}
        if (in_array($status->getStatus()->getId(), [3, 5])) {
            return new RedirectResponse('/applications');
        }

        $arrMaterials = $materialsRepository->findBy( array('application' => $objApplication->getId()), array('num' => 'ASC') );

        //Получаем список файлов
        $files = [];
        $rawFiles = $filesRepository->findBy( array('application' => $objApplication), array('id' => 'ASC') );
        foreach ($rawFiles as $file) {
            $objFile = new \stdClass;
            $objFile->name = basename($file->getPath());
            $objFile->path = $file->getPath();
            $objFile->size = filesize($this->getParameter('applications_directory').'/'.$file->getPath());
            $objFile->type = $file->getFileType();
            $objFile->key = $file->getId();
            $files[] = $objFile;
            unset($objFile);
        }

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications';
        $breadcrumbs[0]->title = 'Активные заявки';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/applications/edit/?number='.$id;
        $breadcrumbs[1]->title = 'Редактирование заявки №'.$id;

        $params = [];
        $params['title'] = 'Редактирование заявки №'.$id;
        $params['breadcrumbs'] = $breadcrumbs;
        $params['units'] = $unitsRepository->findAll();
        $params['application'] = $objApplication;
        $params['materials'] = $arrMaterials;
        $params['files'] = $files;

        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            $params['users'] = $usersRepository->findBy(
                array(),
                array('active' => 'DESC', 'login' => 'ASC')
            );
        }

        return $this->render('applications/edit.html.twig', $params);
    }

    /**
     * Сохранение заявки (принимает данные из формы)
     * @Route("/applications/edit", methods={"POST"}))
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_CREATOR')")
     */
    public function saveApplicationForm(Request $request, ApplicationsRepository $applicationsRepository, MaterialsRepository $materialsRepository, TypesOfEquipmentRepository $typesOfEquipmentRepository, UnitsRepository $unitsRepository, UsersRepository $usersRepository, StatusesOfApplicationsRepository $statusesOfApplicationsRepository, FilesRepository $filesRepository): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('edit-application', $submittedToken)) {
            $application = $applicationsRepository->findBy( array('id' => $request->request->get('id')) );
            if (sizeof($application) > 0) {$application = array_shift($application);}

            //Проверяем наличие прав на изменение заявки
            if ($this->security->getUser()->getId() != $application->getAuthor()->getId()) {
                if (!in_array('ROLE_SUPERVISOR', $this->getUser()->getRoles())) { 
                    $result[] = 0;
                    $result[] = 'Нет прав для сохранения изменений';

                    return new JsonResponse($result);
                    die();
                }
            }

            //Готовим данные
            $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

            try {
                //Получаем массив ID
                $arrId = $request->request->get('idContentApp');

                //Получаем массив номеров
                $arrNum = $request->request->get('numContentApp');

                //Получаем массив наименований
                $arrTitles = $request->request->get('titleContentApp');
                $rowsCount = sizeof($arrTitles) - sizeof($arrId); //Определяем количество строк для добавления
                
                $arrTitlesToSave = array_slice($arrTitles, 0, sizeof($arrId)); //Массив для сохраннения изменений
                $arrTitles = array_slice($arrTitles, sizeof($arrId), $rowsCount); //Массив для добавления новых строк

                $arrCountsToSave = array_slice($request->request->get('amountContentApp'), 0, sizeof($arrId)); //Массив для сохраннения изменений
                $arrCounts = array_slice($request->request->get('amountContentApp'), sizeof($arrId), $rowsCount); //Получаем массив количества
                
                $arrCommentsToSave = array_slice($request->request->get('commentContentApp'), 0, sizeof($arrId)); //Массив для сохраннения изменений
                $arrComments = array_slice($request->request->get('commentContentApp'), sizeof($arrId), $rowsCount); //Получаем массив коментариев

                //Получаем массив срочности
                $arrUrgency = array_pad([], $rowsCount + sizeof($arrId), false);
                $tmp = $request->request->get('urgentContentApp'); 

                if ($tmp !== null) {
                    asort($tmp);
                    
                    //Преобразовываем типы
                    for ($i=0; $i<sizeof($tmp); $i++) {$tmp[$i] = (int)$tmp[$i];}

                    //Получаем массив активности
                    for ($i=0; $i<sizeof($arrNum); $i++) {
                        foreach ($tmp as $value) {
                            if ($value == $arrNum[$i]) {
                                $arrUrgency[$i] = true;
                            }
                        }
                    }
                }
                unset($tmp);

                $arrUrgencyToSave = array_slice($arrUrgency, 0, sizeof($arrId)); //Массив для сохраннения изменений
                $arrUrgency = array_slice($arrUrgency, sizeof($arrId), $rowsCount); //Массив для добавления новых строк

                //Получаем массив единиц измерения
                $unitsRaw = $request->request->get('unitContentApp');

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

                $arrUnitsToSave = array_slice($arrUnits, 0, sizeof($arrId)); //Массив для сохраннения изменений
                $arrUnits = array_slice($arrUnits, sizeof($arrId), $rowsCount); //Массив для добавления новых строк

                //Получаем массив видов техники
                $toesRaw = $request->request->get('typeOfEquipmentContentApp');

                //Обрабатываем массив видов техники
                $arrTOE = [];
                foreach ($toesRaw as $toe) {
                    if (!empty($toe)) {
                        //Смотрим, может такой вид техники уже есть в базе
                        $tmp = $typesOfEquipmentRepository->findBy(array('title' => $toe));
                        if (sizeof($tmp) > 0) {
                            //Используем ID вида техники из базы
                            $arrTOE[] = $tmp[0];
                        } else {
                            //Добавляем новый вид техники
                            $dbTypeOfEquipment = new TypesOfEquipment;
                            $dbTypeOfEquipment->setTitle($toe);
            
                            $this->entityManager->persist($dbTypeOfEquipment);
                            $this->entityManager->flush();
            
                            $arrTOE[] = $dbTypeOfEquipment;
                        }
                        unset($tmp);
                    } else {
                        $arrTOE[] = null;
                    }
                }

                $arrTOEToSave = array_slice($arrTOE, 0, sizeof($arrId)); //Массив для сохраннения изменений
                $arrTOE = array_slice($arrTOE, sizeof($arrId), $rowsCount); //Массив для добавления новых строк

                //Определяем авторство
                if ($request->request->get('user') !== null) { 
                    $author = $usersRepository->findBy( array('id' => (int)$request->request->get('user')) )[0];
                    $application->setAuthor($author);
                }

                //Добавляем статус
                if ($request->request->get('yearApplication')) {$application->setIsYear(true);} else {$application->setIsYear(false);}

                //Сохраняем материалы в заявке               
                for ($i = 0; $i < sizeof($arrId); $i++) {
                    $material = $materialsRepository->findBy( array('id' => $arrId[$i]) );
                    if (sizeof($material) > 0) {$material = $material[0];}
                    
                    $material->setTitle($arrTitlesToSave[$i]);
                    
                    $material->setAmount($arrCountsToSave[$i]);
                    if ($arrCountsToSave[$i] <= 0) {
                        //Если количество равно 0, отмечаем позицию как удаленную
                        $material->setIsDeleted(true);
                    }

                    $material->setUnit($arrUnitsToSave[$i]);
                    $material->setUrgency($arrUrgencyToSave[$i]);
                    $material->setTypeOfEquipment($arrTOEToSave[$i]);
                    if (empty($arrCommentsToSave[$i])) {$material->setComment(null);} else {$material->setComment($arrCommentsToSave[$i]);}
                    
                    $this->entityManager->persist($material);
                    $this->entityManager->flush();
                    unset($material);
                }

                $materialsAdded = false;

                //Добавляем материалы к заявке
                for ($i = 0; $i < sizeof($arrTitles); $i++) {
                    if (!empty($arrTitles[$i])) {
                        $material = new Materials(
                            $arrTitles[$i],
                            $arrCounts[$i], 
                            $arrUnits[$i], //Units::class
                            $arrUrgency[$i], 
                            $arrTOE[$i], //TypesOfEquipment::class
                            $arrComments[$i],
                            null,
                            $application, //Applications::class
                            $i + 1 + sizeof($arrId) + (int)$request->request->get('deletedMaterialsContentApp')
                        );

                        $material->setApplication($application);
                        $this->entityManager->persist($material);
                        $this->entityManager->flush();
                        unset($material);

                        $materialsAdded = true;
                    }
                }

                //Начинаем запись, пишем данные в таблицу applications
                $application->setTitle($request->request->get('titleApp'));
                $application->setComment($request->request->get('commentApp'));
                $application->setNumber($request->request->get('additionalNumApp'));

                if ($materialsAdded) {
                    $application->setIsBillsLoaded(false);
                }

                $this->entityManager->persist($application);
                $this->entityManager->flush();

                //Добавляем файлы
                $arrFiles = json_decode($request->request->get('files'));
                if ($arrFiles !== null ) {
                    foreach ($arrFiles as $file) {
                        $objFile = $filesRepository->findBy( array('id' => $file) )[0];
                        $objFile->setApplication($application);
                        $this->entityManager->persist($objFile);
                    }
                }

                $this->entityManager->flush();
                $this->entityManager->getConnection()->commit();

                $result[] = 1;
                $result[] = $request->request->get('id');
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
     * Просмотр заявки
     * @Route("/applications/view", methods={"GET"})
     * @IsGranted("ROLE_USER")
     */
    public function viewApplication(
        Request $request, 
        ApplicationsRepository $applicationsRepository, 
        ApplicationsStatusesRepository $applicationsStatusesRepository, 
        BillsRepository $billsRepository, 
        BillsMaterialsRepository $billsMaterialsRepository, 
        BillsStatusesRepository $billsStatusesRepository,
        FilesRepository $filesRepository, 
        LogisticsMaterialsRepository $logisticsMaterialsRepository,
        MaterialsCommentsRepository $materialsCommentsRepository,
        MaterialsRepository $materialsRepository, 
        ResponsibleLogRepository $responsibleLogRepository,
        StatusesOfBillsRepository $statusesOfBillsRepository,
        StatusesOfApplicationsRepository $statusesOfApplicationsRepository,
        UsersRepository $usersRepository,
        UnitsRepository $unitsRepository
        ): Response
    {
        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        $id = $request->query->get('number');
        if ($id === null || empty($id) || !is_numeric($id)) {
            return new RedirectResponse('/applications');
        }

        //Проверяем наличие заявки
        $objApplication = $applicationsRepository->findBy( array('id' => $id) );
        if (sizeof($objApplication) == 0) {
            return new RedirectResponse('/applications');
        }
        if (is_array($objApplication)) {$objApplication = array_shift($objApplication);}

        //Проверяем наличие прав просмотра
        $canSee = false;
        
        if (in_array('ROLE_SUPERVISOR', $roles) || in_array('ROLE_WATCHER', $roles)) {$canSee = true;}
        if (in_array('ROLE_CREATOR', $roles) && $this->security->getUser()->getId() == $objApplication->getAuthor()->getId()) {$canSee = true;}

        $arrMaterials = $materialsRepository->findBy(array('application' => $id)); //Также пригодится потом

        if (in_array('ROLE_EXECUTOR', $roles)) {
            foreach ($arrMaterials as $material) {
                if ($material->getResponsible() !== null) {
                    if ($material->getResponsible()->getId() == $this->security->getUser()->getId()) {
                        $canSee = true;
                        break;
                    }
                }
            }
        }

        if (!$canSee) { 
            return new RedirectResponse('/applications');
        }

        //Заголовок
        $title = $objApplication->getTitle().' #'.$id;

        //Статусы
        $statuses = $applicationsStatusesRepository->findBy( array('application' => $id), array('datetime' => 'DESC') );

        //Получаем список файлов
        $files = [];
        $arrFiles = $filesRepository->findBy(array('application' => $id));
        foreach ($arrFiles as $file) {                
            $tmp = explode('.', basename($file->getPath())); $ext = end($tmp); unset($tmp);

            $params = [
                'path' => $file->getPath(),
                'name' => basename($file->getPath()),
                'title' => pathinfo($file->getPath())['filename'],
                'ext' => $ext,
                'type' => $file->getFileType()
            ];

            //Определяем класс кнопки и иконку
            $params['class'] = 'btn-outline-secondary'; $params['icon'] = 'bi-file-image';
            if (in_array($ext, ['doc', 'docx'])) {$params['class'] = 'btn-outline-primary'; $params['bi-file-richtext'] = '';}
            if (in_array($ext, ['xls', 'xlsx'])) {$params['class'] = 'btn-outline-success'; $params['icon'] = 'bi-file-bar-graph';}
            if (in_array($ext, ['pdf'])) {$params['class'] = 'btn-outline-danger'; $params['icon'] = 'bi-file-pdf';}
            if (in_array($ext, ['txt'])) {$params['class'] = 'btn-outline-secondary'; $params['icon'] = 'bi-file-text';}
            if (in_array($ext, ['htm', 'html'])) {$params['class'] = 'btn-outline-secondary'; $params['icon'] = 'bi-file-code';}

            $files[] = $params; unset($params);
        }

        //Подгружаем список счетов
        $arrMaterialsWithBills = []; //ID, дата поставки и количество материалов у которых есть счета
        $bills = [];
        $arrBills = [];
        foreach ($arrMaterials as $material) {
            $arrBillsMaterials = $billsMaterialsRepository->findBy(array('material' => $material->getId()));
            foreach ($arrBillsMaterials as $billMaterial) {
                $arrBills[] = $billMaterial->getBill()->getId();

                //Заполняем массив материалов
                $index = -1;
                for ($i=0; $i<sizeof($arrMaterialsWithBills); $i++) {
                    if ($arrMaterialsWithBills[$i][0] == $billMaterial->getMaterial()->getId()) {
                        $index = $i; break;
                    }
                }

                if ($index == -1) {
                    $arrMaterialsWithBills[] = [$billMaterial->getMaterial()->getId(), (float)$billMaterial->getAmount(), $billMaterial->getBill()->getDateClose()];
                } else {
                    $arrMaterialsWithBills[$index][1] += (float)$billMaterial->getAmount();
                    if ($arrMaterialsWithBills[$index][2] < $billMaterial->getBill()->getDateClose()) {$arrMaterialsWithBills[$index][2] = $billMaterial->getBill()->getDateClose();}
                }
            }
        }

        $arrBills = array_unique($arrBills);
        foreach ($arrBills as $billId) {
            $bill = $billsRepository->findBy(array('id' => $billId))[0];

            $tmp = explode('.', basename($bill->getPath())); $ext = end($tmp); unset($tmp);

            $params = [
                'path' => $bill->getPath(),
                'name' => basename($bill->getPath()),
                'title' => pathinfo($bill->getPath())['filename'],
                'ext' => $ext,
                'type' => $bill->getFileType(),
                'sum' => $bill->getSum(),
                'currency' => $bill->getCurrency(),
                'id' => $billId
            ];

            //Определяем класс кнопки и иконку
            //Получаем статус счета
            $status = $billsStatusesRepository->findBy(array('bill' => $billId), array('datetime' => 'DESC'))[0];

            $params['status'] = $status->getStatus(); 

            $bills[] = $params; unset($params);
        }

        unset($arrBills, $arrMaterials, $arrBillsMaterials);
        
        //Получаем всевозможные статусы счетов для списка
        $arrStatuses = $statusesOfBillsRepository->findBy(array(), array('id' => 'ASC'));

        //Получаем всевозможные статусы заявок для списка
        $arrStatusesOfApplications = $statusesOfApplicationsRepository->findBy(array(), array('id' => 'ASC'));

        //Список материалов
        $messages = []; //Сообщения к материалам
        $arrMaterials = $materialsRepository->findBy( array('application' => $objApplication->getId()), array('num' => 'ASC') );
        for ($i=0; $i<sizeof($arrMaterials); $i++) {
            //Дополняем массив материалов информацией о том, выставлены ли по ним счета
            $amount = 0;
            $dateClose = '<span class="text-muted">Не известна</span>';
            for ($j=0; $j<sizeof($arrMaterialsWithBills); $j++) {
                if ($arrMaterialsWithBills[$j][0] == $arrMaterials[$i]->getId()) {
                    $amount = $arrMaterialsWithBills[$j][1];
                    $dateClose = $arrMaterialsWithBills[$j][2]->format('d.m.Y');
                    break;
                }
            }

            $arrMaterials[$i]->done = $amount;
            $arrMaterials[$i]->dateClose = $dateClose;
            $arrMaterials[$i]->duplicates = [];
            $arrMaterials[$i]->prices = [];

            //Получаем лог изменения статусов ответственных
            $arrMaterials[$i]->log = $responsibleLogRepository->findBy( array('material' => $arrMaterials[$i]->getId()) );

            //Смотрим, есть ли логистическая информация
            $logistics = $logisticsMaterialsRepository->findBy( array('material' => $arrMaterials[$i]->getId()) );
            if ($logistics !== null && sizeof($logistics) > 0) {$arrMaterials[$i]->logistics = true;} else {$arrMaterials[$i]->logistics = false;}
            unset($logistics);

            //Смотрим возможность дубликата
            //Получаем интервал дат, на которых смотрятся дубликаты
            $from = new \DateTime($objApplication->getDateCreate()->format('Y-m-d').' 00:00:00');
            $from->modify('-1 month');
            $to = new \DateTime($objApplication->getDateCreate()->format('Y-m-d').' 23:59:59');

            //Получаем массив похожих материалов
            $arrSameMaterials = $materialsRepository->createQueryBuilder('m')
            ->select('a.id, a.date_create')
            ->where('LOWER(m.title) LIKE LOWER(:mtitle)')
            ->andWhere('m.id <> :mid')
            ->andWhere('a.id <> :aid')
            ->setParameter('mtitle', '%'.$arrMaterials[$i]->getTitle().'%')
            ->setParameter('mid', $arrMaterials[$i]->getId())
            ->setParameter('aid', $id)
            ->join('App\Entity\Applications', 'a', 'WITH' ,'m.application=a.id')
            ->andWhere('a.date_create BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('a.id, a.date_create')
            ->getQuery()
            ->getResult()
            ;

            if (sizeof($arrSameMaterials) > 0) {
                $arrMaterials[$i]->duplicates = $arrSameMaterials;
            }

            unset($arrSameMaterials);

            //Получаем счета в которых ранее встречалась данная позиция
            $arrPrices = $materialsRepository->createQueryBuilder('m')
            ->select('IDENTITY(bm.bill) AS id, b.num')
            ->where('LOWER(m.title) LIKE LOWER(:mtitle)')
            ->andWhere('m.id <> :mid')
            ->setParameter('mtitle', '%'.$arrMaterials[$i]->getTitle().'%')
            ->setParameter('mid', $arrMaterials[$i]->getId())
            ->join('App\Entity\BillsMaterials', 'bm', 'WITH' ,'bm.material = m.id')
            ->join('App\Entity\Bills', 'b', 'WITH' ,'bm.bill = b.id')
            ->groupBy('bm.bill, b.num')
            ->getQuery()
            ->getResult()
            ;

            if (is_array($arrPrices) && sizeof($arrPrices) > 0) {
                foreach ($arrPrices as $bill) {
                    //Получаем дату загрузки счета
                    $status = $this->entityManager->getRepository(BillsStatuses::class)->findBy(array('bill' => $bill['id']), array('datetime' => 'DESC'));
                    if (is_array($status)) {$status = array_shift($status);}

                    $arrMaterials[$i]->prices[] = ['id' => $bill['id'], 'num' => $bill['num'], 'datetime' => $status->getDateTime()];
                }
            }

            //Подгружаем счета
            $billsTmp = $billsMaterialsRepository->findBy(array('material' => $arrMaterials[$i]->getId()));
            $arrMaterials[$i]->bills = [];
            foreach($billsTmp as $bill) {
                $arrMaterials[$i]->bills[] = $bill->getBill();
            }

            //Сообщения к материалам
            $materialMessages = $materialsCommentsRepository->findBy(array('material' => $arrMaterials[$i]->getId()));
            $messages = array_merge($messages, $materialMessages);
        }

        //Список пользователей
        $users = $usersRepository->findByRole('ROLE_EXECUTOR');

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications';
        $breadcrumbs[0]->title = 'Активные заявки';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/applications/view?number='.$id;
        $breadcrumbs[1]->title = $title;

        return $this->render('applications/view.html.twig', [
            'title' => $title,
            'breadcrumbs' => $breadcrumbs,
            'application' => $objApplication,
            'urgency' => $applicationsRepository->getUrgency($id),
            'statuses' => $statuses,
            'responsibles' => $applicationsRepository->getResponsibles($id),
            'files' => $files,
            'bills' => $bills,
            'billsstatuses' => $arrStatuses,
            'applicationsstatuses' => $arrStatusesOfApplications,
            'materials' => $arrMaterials,
            'users' => $users,
            'units' => $unitsRepository->findAll(),
            'messages' => $messages
        ]);
    }

    /**
     * Просмотр заявки
     * @Route("/applications/set-status", methods={"POST"})
     * @IsGranted("ROLE_SUPERVISOR")
     */
    public function setApplicationStatus(Request $request, ApplicationsRepository $applicationsRepository, StatusesOfApplicationsRepository $statusesOfApplicationsRepository): Response
    {
        $id = $request->request->get('id');
        $status = $request->request->get('status');
        $comment = $request->request->get('comment');

        if ($id === null || $status === null) { // || !in_array((int)$status, [2, 4, 5])) {
            return new RedirectResponse('/applications');
        }

        $id = (int)$id; $status = (int)$status;

        //Получаем заявку
        $objApplication = $applicationsRepository->findBy( array('id' => $id) );
        if (sizeof($objApplication) == 0) {
            return new RedirectResponse('/applications');
        }
        if (is_array($objApplication)) {$objApplication = array_shift($objApplication);}

        //Если отменяем заявку - обновляем дату закрытия
        if ((int)$status == 5) {
            $dateClose = new \DateTime();
            $objApplication->setDateClose($dateClose);
            $this->entityManager->persist($objApplication);
            $this->entityManager->flush();
        }

        //Получаем статус
        $objStatus = $statusesOfApplicationsRepository->findBy( array('id' => $status) );
        if (is_array($objStatus)) {$objStatus = array_shift($objStatus);}

        //Добавляем статус в базу
        $applicationStatus = new ApplicationsStatuses;
        $applicationStatus->setApplication($objApplication);
        $applicationStatus->setStatus($objStatus);
        if (!empty($comment)) {$applicationStatus->setComment($comment);}
        
        $this->entityManager->persist($applicationStatus);
        $this->entityManager->flush();
    
        return new RedirectResponse('/applications/view?number='.$id);
    }

    /**
     * Добавление комментария к материалу
     * @Route("/applications/add-material-comment", methods={"POST"})
     * @IsGranted("ROLE_EXECUTOR")
     */
    public function setMaterialComment(Request $request, MaterialsRepository $materialsRepository): JsonResponse
    {
        $result = [];

        try {
            $id = $request->request->get('material');
            $note = $request->request->get('note');
            if ($note !== null) {$note = nl2br($note);}

            if ($id === null || !is_numeric($id)) {
                return new RedirectResponse('/applications');
            }

            $id = (int)$id;

            //Получаем материал
            $objMaterial = $materialsRepository->findBy( array('id' => $id) );
            if (sizeof($objMaterial) == 0) {
                return new RedirectResponse('/applications');
            }
            if (is_array($objMaterial)) {$objMaterial = array_shift($objMaterial);}

            //Добавляем комментарий
            $objMaterial->setNote($note);
            
            $this->entityManager->persist($objMaterial);
            $this->entityManager->flush();

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
     * Получение материала за наличные
     * @Route("/applications/set-material-cash", methods={"POST"})
     * @IsGranted("ROLE_EXECUTOR")
     */
    public function setMaterialCash(
        Request $request, 
        MaterialsRepository $materialsRepository, 
        BillsMaterialsRepository $billsMaterialsRepository,
        StatusesOfApplicationsRepository $statusesOfApplicationsRepository
    ): JsonResponse
    {
        $result = [];

        $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

        try {
            $id = $request->request->get('material');

            if ($id === null || !is_numeric($id)) {
                return new RedirectResponse('/applications');
            }

            $id = (int)$id;

            //Получаем материал
            $objMaterial = $materialsRepository->findBy( array('id' => $id) );
            if (sizeof($objMaterial) == 0) {
                return new RedirectResponse('/applications');
            }
            if (is_array($objMaterial)) {$objMaterial = array_shift($objMaterial);}

            //Добавляем комментарий
            $objMaterial->setCash(true);
            
            $this->entityManager->persist($objMaterial);
            $this->entityManager->flush();

            //Возможно стоит обновить дату закрытия заявки и/или скрыть заявку из формы загрузки счета
            $objApplication = $objMaterial->getApplication();
            $isChanged = false;

            //Проверяем, стоит ли обновить дату закрытия заявки
            $dateClose = new \DateTime();
            $appDateClose = new \DateTime();
            $appDateClose->setTimestamp(strtotime('1970-01-01 00:00:01'));
            if ($objApplication->getDateClose() !== null) {$appDateClose = $objApplication->getDateClose();}

            if ($dateClose > $appDateClose) {
                $objApplication->setDateClose($dateClose);
                $isChanged = true;
            }

            //Проверяем, все ли позиции в заявке закрыты
            $materials_ = $materialsRepository->findBy(array('application' => (int)$objApplication->getId(), 'isDeleted' => false, 'impossible' => false, 'cash' => false));

            $flag = false;
            foreach ($materials_ as $material_) {
                //Получаем количество материала в заявке
                $applicationAmount = (int)$material_->getAmount();

                //Получаем количество материала в загруженных счетах
                $billsAmount = $billsMaterialsRepository->createQueryBuilder('bm')
                ->select('SUM(bm.recieved) as recieved')
                ->where('bm.material = :material')
                ->setParameter('material', $material_->getId())
                ->getQuery()->getResult();
                $billsAmount = (int)$billsAmount[0]['recieved'];

                if ($applicationAmount != $billsAmount) {
                    $flag = true; break;
                }
            }

            if (!$flag) {
                $objApplication->setIsBillsLoaded(true);
                $isChanged = true; 

                //Добавляем статус что заявка закрыта
                $objStatus = $statusesOfApplicationsRepository->findBy( array('id' => 3) );
                if (is_array($objStatus)) {$objStatus = array_shift($objStatus);}

                //Добавляем статус в базу
                $applicationStatus = new ApplicationsStatuses;
                $applicationStatus->setApplication($objApplication);
                $applicationStatus->setStatus($objStatus);

                $this->entityManager->persist($applicationStatus);
                $this->entityManager->flush();
            }

            if ($isChanged) {
                //Записываем
                $this->entityManager->persist($objApplication);
                $this->entityManager->flush();
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
    }

    /**
     * Отметка о том что материал невозможно поставить
     * @Route("/applications/set-material-impossible", methods={"POST"})
     * @IsGranted("ROLE_EXECUTOR")
     */
    public function setMaterialImpossible(
        Request $request, 
        MaterialsRepository $materialsRepository,
        BillsMaterialsRepository $billsMaterialsRepository,
        StatusesOfApplicationsRepository $statusesOfApplicationsRepository
    ): JsonResponse
    {
        $result = [];

        $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

        try {
            $id = $request->request->get('material');

            if ($id === null || !is_numeric($id)) {
                return new RedirectResponse('/applications');
            }

            $id = (int)$id;

            //Получаем материал
            $objMaterial = $materialsRepository->findBy( array('id' => $id) );
            if (sizeof($objMaterial) == 0) {
                return new RedirectResponse('/applications');
            }
            if (is_array($objMaterial)) {$objMaterial = array_shift($objMaterial);}

            //Добавляем комментарий
            $objMaterial->setImpossible(true);
            
            $this->entityManager->persist($objMaterial);
            $this->entityManager->flush();

            //Возможно стоит обновить дату закрытия заявки и/или скрыть заявку из формы загрузки счета
            $objApplication = $objMaterial->getApplication();
            $isChanged = false;

            //Проверяем, стоит ли обновить дату закрытия заявки
            $dateClose = new \DateTime();
            $appDateClose = new \DateTime();
            $appDateClose->setTimestamp(strtotime('1970-01-01 00:00:01'));
            if ($objApplication->getDateClose() !== null) {$appDateClose = $objApplication->getDateClose();}

            if ($dateClose > $appDateClose) {
                $objApplication->setDateClose($dateClose);
                $isChanged = true;
            }

            //Проверяем, все ли позиции в заявке закрыты
            $materials_ = $materialsRepository->findBy(array('application' => (int)$objApplication->getId(), 'isDeleted' => false, 'impossible' => false, 'cash' => false));
         
            $flag = false;
            foreach ($materials_ as $material_) {
                //Получаем количество материала в заявке
                $applicationAmount = (int)$material_->getAmount();

                //Получаем количество материала в загруженных счетах
                $billsAmount = $billsMaterialsRepository->createQueryBuilder('bm')
                ->select('SUM(bm.recieved) as recieved')
                ->where('bm.material = :material')
                ->setParameter('material', $material_->getId())
                ->getQuery()->getResult();
                $billsAmount = (int)$billsAmount[0]['recieved'];

                if ($applicationAmount != $billsAmount) {
                    $flag = true; break;
                }
            }

            if (!$flag) {
                $objApplication->setIsBillsLoaded(true);
                $isChanged = true; 

                //Добавляем статус что заявка закрыта
                $objStatus = $statusesOfApplicationsRepository->findBy( array('id' => 3) );
                if (is_array($objStatus)) {$objStatus = array_shift($objStatus);}

                //Добавляем статус в базу
                $applicationStatus = new ApplicationsStatuses;
                $applicationStatus->setApplication($objApplication);
                $applicationStatus->setStatus($objStatus);

                $this->entityManager->persist($applicationStatus);
                $this->entityManager->flush();
            }

            if ($isChanged) {
                //Записываем
                $this->entityManager->persist($objApplication);
                $this->entityManager->flush();
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
    }

    /**
     * Сохранение заявки при просмотре (назначение ответственных и статусы счетов) (принимает данные из формы)
     * @Route("/applications/view", methods={"POST"}))
     * @IsGranted("ROLE_SUPERVISOR")
     */
    public function saveAfterViewForm(
        Request $request, 
        ApplicationsStatusesRepository $applicationsStatusesRepository, 
        ApplicationsRepository $applicationsRepository, 
        BillsRepository $billsRepository, 
        MaterialsRepository $materialsRepository, 
        UsersRepository $usersRepository, 
        ResponsibleLog $responsibleLog = null,
        StatusesOfApplicationsRepository $statusesOfApplicationsRepository,
        StatusesOfBillsRepository $statusesOfBillsRepository
    ): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('view-application', $submittedToken)) {
            //Получаем переменные
            $id = $request->request->get('aid');
            $arrBID = $request->request->get('bid');
            $arrBStatus = $request->request->get('bstatus');
            $arrMID = $request->request->get('mid');
            $arrMResponsible = $request->request->get('mresponsible');

            $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

            try {
                //Ставим статусы счетам
                if ($arrBID != null && $arrBStatus != null && sizeof($arrBID) > 0 && sizeof($arrBStatus) > 0 && sizeof($arrBID) == sizeof($arrBStatus)) {
                    for ($i=0; $i<sizeof($arrBID); $i++) {
                        //Получаем счет
                        $bill = $billsRepository->findBy( array('id' => $arrBID[$i]) );
                        if (sizeof($bill) > 0) {
                            $bill = $bill[0];
                        
                            //Получаем статус
                            $status = $statusesOfBillsRepository->findBy( array('id' => $arrBStatus[$i]) );
                            if (sizeof($status) > 0) {
                                $status = $status[0];

                                //Проверяем, изменился ли статус
                                if ($billsRepository->getStatus($arrBID[$i]) != $arrBStatus[$i]) {
                                    //Вносим информацию в базу
                                    $billStatus = new BillsStatuses();
                                    $billStatus->setBill($bill);
                                    $billStatus->setStatus($status);
                                    $this->entityManager->persist($billStatus);
                                    $this->entityManager->flush();
                                }
                            }
                        }
                    }   
                }

                //Назначаем ответственных к материалам
                if ($arrMID != null && $arrMResponsible != null && sizeof($arrMID) > 0 && sizeof($arrMResponsible) > 0 && sizeof($arrMID) == sizeof($arrMResponsible)) {
                    for ($i=0; $i<sizeof($arrMID); $i++) {
                        //Получаем материал
                        $material = $materialsRepository->findBy( array('id' => $arrMID[$i]) );
                        if (sizeof($material) > 0) {
                            if (is_array($material)) {$material = array_shift($material);}
                        
                            //Получаем ответственного
                            if (!empty($arrMResponsible[$i])) {
                                $user = $usersRepository->findBy( array('id' => $arrMResponsible[$i]) );
                                if (sizeof($user) > 0) {
                                    if (is_array($user)) {$user = array_shift($user);}

                                    if ($material->getResponsible() === null || $material->getResponsible()->getId() != $user->getId()) {
                                        //Если были изменения по ответственным, вносим их
                                        $material->setResponsible($user);
                                        $this->entityManager->persist($material);
                                        $this->entityManager->flush();
        
                                        //Добавляем информацию в лог
                                        $responsibleLog = new ResponsibleLog;
                                        $responsibleLog->setMaterial($material);
                                        $responsibleLog->setResponsible($user);
                                        $responsibleLog->setSupervisor($this->security->getUser());
                                        $this->entityManager->persist($responsibleLog);
                                        $this->entityManager->flush();
                                    }
                                }
                            } else {
                                //Ответственный не назначен
                                if ($material->getResponsible() !== null) {
                                    $material->setResponsible(null);
                                    $this->entityManager->persist($material);
                                    $this->entityManager->flush();

                                    //Добавляем информацию в лог
                                    $responsibleLog = new ResponsibleLog;
                                    $responsibleLog->setMaterial($material);
                                    $responsibleLog->setSupervisor($this->security->getUser());
                                    $this->entityManager->persist($responsibleLog);
                                    $this->entityManager->flush();   
                                }
                            }
                        }
                    } 
                }

                //Если в завке назначены все ответственные и стоит статус "Ожидание назначения ответственных", ставим статус "Выполнение"
                $materialsWithoutResponsible = $materialsRepository->createQueryBuilder('m')
                ->where('m.application = :application')
                ->andWhere('m.isDeleted = FALSE')
                ->andWhere('m.responsible IS NULL')
                ->setParameter('application', $id)
                ->getQuery()
                ->getResult();

                if (sizeof($materialsWithoutResponsible) == 0) {
                    //Все назначены, получаем статус
                    $status = $applicationsStatusesRepository->findBy( array('application' => $id), array('datetime' => 'DESC') );
                    if (is_array($status)) {$status = array_shift($status);}
                    if ($status->getStatus()->getId() == 1) {
                        //Получаем заявку
                        $objApplication = $applicationsRepository->findBy( array('id' => $id) );
                        if (is_array($objApplication)) {$objApplication = array_shift($objApplication);}

                        //Получаем статус
                        $objStatus = $statusesOfApplicationsRepository->findBy( array('id' => 2) );
                        if (is_array($objStatus)) {$objStatus = array_shift($objStatus);}

                        //Добавляем статус "Выполнение" в базу
                        $applicationStatus = new ApplicationsStatuses;
                        $applicationStatus->setApplication($objApplication);
                        $applicationStatus->setStatus($objStatus);

                        $this->entityManager->persist($applicationStatus);
                        $this->entityManager->flush();
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
     * Поиск по заявкам
     * @Route("/applications/search", methods={"GET"}))
     * @IsGranted("ROLE_USER")
     */
    public function search(
        Request $request, 
        ApplicationsRepository $applicationsRepository, 
        MaterialsRepository $materialsRepository,
        TypesOfEquipmentRepository $typesOfEquipmentRepository
    ): Response
    {
        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        $q = $request->query->get('q');
        if ($q === null || empty($q)) {
            $title = 'Поиск по содержимому заявок';
        } else {
            $title = 'Результаты поиска по содержимому заявок';
        }

        $results = [];

        if (mb_strlen($q) > 2) {
            //Выполняем поиск по заголовкам
            $applications = $applicationsRepository->findLike($q);

            foreach ($applications as $application) {
                $result = new \stdClass;
                $result->application = $application;
                $result->materials = [];
                $result->comment = '';
                $result->number = '';
                $result->equipment = '';
                $results[] = $result;
                unset($result);
            }

            unset($applications);

            //Выполняем поиск по комментариям
            $applications = $applicationsRepository->findLikeComment($q);

            foreach ($applications as $application) {
                $exists = false;
                for ($i=0; $i<sizeof($results); $i++) {
                    if ($results[$i]->application->getId() == $application->getId()) {
                        $exists = true;
                        //Заявка уже существует в результатах, добавляем в нее комментарий
                        $results[$i]->comment = $application->getComment();
                    }
                }

                if (!$exists) {
                    //Добавляем заявку в результаты
                    $result = new \stdClass;
                    $result->application = $application;
                    $result->materials = [];
                    $result->comment = $application->getComment();
                    $result->number = '';
                    $result->equipment = '';
                    $results[] = $result;
                    unset($result);
                }
            }

            unset($applications);

            //Выполняем поиск по дополнительным номерам
            $applications = $applicationsRepository->findLikeNumber($q);

            foreach ($applications as $application) {
                $exists = false;
                for ($i=0; $i<sizeof($results); $i++) {
                    if ($results[$i]->application->getId() == $application->getId()) {
                        $exists = true;
                        //Заявка уже существует в результатах, добавляем в нее комментарий
                        $results[$i]->number = $application->getNumber();
                    }
                }

                if (!$exists) {
                    //Добавляем заявку в результаты
                    $result = new \stdClass;
                    $result->application = $application;
                    $result->materials = [];
                    $result->comment = '';
                    $result->number = $application->getNumber();
                    $result->equipment = '';
                    $results[] = $result;
                    unset($result);
                }
            }

            unset($applications);

            //Выполняем поиск по виду техники
            $toes = $typesOfEquipmentRepository->findLike($q);

            if (sizeof($toes) > 0) {
                foreach ($toes as $toe) {
                    // Получаем список материалов
                    $materials = $materialsRepository->findBy(array('typeOfEquipment' => $toe->getId()));
                    
                    foreach ($materials as $material) {
                        $exists = false;
                        for ($i=0; $i<sizeof($results); $i++) {
                            if ($results[$i]->application->getId() == $material->getApplication()->getId()) {
                                $exists = true;
                                //Заявка уже существует в результатах, добавляем в нее материал
                                array_push($results[$i]->materials, $material);
                                $results[$i]->equipment = $toe->getTitle();
                            }
                        }
    
                        if (!$exists) {
                            //Добавляем заявку в результаты
                            $result = new \stdClass;
                            $result->application = $material->getApplication();
                            $result->materials = [$material];
                            $result->comment = '';
                            $result->number = '';
                            $result->equipment = $toe->getTitle();
                            $results[] = $result;
                            unset($result);
                        }
                    }
                }
            }

            unset($materials, $toes);

            //Выполняем поиск по материалам
            $materials = $materialsRepository->findLike($q, 'm.id', 'DESC');

            if (sizeof($materials) > 0) {
                foreach ($materials as $material) {
                    $exists = false;
                    for ($i=0; $i<sizeof($results); $i++) {
                        if ($results[$i]->application->getId() == $material->getApplication()->getId()) {
                            $exists = true;
                            //Заявка уже существует в результатах, добавляем в нее материал
                            array_push($results[$i]->materials, $material);
                        }
                    }

                    if (!$exists) {
                        //Добавляем заявку в результаты
                        $result = new \stdClass;
                        $result->application = $material->getApplication();
                        $result->materials = [$material];
                        $result->comment = '';
                        $result->number = '';
                        $result->equipment = '';
                        $results[] = $result;
                        unset($result);
                    }
                }
            }

            unset($materials);

            for ($i=0; $i<sizeof($results); $i++) {
                $results[$i]->applicationUrgency = $applicationsRepository->getUrgency($results[$i]->application->getId());
            }

            //Убираем из результатов те заявки которые пользователь не может видеть
            $results_ = [];

            foreach ($results as $result) {
                //Проверяем наличие прав просмотра
                $canSee = false;
                
                if (in_array('ROLE_SUPERVISOR', $roles)) {$canSee = true;}
                if (in_array('ROLE_CREATOR', $roles) && $this->security->getUser()->getId() == $result->application->getAuthor()->getId()) {$canSee = true;}
                if (in_array('ROLE_EXECUTOR', $roles)) {
                    $arrMaterials = $materialsRepository->findBy(array('application' => $result->application->getId()));
                    foreach ($arrMaterials as $material) {
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
        $breadcrumbs[1]->href = '/applications/search'.(!empty($q) ? '?q='.$q : '');
        $breadcrumbs[1]->title = $title;

        return $this->render('applications/search.html.twig', [
            'title' => $title,
            'breadcrumbs' => $breadcrumbs,
            'q' => $q,
            'results' => $results
        ]);
    }

    /**
     * Применение фильтра для выполненных заявок
     * @Route("/applications/apply-filter-done", methods={"POST"})
     * @IsGranted("ROLE_USER")
     */
    public function doneApplicationsApplyFilter(Request $request, UsersRepository $usersRepository, StatusesOfApplicationsRepository $statusesOfApplicationsRepository, ApplicationsRepository $applicationsRepository, BillsStatusesRepository $billsStatusesRepository, OfficesRepository $officesRepository): Response
    {
        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        //Получаем фильтр
        $filter = new Filter;

        //Получаем параметры
        $results = 25; if ($request->cookies->get('resultsCount') !== null) {$results = $request->cookies->get('resultsCount');}
        if (!is_numeric($results) || $results < 0) {$results = 25;}
        $filter->resultsPerPage = $results;

        //Фильтр по автору
        //Если у пользователя права ответственного, он может видеть только свои заявки
        if (in_array('ROLE_CREATOR', $roles) && !in_array('ROLE_SUPERVISOR', $roles)) {
            $filter->author = $usersRepository->findBy(array('id' => $this->security->getUser()->getId()));
            if (is_array($filter->author)) {$filter->author = array_shift($filter->author);}
            // $filter->isFiltered = true;
        } else {
            if ($request->request->get('filterAuthor') !== null && $request->request->get('filterAuthor') != -1) {
                //Получаем отправителя из запроса
                $filter->author = $usersRepository->findBy(array('active' => true, 'id' => (int)$request->request->get('filterAuthor')));
                if (is_array($filter->author)) {$filter->author = array_shift($filter->author);}
                $filter->isFiltered = true;
            }
        }

        //Фильтр по ответственному
        //Если у пользователя права ответственного, он может видеть только свои заявки
        if (in_array('ROLE_EXECUTOR', $roles) && !in_array('ROLE_SUPERVISOR', $roles)) {
            $filter->responsible = $usersRepository->findBy(array('id' => $this->security->getUser()->getId()));
            if (is_array($filter->responsible)) {$filter->responsible = array_shift($filter->responsible);}
            // $filter->isFiltered = true;
        } else {
            if ($request->request->get('filterResponsible') !== null && $request->request->get('filterResponsible') != -1) {
                //Получаем исполнителя из запроса
                $filter->responsible = $usersRepository->findBy(array('active' => true, 'id' => (int)$request->request->get('filterResponsible')));
                if (is_array($filter->responsible)) {$filter->responsible = array_shift($filter->responsible);}
                $filter->isFiltered = true;
            }
        }

        //Фильтр по подразделению
        //Если у пользователя права ответственного, он может видеть только свои заявки
        if (in_array('ROLE_EXECUTOR', $roles) && !in_array('ROLE_SUPERVISOR', $roles)) {
            $filter->office = $officesRepository->findBy(array('id' => $this->security->getUser()->getOffice()->getId()));
            if (is_array($filter->office)) {$filter->office = array_shift($filter->office);}
            // $filter->isFiltered = true;
        } else {
            if ($request->request->get('filterOffice') !== null && $request->request->get('filterOffice') != -1) {
                //Получаем исполнителя из запроса
                $filter->office = $officesRepository->findBy(array('id' => (int)$request->request->get('filterOffice')));
                if (is_array($filter->office)) {$filter->office = array_shift($filter->office);}
                $filter->isFiltered = true;
            }
        }

        if ($request->request->get('filterStatus') !== null && $request->request->get('filterStatus') != -1) {
            //Получаем статус из запроса
            $filter->status = $statusesOfApplicationsRepository->findBy(array('id' => (int)$request->request->get('filterStatus')));
            if (is_array($filter->status)) {$filter->status = array_shift($filter->status);}
            $filter->isFiltered = true;
        }

        if ($request->request->get('filterTitle') !== null && !empty($request->request->get('filterTitle'))) {
            //Получаем заголовок из запроса
            $filter->title = $request->request->get('filterTitle');
            $filter->isFiltered = true;
        }

        if ($request->request->get('filterDateCloseFrom') !== null && !empty($request->request->get('filterDateCloseFrom'))) {
            //Получаем дату начала диапазона из запроса
            $filter->dateCloseFrom = new \DateTime($request->request->get('filterDateCloseFrom').' 00:00:00');
            $filter->isFiltered = true;
        }

        if ($request->request->get('filterDateCloseTo') !== null && !empty($request->request->get('filterDateCloseTo'))) {
            //Получаем дату окончания диапазона из запроса
            $filter->dateCloseTo = new \DateTime($request->request->get('filterDateCloseTo').' 00:00:00');
            $filter->isFiltered = true;
        }

        //Фильтр по годовым заявкам
        if ($request->request->get('filterYear') !== null) {
            if ((int)$request->request->get('filterYear') == 1) {
                $filter->year = 1;
                $filter->isFiltered = true;
            } elseif ((int)$request->request->get('filterYear') == 0) {
                $filter->year = 0;
                $filter->isFiltered = true;
            } else {
                $filter->year = -1;
            }
        }

        //Сортировка
        if ($request->request->get('filterOrderBy') !== null && $request->request->get('filterSort') !== null) {
            $filter->sort = $request->request->get('filterSort');
            $filter->orderByIndex = $request->request->get('filterOrderBy');
            switch ($filter->orderByIndex) {
                case '0': $filter->orderBy = 'a.id'; break;
                case '1': $filter->orderBy = 'a.date_create'; break;
                case '2': $filter->orderBy = 'a.title'; break;
                case '3': $filter->orderBy = 'e.username'; break;
                case '4': $filter->orderBy = 'status_title'; break;
            }
        }

        //Страница
        if ($request->request->get('filterPage') !== null) {
            $filter->page = (int)$request->request->get('filterPage');
        }

        $_SESSION['applicationsDoneFilter'] = serialize($filter);
        return new RedirectResponse('/applications/done');
    }

    /**
     * Снятие фильтра для выполненных заявок
     * @Route("/applications/delete-filter-done", methods={"GET"})
     * @IsGranted("ROLE_USER")
     */
    public function doneApplicationsDeleteFilter(Request $request, UsersRepository $usersRepository): Response
    {
        $roles = $this->security->getUser()->getRoles();

        //Получаем фильтр
        $filter = new Filter;
        if (in_array('ROLE_EXECUTOR', $roles) && !in_array('ROLE_SUPERVISOR', $roles)) {
            $filter->responsible = $usersRepository->findBy(array('id' => $this->security->getUser()->getId()));
            if (is_array($filter->responsible)) {$filter->responsible = array_shift($filter->responsible);}
            $filter->resultsPerPage = 0;
        }

        $_SESSION['applicationsDoneFilter'] = serialize($filter);
        return new RedirectResponse('/applications/done');
    }

    /**
     * Список выполненных заявок
     * @Route("/applications/done", name="done", methods={"GET", "POST"})
     * @IsGranted("ROLE_USER")
     */
    public function doneApplications(Request $request, UsersRepository $usersRepository, StatusesOfApplicationsRepository $statusesOfApplicationsRepository, ApplicationsRepository $applicationsRepository, BillsStatusesRepository $billsStatusesRepository, OfficesRepository $officesRepository): Response
    {
        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        //Получаем фильтр
        if (isset($_SESSION['applicationsDoneFilter'])) {
            $filter = unserialize($_SESSION['applicationsDoneFilter']);
        } else {
            $filter = new Filter;
            if (in_array('ROLE_EXECUTOR', $roles) && !in_array('ROLE_SUPERVISOR', $roles)) {
                $filter->responsible = $usersRepository->findBy(array('id' => $this->security->getUser()->getId()));
                if (is_array($filter->responsible)) {$filter->responsible = array_shift($filter->responsible);}
                $filter->resultsPerPage = 0;
            }
        }
        $filter->done = true;

        //Фильтр готов, выводим форму
        $applications = $applicationsRepository->getList($filter);

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications';
        $breadcrumbs[0]->title = 'Активные заявки';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/applications/done';
        $breadcrumbs[1]->title = 'Выполненные заявки';

        //Получаем отправителей и ответственных
        $users = $usersRepository->findBy(
            array('active' => true),
            array('username' => 'ASC', 'id' => 'ASC')
        );

        $usersSenders = [];
        $usersResponsibles = [];
        foreach ($users as $user) {
            if (in_array('ROLE_CREATOR', $user->getRoles())) {
                $usersSenders[] = $user;
            }

            if (in_array('ROLE_EXECUTOR', $user->getRoles())) {
                $usersResponsibles[] = $user;
            }
        }

        unset($users);

        $params = [
            'title' => 'Выполненные заявки',
            'breadcrumbs' => $breadcrumbs,
            'applications' => $applications,
            'usersResponsibles' => $usersResponsibles,
            'usersSenders' => $usersSenders,
            'offices' => $officesRepository->findAll(),
            'statuses' => $statusesOfApplicationsRepository->getStatusesForDoneFilter(),
            'filter' => $filter,
            'printcount' => $this->getPrintBillsCount()
        ];

        //Проверяем есть ли уведомление
        if ($request->request->get('msg') !== null && $request->request->get('bg-color') !== null && $request->request->get('text-color') !== null) {
            ob_start();

            echo '<script type="text/javascript">'."\n";
            echo '    $(document).ready(function() {'."\n";
            echo '        addToast(\''.$request->request->get('msg').'\', \''.$request->request->get('bg-color').'\', \''.$request->request->get('text-color').'\');'."\n";
            echo '        showToasts();'."\n";
            echo '    });'."\n";
            echo '</script>'."\n";

            $scripts = ob_get_contents();
            ob_end_clean();

            $params['scripts'] = $scripts;
        }

        return $this->render('applications/done.html.twig', $params);
    }

    /**
     * Экспорт заявки в Excel
     * @Route("/applications/export-to-excel", methods={"GET"})
     * @IsGranted("ROLE_USER")
     */
    public function exportToExcelApplication(
        Request $request, 
        ApplicationsRepository $applicationsRepository, 
        MaterialsRepository $materialsRepository, 
        UsersRepository $usersRepository
        ): Response
    {
        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        $id = $request->query->get('number');
        if ($id === null || empty($id) || !is_numeric($id)) {
            return new RedirectResponse('/applications');
        }

        //Проверяем наличие заявки
        $objApplication = $applicationsRepository->findBy( array('id' => $id) );
        if (sizeof($objApplication) == 0) {
            return new RedirectResponse('/applications');
        }
        if (is_array($objApplication)) {$objApplication = array_shift($objApplication);}

        //Проверяем наличие прав просмотра
        $canPrint = false;
        
        if (in_array('ROLE_SUPERVISOR', $roles)) {$canPrint = true;}
        if (in_array('ROLE_CREATOR', $roles) && $this->security->getUser()->getId() == $objApplication->getAuthor()->getId()) {$canPrint = true;}

        $arrMaterials = $materialsRepository->findBy(array('application' => $id)); //Также пригодится потом

        if (in_array('ROLE_EXECUTOR', $roles)) {
            foreach ($arrMaterials as $material) {
                if ($material->getResponsible() !== null) {
                    if ($material->getResponsible()->getId() == $this->security->getUser()->getId()) {
                        $canPrint = true;
                        break;
                    }
                }
            }
        }

        if (!$canPrint) { 
            return new RedirectResponse('/applications');
        }

        //Заголовок
        $title = $objApplication->getTitle().' №'.$id;

        //Список материалов
        $arrMaterials = $materialsRepository->findBy( array('application' => $objApplication->getId()), array('num' => 'ASC') );

        //Список пользователей
        $users = $usersRepository->findByRole('ROLE_EXECUTOR');

        $objPHPExcel = new \PHPExcel();

        //Свойства документа
        $objPHPExcel->getProperties()->setTitle($objApplication->getTitle().' №'.$id.(empty($objApplication->getNumber()) ? '' : ' ('.$objApplication->getNumber().')'));
        $objPHPExcel->getProperties()->setCompany('ЗАО «Артель старателей «Витим»');
        $objPHPExcel->getProperties()->setCreated(date('d.m.Y'));

        //Создаем лист
        $objPHPExcel->setActiveSheetIndex(0);
        $sheet = $objPHPExcel->getActiveSheet();
        $sheet->setTitle('Заявка '.$id);
        
        //Формат
        $sheet->getPageSetup()->SetPaperSize(\PHPExcel_Worksheet_PageSetup::PAPERSIZE_A4);
        
        //Ориентация
        $sheet->getPageSetup()->setOrientation(\PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE); //ORIENTATION_PORTRAIT, ORIENTATION_LANDSCAPE
        
        //Поля
        $sheet->getPageMargins()->setTop(1);
        $sheet->getPageMargins()->setRight(0.75);
        $sheet->getPageMargins()->setLeft(0.75);
        $sheet->getPageMargins()->setBottom(1);

        //Задаем шапку
        $sheet->setCellValueByColumnAndRow(0, 1, '№');
        $sheet->setCellValueByColumnAndRow(1, 1, 'Наименование');
        $sheet->setCellValueByColumnAndRow(2, 1, 'Ед.изм.');
        $sheet->setCellValueByColumnAndRow(3, 1, 'Кол-во');
        $sheet->setCellValueByColumnAndRow(4, 1, 'Вид техники');
        $sheet->setCellValueByColumnAndRow(5, 1, 'Уточнение');
        $sheet->setCellValueByColumnAndRow(6, 1, 'Срочность');
        $sheet->setCellValueByColumnAndRow(7, 1, 'Ответственный');

        $style = array(
            'fill' => array(
                'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'EEEEEE')
            ),
            'font' => array(
                'size'      => 12,
                'bold'      => true
            ),
            'borders'=>array(
                'allborders' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => '000000')
                )
            )
        );

        $sheet->getStyle('A1:H1')->applyFromArray($style);

        //Заполняем содержимое
        $num = 0;
        foreach ($arrMaterials as $material) {
            if (!$material->getIsDeleted()) {
                $num++;

                $sheet->setCellValueByColumnAndRow(0, $num + 1, $num);
                $sheet->setCellValueByColumnAndRow(1, $num + 1, $material->getTitle());
                $sheet->setCellValueByColumnAndRow(2, $num + 1, $material->getUnit()->getTitle());
                $sheet->setCellValueByColumnAndRow(3, $num + 1, $material->getAmount());
                $sheet->setCellValueByColumnAndRow(4, $num + 1, ( $material->getTypeOfEquipment() ? $material->getTypeOfEquipment()->getTitle() : '' ));
                $sheet->setCellValueByColumnAndRow(5, $num + 1, $material->getComment());

                if ($material->getUrgency()) {
                    $sheet->setCellValueByColumnAndRow(6, $num + 1, 'Срочно');
                    $style = array(
                        'font' => array(
                            'color'     => array('rgb' => 'FF0000'),
                            'bold'      => true
                        )
                    );
            
                    $sheet->getStyle('G'.($num + 1))->applyFromArray($style);
                }

                if ($material->getResponsible()) {
                    $sheet->setCellValueByColumnAndRow(7, $num + 1, $material->getResponsible()->getShortUsername());
                } else {
                    $sheet->setCellValueByColumnAndRow(7, $num + 1, 'Не назначен');
                    $style = array(
                        'font' => array(
                            'color'     => array('rgb' => 'CCCCCC'),
                            'bold'      => true
                        )
                    );
            
                    $sheet->getStyle('H'.($num + 1))->applyFromArray($style);
                }
            }
        }

        $style = array(
            'borders'=>array(
                'allborders' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => '000000')
                )
            )
        );

        $sheet->getStyle('A2:H'.($num + 1))->applyFromArray($style);

        //Авто ширина колонки по содержимому
        foreach(range('A','H') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(TRUE);
        }
        
        //Отдаем на скачивание
        header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename=Заявка '.$id.'.xlsx');
         
        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $objWriter->save('php://output'); 
        exit();
    }

    /**
     * Печать заявки для согласования
     * @Route("/applications/print", methods={"GET"})
     * @IsGranted("ROLE_USER")
     */
    public function printApplication(
        Request $request, 
        ApplicationsRepository $applicationsRepository, 
        MaterialsRepository $materialsRepository, 
        UsersRepository $usersRepository
        ): Response
    {
        //Получаем роли текущего пользователя
        $roles = $this->security->getUser()->getRoles();

        $id = $request->query->get('number');
        if ($id === null || empty($id) || !is_numeric($id)) {
            return new RedirectResponse('/applications');
        }

        //Проверяем наличие заявки
        $objApplication = $applicationsRepository->findBy( array('id' => $id) );
        if (sizeof($objApplication) == 0) {
            return new RedirectResponse('/applications');
        }
        if (is_array($objApplication)) {$objApplication = array_shift($objApplication);}

        //Проверяем наличие прав просмотра
        $canPrint = false;
        
        if (in_array('ROLE_SUPERVISOR', $roles)) {$canPrint = true;}
        if (in_array('ROLE_CREATOR', $roles) && $this->security->getUser()->getId() == $objApplication->getAuthor()->getId()) {$canPrint = true;}

        $arrMaterials = $materialsRepository->findBy(array('application' => $id)); //Также пригодится потом

        if (in_array('ROLE_EXECUTOR', $roles)) {
            foreach ($arrMaterials as $material) {
                if ($material->getResponsible() !== null) {
                    if ($material->getResponsible()->getId() == $this->security->getUser()->getId()) {
                        $canPrint = true;
                        break;
                    }
                }
            }
        }

        if (!$canPrint) { 
            return new RedirectResponse('/applications');
        }

        //Заголовок
        $title = $objApplication->getTitle().' №'.$id;

        //Список материалов
        $arrMaterials = $materialsRepository->findBy( array('application' => $objApplication->getId()), array('num' => 'ASC') );

        //Список пользователей
        $users = $usersRepository->findByRole('ROLE_EXECUTOR');

        ob_start();
        echo <<<HERE
            h1 {
                font-family: Tahoma, Serif;
                font-size: 24px;
                margin-bottom: 10px;
                }

            h2 {
                color: #555555;
                font-family: Tahoma, Serif;
                font-size: 14px;
                font-weight: normal;
                }

            table {
                border-collapse: collapse;
                font-family: Tahoma, Serif;
                margin-top: 10px;
                width: 100%;
                }

            table thead tr th,
            table tbody tr td {
                border: 1px solid #000000;
                font-weight: normal;
                padding: 5px 10px;
                }

            table thead tr th {
                background: #dddddd;
                border-bottom: 0px solid #000000;
                }

            table tbody tr td.no-border {
                border: none;
                padding: 0;
                }
HERE;
        $css = ob_get_contents();
        ob_end_clean();

        ob_start();

        echo '<table autosize="1" border="1" width="100%" style="overflow: wrap">';
        echo '    <tbody>';
        echo '        <tr>';
        echo '            <td class="no-border"><h1>'.$objApplication->getTitle().'</h1></td>';
        echo '            <td class="no-border" style="text-align: right;">Согласовано: _________________ /Жарков Ю.В./</td>';
        echo '        </tr>';
        echo '    </tbody>';
        echo '</table>';
        echo '<h2>Заявка от '.$objApplication->getDateCreate()->format('d.m.Y').' № '.$objApplication->getId().(!empty($objApplication->getNumber()) ? ' ('.$objApplication->getNumber().')' : '').'</h2>';
        echo '<h2>Отправитель: '.$objApplication->getAuthor()->getUsername().'</h2>';

        echo <<<HERE
            <table>
                <thead>
                    <tr>
                        <th>№</th>
                        <th style="text-align: left;">Наименование</th>
                        <th>Ед.изм.</th>
                        <th>Кол-во</th>
                        <th style="text-align: left;">Вид техники</th>
                        <th style="text-align: left;">Уточнение</th>
                        <th>Срочность</th>
                        <th>Ответственный</th>
                    </tr>
                </thead>
                <tbody>
HERE;

                    $num = 0;
                    foreach ($arrMaterials as $material) {
                        if (!$material->getIsDeleted()) {
                            $num++;
                            echo '                    <tr>'."\n";
                            echo '                        <td style="text-align: center;">'.$num.'</td>'."\n";
                            echo '                        <td>'.$material->getTitle().'</td>'."\n";
                            echo '                        <td style="text-align: center;">'.$material->getUnit()->getTitle().'</td>'."\n";
                            echo '                        <td style="text-align: center;">'.$material->getAmount().'</td>'."\n";
                            echo '                        <td>'.( $material->getTypeOfEquipment() ? $material->getTypeOfEquipment()->getTitle() : '' ).'</td>'."\n";
                            echo '                        <td>'.wordwrap($material->getComment(), 60, '<br />', true).'</td>'."\n";
                            echo '                        <td style="text-align: center;">'.( $material->getUrgency() ? '<span style="color: #ff0000; font-size: 12px; text-transform:uppercase;">Срочно</span>' : '' ).'</td>'."\n";
                            echo '                        <td style="text-align: center;">'.( $material->getResponsible() ? $material->getResponsible()->getShortUsername() : '<span style="color: #777; font-size: 12px;">Не назначен</span>' ).'</td>'."\n";
                            echo '                    </tr>'."\n";
                        }
                    }

                    echo <<<HERE
                </tbody>
            </table>
HERE;

        $content = ob_get_contents();
        ob_end_clean();

        ob_start();

        echo '            <table style="font-size: 12px;">'."\n";
        echo '                <tr>'."\n";
        echo '                    <td width="33%">Дата печати: {DATE j.m.Y}</td>'."\n";
        echo '                    <td width="33%" align="center">Страница {PAGENO} из {nbpg}</td>'."\n";
        echo '                    <td width="33%" style="text-align: right;">Заявка: '.$title.'</td>'."\n";
        echo '                </tr>'."\n";
        echo '            </table>'."\n";

        $footer = ob_get_contents();
        ob_end_clean();

        require_once $this->getParameter('kernel.project_dir').'/../vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'orientation' => 'L'
        ]);
        // $mpdf->debug = true;

        $mpdf->packTableData = true;
        $mpdf->keep_table_proportions = TRUE;
        $mpdf->shrink_tables_to_fit=1;
        $mpdf->SetHTMLFooter($footer);
        $mpdf->SetTitle($title);
        // $mpdf->setFooter('{PAGENO} / {nbpg}');
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($content, \Mpdf\HTMLParserMode::HTML_BODY);
        
        // $mpdf->SetHTMLFooter('Page {PAGENO} of {nb}');

        $mpdf->Output($title.'.pdf', 'I');
    }

    /**
     * Разбитие позиции в заявке на несколько (принимает данные из формы)
     * @Route("/applications/split", methods={"POST"}))
     * @IsGranted("ROLE_EXECUTOR")
     */
    public function splitMaterialForm(
        Request $request, 
        ApplicationsRepository $applicationsRepository, 
        BillsMaterialsRepository $billsMaterialsRepository,
        MaterialsRepository $materialsRepository, 
        TypesOfEquipmentRepository $typesOfEquipmentRepository, 
        UnitsRepository $unitsRepository, 
        UsersRepository $usersRepository, 
        StatusesOfApplicationsRepository $statusesOfApplicationsRepository, 
        FilesRepository $filesRepository): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('split-material', $submittedToken)) {
            //Получаем заявку
            $application = $applicationsRepository->findBy( array('id' => $request->request->get('id')) );
            if (sizeof($application) > 0) {$application = array_shift($application);}

            //Получаем материал
            $originalMaterial = $materialsRepository->findBy( array('id' => $request->request->get('material')) );
            if (sizeof($originalMaterial) > 0) {$originalMaterial = array_shift($originalMaterial);}

            //Получаем счет, если он есть
            $billMaterial = $billsMaterialsRepository->findBy(array('material' => $originalMaterial->getId()));
            if (sizeof($billMaterial) > 0) {
                $billMaterial = array_shift($billMaterial);
                $objBill = $billMaterial->getBill();
            }

            //Получаем количество позиций в заявке
            // $materials = $materialsRepository->findBy( array('application' => $application->getId()) );
            // $materialsCount = sizeof($materials);

            //Проверяем наличие прав на изменение данной позиции
            if ($originalMaterial->getResponsible()->getId() != $this->security->getUser()->getId() || !in_array('ROLE_EXECUTOR', $this->getUser()->getRoles())) {
                $result[] = 0;
                $result[] = 'Нет прав для сохранения изменений';

                return new JsonResponse($result);
            }

            //Готовим данные
            $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

            try {
                //Начинаем запись, вносим изменения в старый материал
                $originalMaterial->setIsDeleted(true);
                $this->entityManager->persist($originalMaterial);
                $this->entityManager->flush();

                //Получаем массив наименований
                $arrTitles = $request->request->get('titleContentApp');
                $rowsCount = sizeof($arrTitles); //Определяем полезное количество строк
                while ($rowsCount > 0) {if (empty($arrTitles[$rowsCount - 1])) {$rowsCount--;} else {break;}}
                $arrTitles = array_slice($arrTitles, 0, $rowsCount);
                $arrCounts = array_slice($request->request->get('amountContentApp'), 0, $rowsCount); //Получаем массив количества
                
                //Получаем массив единиц измерения
                $unitsRaw = array_slice($request->request->get('unitContentApp'), 0, $rowsCount);
                
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
                
                $materials = [];

                //Добавляем материалы к заявке
                for ($i = 0; $i < sizeof($arrTitles); $i++) {
                    $material = new Materials(
                        $arrTitles[$i],
                        $arrCounts[$i], 
                        $originalMaterial->getUnit(), //Units::class
                        $originalMaterial->getUrgency(), 
                        $originalMaterial->getTypeOfEquipment(), //TypesOfEquipment::class
                        'Замена позиции №'.$originalMaterial->getNum().' ('.$originalMaterial->getTitle().')',
                        '',
                        $application, //Applications::class
                        $originalMaterial->getNum().'.'.($i + 1) // $materialsCount + $i + 1
                    );

                    $material->setApplication($application);
                    $material->setResponsible($originalMaterial->getResponsible());
                    $this->entityManager->persist($material);
                    $this->entityManager->flush();

                    $materials[] = $material;
                    unset($material);
                }

                //Если был загружен счет, добавляем материалы в данный счет
                if (isset($objBill)) {
                    //Удаляем материал из счета
                    $this->entityManager->remove($billMaterial);
                    $this->entityManager->flush();

                    foreach ($materials as $material) {
                        $objBillMaterial = new BillsMaterials;
                        $objBillMaterial->setBill($objBill);
                        $objBillMaterial->setAmount($material->getAmount());
                        $objBillMaterial->setMaterial($material);

                        //Записываем
                        $this->entityManager->persist($objBillMaterial);
                        $this->entityManager->flush();
                    }
                }

                unset($materials);

                $this->entityManager->getConnection()->commit();

                $result[] = 1;
                $result[] = $request->request->get('id');
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
     * Список контрагентов
     * @Route("/applications/providers", methods={"GET"})
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_EXECUTOR') or is_granted('ROLE_BUH')")
     */
    public function providersList(
        BillsRepository $billsRepository,
        ProvidersRepository $providersRepository,
        Request $request): Response
    {
        //Получаем список известных поставщиков
        $providers = $providersRepository->findAll();

        //Получаем список всех ИНН из загруженных счетов
        $innQuery = $billsRepository->createQueryBuilder('b')
        ->select('DISTINCT b.inn AS inn')
        ->orderBy('b.inn', 'ASC')
        ->getQuery()
        ->getResult();

        $inn = [];
        foreach ($innQuery as $inn_) {
            //Смотрим, есть ли в поставщиках информация по данному ИНН
            $exist = FALSE;
            foreach ($providers as $provider) {if ($provider->getInn() == $inn_['inn']) {$exist = TRUE; break;}}
            if (!$exist) {$inn[] = $inn_['inn'];}
        }

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications/providers';
        $breadcrumbs[0]->title = 'Список контрагентов';

        return $this->render('applications/providers.html.twig', [
            'title' => 'Список контрагентов',
            'breadcrumbs' => $breadcrumbs,
            'providers' => $providers,
            'inns' => $inn
        ]);
    }

    /**
     * Добавление сообщения к материалу
     * @Route("/applications/set-material-message", methods={"POST"}))
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_EXECUTOR')")
     */
    public function setMaterialMesage(
        Request $request, 
        MaterialsRepository $materialsRepository, 
        MaterialsCommentsRepository $materialsCommentsRepository,
        FilesRepository $filesRepository): JsonResponse
    {
        $result = [];

        $materials = json_decode($request->request->get('materials'));
        $color = $request->request->get('color');
        $message = $request->request->get('message');

        try {
            if (!empty($message)) {
                //Добавляем сообщение
                foreach ($materials as $materialId) {
                    $materialsComments = new MaterialsComments;
                    $objMaterial = $materialsRepository->findBy( array('id' => $materialId) );
                    if (sizeof($objMaterial) > 0) {$objMaterial = array_shift($objMaterial);}
                    $materialsComments->setMaterial($objMaterial);
                    $materialsComments->setColor($color);
                    $materialsComments->setComment(trim($message));
                    $materialsComments->setUser($this->security->getUser());

                    $this->entityManager->persist($materialsComments);
                    $this->entityManager->flush();
                }
            } else {
                //Удаляем сообщение
                foreach ($materials as $materialId) {
                    $materialsComments = new MaterialsComments;
                    $objMaterialsComments = $materialsCommentsRepository->findBy( array('material' => $materialId, 'user' => $this->security->getUser()->getId()) );
                    if (sizeof($objMaterialsComments) > 0) {$objMaterialsComments = array_shift($objMaterialsComments);}
                    $this->entityManager->remove($objMaterialsComments);
                    $this->entityManager->flush();
                }
            }

            $result[] = 1;
            $result[] = '';            
        } catch (Exception $e) {
            $this->entityManager->getConnection()->rollBack();

            $result[] = 0;
            $result[] = $e;
            //throw $e;
        }

        return new JsonResponse($result);
    }
}

//Используется для параметров филтрации заявок
class Filter
{
    public $author;
    public $responsible;
    public $office;
    public $status;
    public $title;
    public $dateFrom;
    public $dateTo;
    public $dateCloseFrom;
    public $dateCloseTo;
    public $orderBy;
    public $orderByIndex;
    public $sort;
    public $page;
    public $resultsPerPage;
    public $done;
    public $year;
    public $isFiltered; //Флаг указвает применен ли фильтр

    public function __construct()
    {
        $this->author = null;
        $this->responsible = null;
        $this->office = null;
        $this->status = null;
        $this->title = null;
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->dateCloseFrom = null;
        $this->dateCloseTo = null;
        $this->orderBy = 'a.date_create';
        $this->orderByIndex = 1;
        $this->sort = 'DESC';
        $this->page = 1;
        $this->resultsPerPage = 25;
        $this->done = null;
        $this->year = -1;
        $this->isFiltered = FALSE;
    }
}