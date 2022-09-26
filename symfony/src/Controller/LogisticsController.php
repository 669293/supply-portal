<?php
// src/Controller/LogisticsController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security as SecurityCore;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Logistics;
use App\Entity\LogisticsMaterials;
use App\Entity\Materials;
use App\Entity\Offices;
use App\Entity\Photos;
use App\Repository\ApplicationsRepository;
use App\Repository\BillsRepository;
use App\Repository\BillsMaterialsRepository;
use App\Repository\LogisticsRepository;
use App\Repository\LogisticsMaterialsRepository;
use App\Repository\MaterialsRepository;
use App\Repository\OfficesRepository;
use App\Repository\PhotosRepository;
use App\Repository\ProvidersRepository;
use App\Repository\UsersRepository;

class LogisticsController extends AbstractController
{
    private $security;
    private $entityManager;

    public function __construct(SecurityCore $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    /**
     * Автозаполнение поля "Способ отправки"
     * @Route("/logistics/way", methods={"GET"})
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_EXECUTOR') or is_granted('ROLE_LOGISTICS')")
     */
    public function wayAutocomplete(Request $request, LogisticsRepository $logisticsRepository): JsonResponse
    {
        $ways = $logisticsRepository->findWayLike($request->query->get('query'));

        $suggestions = [];
        foreach ($ways as $way) {
            $exists = false;
            for ($i=0; $i<sizeof($suggestions); $i++) {
                if ($suggestions[$i] == $way->getWay()) {$exists = true; break;}
            }
            if (!$exists) {$suggestions[] = $way->getWay();}
        }

        $result = new \stdClass;
        $result->suggestions = $suggestions;

        return new JsonResponse($result);
    }

    /**
     * Автозаполнение поля "Номер для отслеживания"
     * @Route("/logistics/track", methods={"GET"})
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_EXECUTOR') or is_granted('ROLE_LOGISTICS')")
     */
    public function trackAutocomplete(Request $request, LogisticsRepository $logisticsRepository): JsonResponse
    {
        $tracks = $logisticsRepository->findTrackLike($request->query->get('query'));
        $suggestions = [];
        foreach ($tracks as $track) {
            $exists = false;
            for ($i=0; $i<sizeof($suggestions); $i++) {
                if ($suggestions[$i] == $track->getTrack()) {$exists = true; break;}
            }
            if (!$exists) {$suggestions[] = $track->getTrack();}
        }

        $result = new \stdClass;
        $result->suggestions = $suggestions;

        return new JsonResponse($result);
    }

    /**
     * Загрузка фотографий
     * @Route("/applications/logistics/upload-photo", methods={"POST"})
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_EXECUTOR') or is_granted('ROLE_LOGISTICS')")
     */
    public function uploadPhoto(Request $request, ValidatorInterface $validator, UsersRepository $usersRepository): JsonResponse
    {
        $result = new \stdClass;

        $file = $request->files->get('photos');
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
            $dirname = uniqid(); while (file_exists($this->getParameter('photos_directory').'/'.$dirname)) {$dirname = uniqid();} 
            mkdir($this->getParameter('photos_directory').'/'.$dirname, 0755);

            try {
                $file->move(
                    $this->getParameter('photos_directory').'/'.$dirname,
                    $file->getClientOriginalName()
                );

                //Добавляем информацию в базу
                $dbFile = new Photos;
                $dbFile->setPath($dirname.'/'.$file->getClientOriginalName());

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
     * Удаление фотографий
     * @Route("/applications/logistics/delete-photo", methods={"POST"})
     * @Security("is_granted('ROLE_SUPERVISOR') or is_granted('ROLE_EXECUTOR') or is_granted('ROLE_LOGISTICS')")
     */
    public function deletePhoto(Request $request, PhotosRepository $photosRepository): JsonResponse
    {
        try {
            $file = $photosRepository->findBy( array('id' => $request->request->get('id')) );
            if (is_array($file)) {$file = array_shift($file);}

            //Получаем роли текущего пользователя
            $roles = $this->security->getUser()->getRoles();

            //Проверяем, может ли пользователь удалить этот файл
            if ($this->security->getUser()->getId() != $file->getLogistics()->getUser()->getId() && !in_array('ROLE_SUPERVISOR', $roles)) {
                return new JsonResponse(false);    
            }

            $this->entityManager->remove($file);
            $this->entityManager->flush();

            if (file_exists($this->getParameter('photos_directory').'/'.$file->getPath())) {
                unlink($this->getParameter('photos_directory').'/'.$file->getPath());
                rmdir(dirname($this->getParameter('photos_directory').'/'.$file->getPath()));
            }

            return new JsonResponse(true);
        } catch (FileException $e) {
            return new JsonResponse(false);
        }
    }

    /**
     * Вспомогательная функция для вывода дополнительной информации по отгрузке
     */
    private function getLogisticsMetaInfo(
        Logistics $logistics
    ): \stdClass 
    {
        $tmp = new \stdClass;
        $tmp->logistics = $logistics;

        //Получаем родителя
        if (is_numeric($logistics->getParent())) {
            $logistics_ = $this->entityManager->getRepository(Logistics::class)->findBy( array('id' => $logistics->getParent()) );
            if (is_array($logistics_)) {$logistics_ = array_shift($logistics_);}
            $tmp->parent = $logistics_;
        } else {
            $tmp->parent = null;    
        }

        //Получаем материалы
        $materials = $this->entityManager->getRepository(LogisticsMaterials::class)->findBy( array('logistic' => $logistics->getId()) );
        $tmp->materials = $materials;

        //Для уточнения количества материалов получаем список дочерних отгрузок
        $children = $this->entityManager->getRepository(Logistics::class)->findBy( array('parent' => $logistics->getId()) );
        $childIds = []; foreach ($children as $child) {$childIds[] = $child->getId();} unset($children);

        $arrMaterials = $this->entityManager->getRepository(LogisticsMaterials::class)->createQueryBuilder('lm')
        ->where('lm.logistic IN (:ids)')
        ->setParameter('ids', $childIds)
        ->getQuery()
        ->getResult();

        $materialsMeta = [];
        foreach ($arrMaterials as $material) {
            //Смотрим, есть ли такой материал в массиве
            $exist = false;
            for ($i=0; $i<sizeof($materialsMeta); $i++) {
                if ($materialsMeta[$i]->id == $material->getMaterial()->getId()) {
                    $materialsMeta[$i]->amount += $material->getAmount();
                    $exist = true;
                    break;
                }
            }


            if (!$exist) {
                $tmp_ = new \stdClass;
                $tmp_->id = $material->getMaterial()->getId();
                $tmp_->amount = $material->getAmount();
                $materialsMeta[] = $tmp_;
                unset($tmp_);
            }
        }
        unset($arrMaterials);

        //Отнимаем количество материалов в данной отгрузке
        $avalibleMaterials = false; if (sizeof($materialsMeta) == 0) {$avalibleMaterials = true;}
        foreach ($materials as $material) {
            $amount = (float)$material->getAmount();

            foreach ($materialsMeta as $materialMeta) {
                if ($material->getMaterial()->getId() == $materialMeta->id) {
                    $amount -= (float)$materialMeta->amount;
                }
            }

            if ($amount > 0) {
                $avalibleMaterials = true;
                break;
            }
        }

        $tmp->avalibleMaterials = $avalibleMaterials;
        $tmp->children = [];

        //Получаем фотографии и документы
        $photos = $this->entityManager->getRepository(Photos::class)->findBy( array('logistic' => $logistics->getId()) );
        $arrPhotos = [];
        foreach ($photos as $file) {
            $objFile = new \stdClass;
            $objFile->name = basename($file->getPath());
            $objFile->path = $file->getPath();
            $objFile->size = filesize($this->getParameter('photos_directory').'/'.$file->getPath());
            $objFile->type = $file->getFileType();
            $objFile->key = $file->getId();
            $arrPhotos[] = $objFile;
            unset($objFile);
        }
        $tmp->photos = $arrPhotos;

        return $tmp;
    }

    /**
     * Вспомогательная рекурсивная выборка отгрузок/получений
     */
    private function getChildLogistics($parent, &$array) {
        $arrLogistics = $this->entityManager->getRepository(Logistics::class)->findBy( array('parent' => $parent) );
        foreach ($arrLogistics as $logistics) {
            $element = $this->getLogisticsMetaInfo($logistics);
            array_push($array, $element);
            $this->getChildLogistics($logistics->getId(), $element->children);
        }
        return true;
    }

    /**
     * Просмотр логистической информации по материалу
     * @Route("/applications/logistics/view", methods={"GET"})
     * @IsGranted("ROLE_USER")
     */
    public function logisticsView(
        Request $request, 
        ApplicationsRepository $applicationsRepository,
        BillsRepository $billsRepository,
        PhotosRepository $photosRepository, 
        LogisticsRepository $logisticsRepository,
        LogisticsMaterialsRepository $logisticsMaterialsRepository,
    ): Response
    {
        $material = $request->query->get('material');
        if ($material === null || empty($material) || !is_numeric($material)) {
            $id = $request->query->get('id');
            if ($id === null || empty($id) || !is_numeric($id)) {
                return new RedirectResponse('/applications');
            }
        }

        if (!isset($id)) {
            //Выборка по материалу
            //Проверяем наличие материалов
            $logisticsMaterials = $logisticsMaterialsRepository->findBy( array('material' => $material), array('id' => 'ASC') );
            if (sizeof($logisticsMaterials) == 0) {
                return new RedirectResponse('/applications');
            }

            $tree = [];
            for ($i=0; $i<sizeof($logisticsMaterials); $i++) {
                if ($logisticsMaterials[$i]->getLogistics()->getParent() === null) {
                    $tree[] = $this->getLogisticsMetaInfo($logisticsMaterials[$i]->getLogistics());
                    $this->getChildLogistics($logisticsMaterials[$i]->getLogistics()->getId(), $tree[sizeof($tree) - 1]->children);
                }
            }

            $params['material_id'] = $material;
        } else {
            //Выборка по отгрузке            
            //Получаем отгрузку/получение
            $logistics = $logisticsRepository->findBy( array('id' => $id) );
            if (sizeof($logistics) == 0) {
                return new RedirectResponse('/applications');
            }
            if (is_array($logistics)) {$logistics = array_shift($logistics);}

            $tree = [];
            $tree[0] = $this->getLogisticsMetaInfo($logistics);
            $this->getChildLogistics($logistics->getId(), $tree[0]->children);

            $params['log_id'] = $id;
        }

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications/logistics/';
        $breadcrumbs[0]->title = 'Просмотр грузов';

        //Добваляем информацию по счету в хлебные крошки
        $bill = $request->query->get('bill');
        if ($bill !== null && !empty($bill) && is_numeric($bill)) {
            //Получаем данные по счету
            $objBill = $billsRepository->findBy(array('id' => $bill));
            if (is_array($objBill)) {$objBill = array_shift($objBill);}

            $tmp = new \stdClass();
            $tmp->href = '/applications/bills/in-work/view?id='.$objBill->getId();
            $tmp->title = 'Просмотр счета №'.$objBill->getNum().' на сумму '.number_format($objBill->getSum(), 2, ',', ' ').' '.$objBill->getCurrency();
            $breadcrumbs[] = $tmp; unset($tmp);
        }

        //Добавляем информацию по заявке в хлебные крошки
        $application = $request->query->get('application');
        if ($application !== null && !empty($application) && is_numeric($application)) {
            //Получаем данные по счету
            $objApplication = $applicationsRepository->findBy( array('id' => $application) );
            if (is_array($objApplication)) {$objApplication = array_shift($objApplication);}

            $tmp = new \stdClass();
            $tmp->href = '/applications/view?number='.$objApplication->getId();
            $tmp->title = $objApplication->getTitle().' #'.$application;
            $breadcrumbs[] = $tmp; unset($tmp);
        }

        $tmp = new \stdClass();
        $tmp->href = '/applications/logistics/view?material='.$material;
        $tmp->title = 'Логистическая информация';
        $breadcrumbs[] = $tmp; unset($tmp);

        $params['breadcrumbs'] = $breadcrumbs;
        $params['title'] = 'Просмотр логистической информации';
        $params['tree'] = $tree;

        return $this->render('logistics/view.html.twig', $params);
    }

    /**
     * Добавление логистической информации
     * @Route("/applications/logistics/add-info", methods={"GET"})
     * @IsGranted("ROLE_LOGISTICS")
     */
    public function logisticsAddInfoForm(
        Request $request, 
        LogisticsRepository $logisticsRepository,
        LogisticsMaterialsRepository $logisticsMaterialsRepository,
        OfficesRepository $officesRepository
    ): Response
    {
        $parent = $request->query->get('parent');
        if ($parent === null || empty($parent) || !is_numeric($parent)) {
            return new RedirectResponse('/applications');
        }

        //Получаем логистику
        $logistics = $logisticsRepository->findBy( array('id' => $parent) );
        if (sizeof($logistics) == 0) {
            return new RedirectResponse('/applications');
        }
        if (is_array($logistics)) {$logistics = array_shift($logistics);}

        if ($this->security->getUser()->getOffice()->getId() != $logistics->getOffice()->getId()) {
            return new RedirectResponse('/applications');
        }

        //Получаем родителя
        if (is_numeric($logistics->getParent())) {
            $logistics_ = $logisticsRepository->findBy( array('id' => $logistics->getParent()) );
            if (is_array($logistics_)) {$logistics_ = array_shift($logistics_);}
            $objParent = $logistics_;
        } else {
            $objParent = null;    
        }

        //Получаем материалы
        $logisticsMaterials = $logisticsMaterialsRepository->findBy( array('logistic' => $parent), array('id' => 'ASC') );
        if (sizeof($logisticsMaterials) == 0) {
            return new RedirectResponse('/applications');
        }

        //Для уточнения количества материалов получаем список дочерних отгрузок
        $children = $logisticsRepository->findBy( array('parent' => $parent) );
        $childIds = []; foreach ($children as $child) {$childIds[] = $child->getId();} unset($children);

        $arrMaterials = $logisticsMaterialsRepository->createQueryBuilder('lm')
        ->where('lm.logistic IN (:ids)')
        ->setParameter('ids', $childIds)
        ->getQuery()
        ->getResult();

        $materialsMeta = [];
        foreach ($arrMaterials as $material) {
            //Смотрим, есть ли такой материал в массиве
            $exist = false;
            for ($i=0; $i<sizeof($materialsMeta); $i++) {
                if ($materialsMeta[$i]->id == $material->getMaterial()->getId()) {
                    $materialsMeta[$i]->amount += $material->getAmount();
                    $exist = true;
                    break;
                }
            }
            if (!$exist) {
                $tmp = new \stdClass;
                $tmp->id = $material->getMaterial()->getId();
                $tmp->amount = $material->getAmount();
                $materialsMeta[] = $tmp;
                unset($tmp);
            }
        }
        unset($arrMaterials);

        //Уточнияем количество материалов (отнимаем от материала количество из дочерних отгрузок)
        for ($i=0; $i<sizeof($logisticsMaterials); $i++) {
            //Проверяем, есть ли материал в дочерних отгрузках
            for ($j=0; $j<sizeof($materialsMeta); $j++) {
                if ($materialsMeta[$j]->id == $logisticsMaterials[$i]->getMaterial()->getId()) {
                    $logisticsMaterials[$i]->setAmount($logisticsMaterials[$i]->getAmount() - $materialsMeta[$j]->amount);
                    break;
                }
            }
        }

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications';
        $breadcrumbs[0]->title = 'Активные заявки';
        $breadcrumbs[1] = new \stdClass();
        $breadcrumbs[1]->href = '/applications/logistics/view?id='.$parent;
        $breadcrumbs[1]->title = 'Просмотр логистической информации';
        $breadcrumbs[2] = new \stdClass();
        $breadcrumbs[2]->href = '/applications/logistics/add-info/?parent='.$parent;
        $breadcrumbs[2]->title = 'Добавление логистической информации';

        $params['breadcrumbs'] = $breadcrumbs;
        $params['title'] = 'Добавление логистической информации';
        $params['parent'] = $objParent;
        $params['logmaterials'] = $logisticsMaterials;
        $params['logistics'] = $logistics;
        $params['close'] = '/applications/logistics/view?id='.$parent;
        $params['offices'] = $officesRepository->findAll();

        return $this->render('logistics/add.html.twig', $params);
    }

    /**
     * Удаление логистической информации
     * @Route("/applications/logistics/delete-info", methods={"POST"})
     * @IsGranted("ROLE_LOGISTICS")
     */
    public function logisticsDelInfoForm(
        Request $request, 
        BillsMaterialsRepository $billsMaterialsRepository,
        LogisticsRepository $logisticsRepository,
        LogisticsMaterialsRepository $logisticsMaterialsRepository,
        PhotosRepository $photosRepository
    ): JsonResponse
    {
        $id = $request->request->get('id');
        if ($id === null || empty($id) || !is_numeric($id)) {
            return new RedirectResponse('/applications');
        }

        //Получаем материалы в логистике, изменяем количество отгруженных
        $logisticsMaterials = $logisticsMaterialsRepository->findBy( array('logistic' => $id) );
        foreach ($logisticsMaterials as $logisticsMaterial) {
            $amount = $logisticsMaterial->getAmount();
            $material = $logisticsMaterial->getMaterial();
            $bill = $logisticsMaterial->getLogistics()->getBill();

            if ($bill) {
                $billMaterial = $billsMaterialsRepository->findBy(array('bill' => $bill->getId(), 'material' => $material->getId()));
                if (is_array($billMaterial)) {$billMaterial = array_shift($billMaterial);}

                $billMaterial->setRecieved($billMaterial->getRecieved() - $amount);
                $this->entityManager->persist($billMaterial);
                $this->entityManager->flush();
            }
        }

        //Получаем логистику
        $logistics = $logisticsRepository->findBy( array('id' => $id) );
        if (sizeof($logistics) == 0) {
            return new RedirectResponse('/applications');
        }
        if (is_array($logistics)) {$logistics = array_shift($logistics);}

        if ($this->security->getUser()->getId() != $logistics->getUser()->getId()) {
            return new RedirectResponse('/applications');
        }

        //Удаляем логистику
        $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

        try {
            //Удаляем файлы и фотографии
            $photos = $photosRepository->findBy( array('logistic' => $logistics->getId()) );
            foreach ($photos as $file) {
                if (file_exists($this->getParameter('photos_directory').'/'.$file->getPath())) {
                    unlink($this->getParameter('photos_directory').'/'.$file->getPath());
                    rmdir(dirname($this->getParameter('photos_directory').'/'.$file->getPath()));
                }

                $this->entityManager->remove($file);
                $this->entityManager->flush();
            }

            unset($photos);

            $this->entityManager->remove($logistics);
            $this->entityManager->flush();

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
     * Добавление логистической информации (принимает данные из формы)
     * @Route("/applications/logistics/add-info", methods={"POST"})
     * @IsGranted("ROLE_LOGISTICS")
     */
    public function logisticsAddInfo(
        Request $request,
        LogisticsRepository $logisticsRepository,
        LogisticsMaterialsRepository $logisticsMaterialsRepository,
        MaterialsRepository $materialsRepository,
        OfficesRepository $officesRepository,
        PhotosRepository $photosRepository
    ): JsonResponse
    {
        $result = [];

        $submittedToken = $request->request->get('token');

        if ($this->isCsrfTokenValid('log-add', $submittedToken)) {   
            try {
                $this->entityManager->getConnection()->beginTransaction(); //Начинаем транзакцию

                $materials = $request->request->get('material');
                $amounts = $request->request->get('amount');

                // $params['dateReciept'] = $request->request->get('dateReciept');
                $params['dateShip'] = $request->request->get('dateShip');
                $params['userOfficeShip'] = $request->request->get('userOfficeShip');
                $params['way'] = $request->request->get('way');
                $params['track'] = $request->request->get('track');
                $params['photos'] = $request->request->get('photos');
            
                //Проверяем по параметрам откуда был вызов, из формы просмотра счета или из формы выбора материалов при приходе
                if ($request->request->get('type') === null) {
                    //Вызов из формы выбора материалов при перемещении
                    //Ожидаем массив id логистической информации
                    $resultArray = [];
                    $logisticsIds = $request->request->get('logistic');

                    foreach ($logisticsIds as $logisticId) {
                        $exist = false;
                        foreach ($resultArray as $row) {if ($row['logisticId'] == $logisticId) {$exist = true; break;}}

                        if (!$exist) {
                            //Добавляем
                            $tmp = [];
                            $tmp['logisticId'] = $logisticId;
                            $tmp['materials'] = [];
                            $tmp['amounts'] = [];

                            for ($i=0; $i < sizeof($logisticsIds); $i++) {
                                if ($logisticsIds[$i] == $logisticId) {
                                    $tmp['materials'][] = $materials[$i];
                                    $tmp['amounts'][] = $amounts[$i];
                                }
                            }

                            $resultArray[] = $tmp; unset($tmp);
                        }
                    }

                    $type = 1; //Отправка
                    $logistics = []; //Возвращаемое значение при успешном выполнении
                    foreach ($resultArray as $logistic) {
                        $logistics[] = $this->sendMaterials($logistic['materials'], $logistic['amounts'], $type, $logistic['logisticId'], $params);
                    }
                    $logistics = json_encode($logistics);
                } else {
                    //Вызов из формы добавление логистики
                    $type = $request->request->get('type');
                    $parent = $request->request->get('parent'); if (!is_numeric($parent)) {$parent = null;}

                    $this->sendMaterials($materials, $amounts, $type, $parent, $params);

                    $logistics = ''; //Возвращаемое значение при успешном выполнении
                }
                
                $this->entityManager->getConnection()->commit();

                $result[] = 1;
                $result[] = $logistics;
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
     * Отгрузка материалов
     */
    private function sendMaterials($materials, $amounts, $type, $parent, $params) {
        //Получаем необходимые репозитории
        $logisticsRepository = $this->entityManager->getRepository(Logistics::class);
        $logisticsMaterialsRepository = $this->entityManager->getRepository(LogisticsMaterials::class);
        $materialsRepository = $this->entityManager->getRepository(Materials::class);
        $officesRepository = $this->entityManager->getRepository(Offices::class);
        $photosRepository = $this->entityManager->getRepository(Photos::class);

        if ($materials !== null) {
            $objMaterials = [];
            for ($i=0; $i<sizeof($materials); $i++) {
                //Получаем строку в BillsMaterials
                $objMaterial = $materialsRepository->findBy(array('id' => $materials[$i]));
                if (is_array($objMaterial)) {$objMaterial = array_shift($objMaterial);}
                $objMaterials[] = $objMaterial;
                unset($objMaterial);
            }

            //Для уточнения количества материалов получаем список дочерних отгрузок
            $children = $logisticsRepository->findBy( array('parent' => $parent) );
            $childIds = []; foreach ($children as $child) {$childIds[] = $child->getId();} unset($children);

            $arrMaterials = $logisticsMaterialsRepository->createQueryBuilder('lm')
            ->where('lm.logistic IN (:ids)')
            ->setParameter('ids', $childIds)
            ->getQuery()
            ->getResult();

            $materialsMeta = [];
            foreach ($arrMaterials as $material) {
                //Смотрим, есть ли такой материал в массиве
                $exist = false;
                for ($i=0; $i<sizeof($materialsMeta); $i++) {
                    if ($materialsMeta[$i]->id == $material->getMaterial()->getId()) {
                        $materialsMeta[$i]->amount += $material->getAmount();
                        $exist = true;
                        break;
                    }
                }
                if (!$exist) {
                    $tmp = new \stdClass;
                    $tmp->id = $material->getMaterial()->getId();
                    $tmp->amount = $material->getAmount();
                    $materialsMeta[] = $tmp;
                    unset($tmp);
                }
            }
            unset($arrMaterials);

            //Дополняем массив текущими материалами
            for ($j=0; $j<sizeof($materials); $j++) {
                //Смотрим, есть ли такой материал в массиве
                $exist = false;
                for ($i=0; $i<sizeof($materialsMeta); $i++) {
                    if ($materialsMeta[$i]->id == $materials[$j]) {
                        $materialsMeta[$i]->amount += $amounts[$j];
                        $exist = true;
                        break;
                    }
                }
                if (!$exist) {
                    $tmp = new \stdClass;
                    $tmp->id = $materials[$j];
                    $tmp->amount = $amounts[$j];
                    $materialsMeta[] = $tmp;
                    unset($tmp);
                }
            }

            /*
             * Итого в массиве $materialsMeta информация о всех подчиненных материалах, включая текущую отгрузку
             * Чтобы понять "закрыта" ли отгрузка, получаем исзодные материалы по ней и сравниваем
             */
            $canSetDone = true;
            $logisticsMaterials = $logisticsMaterialsRepository->findBy( array('logistic' => $parent) );
            foreach ($logisticsMaterials as $logisticsMaterial) {
                foreach ($materialsMeta as $materialMeta) {
                    if ($materialMeta->id = $logisticsMaterial->getMaterial()->getId()) {
                        if ($logisticsMaterial->getAmount() > $materialMeta->amount) {
                            $canSetDone = false;
                        }

                        break;
                    }
                }
            }

            if ($canSetDone) {
                //Отмечаем отгрузку как "закрытую"
                $logistics = $logisticsRepository->findBy( array('id' => $parent) );
                if (is_array($logistics)) {$logistics = array_shift($logistics);}
                $logistics->setDone(true);
                $this->entityManager->persist($logistics);
                $this->entityManager->flush();
            }

            //Получаем информацию об отгрузке/получению
            if ($type !== null && is_numeric($type)) {
                $objLogistics = new Logistics;

                if ($type == 0) {
                    //Получение
                    $dateOp = new \DateTime();
                    $dateOp->setTimestamp(strtotime($params['dateReciept'].' 00:00:01')); //Дата операции
                    $objOffice = $this->security->getUser()->getOffice(); //Объект - локация пользователя

                    $objLogistics->setDate($dateOp);
                    $objLogistics->setType(0); //Получение
                    $objLogistics->setOffice($objOffice);
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
                }

                $objLogistics->setUser($this->security->getUser());
                $objLogistics->setParent($parent);
                $this->entityManager->persist($objLogistics);

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

        return $objLogistics->getId();
    }

    /**
     * Вывод информации по грузам в пути/наличии
     * @Route("/applications/logistics/", methods={"GET"})
     * @IsGranted("ROLE_LOGISTICS")
     */
    public function logistics(
        Request $request,
        BillsRepository $billsRepository,
        BillsMaterialsRepository $billsMaterialsRepository,
        LogisticsRepository $logisticsRepository,
        LogisticsMaterialsRepository $logisticsMaterialsRepository,
        ProvidersRepository $providersRepository
    ): Response
    {
        //Получаем подразделение
        $office = $request->query->get('office');
        if ($office === null) {
            //Получаем подразделение пользователя
            $office = $this->security->getUser()->getOffice()->getId();
        }

        //Подзапрос возвращает количество дочерних отгрузок
        $childQuery = $logisticsRepository->createQueryBuilder('lm_')
        ->select('COUNT(lm_.id) AS cnt')
        ->where('lm_.parent = lm.id')
        ->getDQL()
        ;

        //Смотрим, есть ли грузы в адрес подразделения
        $incoming = $logisticsRepository->createQueryBuilder('lm')
        ->where('lm.type = 1')
        ->andWhere('lm.office = :office')
        ->andWhere('('.$childQuery.') = 0')
        ->setParameter('office', $office)
        ->getQuery()
        ->getResult()
        ;

        //Обогащаем массив информацией о счетах
        for ($i=0; $i<sizeof($incoming); $i++) {
            $arrMaterials = $logisticsMaterialsRepository->createQueryBuilder('lm')
            ->select('IDENTITY(lm.material) AS material')
            ->where('lm.logistic = :logistic')
            ->setParameter('logistic', $incoming[$i]->getId())
            ->getQuery()
            ->getResult()
            ;

            $materialsIds = [];
            foreach ($arrMaterials as $material) {if (!in_array($material['material'], $materialsIds)) {$materialsIds[] = $material['material'];}} unset($arrMaterials);
            
            //Получаем ID счетов
            $arrBills = $billsMaterialsRepository->createQueryBuilder('bm')
            ->select('IDENTITY(bm.bill) AS bill')
            ->where('bm.material IN (:materials)')
            ->setParameter('materials', $materialsIds)
            ->getQuery()
            ->getResult()
            ;

            $billsIds = [];
            foreach ($arrBills as $bill) {if (!in_array($bill['bill'], $billsIds)) {$billsIds[] = $bill['bill'];}} unset($arrBills);

            //Получаем объекты счетов
            $incoming[$i]->bills = $billsRepository->createQueryBuilder('b')
            ->where('b.id IN (:bills)')
            ->setParameter('bills', $billsIds)
            ->getQuery()
            ->getResult()
            ;

            foreach ($incoming[$i]->bills as $bill) {
                //Подгружаем информацию о поставщике
                $provider = $providersRepository->findBy(array('inn' => $bill->getInn()));
                if (is_array($provider)) {$provider = array_shift($provider);}
                $bill->provider = $provider;
            }

            unset($materialsIds, $billsIds);
        }

        //Смотрим, есть ли грузы, которые числятся в подразделении
        $instock = $logisticsRepository->createQueryBuilder('lm')
        ->where('lm.type = 0')
        ->andWhere('lm.office = :office')
        ->andWhere('lm.done = false')
        ->andWhere('('.$childQuery.') = 0')
        ->setParameter('office', $office)
        ->getQuery()
        ->getResult()
        ;

        //Обогащаем массив информацией о счетах
        for ($i=0; $i<sizeof($instock); $i++) {
            $arrMaterials = $logisticsMaterialsRepository->createQueryBuilder('lm')
            ->select('IDENTITY(lm.material) AS material')
            ->where('lm.logistic = :logistic')
            ->setParameter('logistic', $instock[$i]->getId())
            ->getQuery()
            ->getResult()
            ;

            $materialsIds = [];
            foreach ($arrMaterials as $material) {if (!in_array($material['material'], $materialsIds)) {$materialsIds[] = $material['material'];}} unset($arrMaterials);
            
            //Получаем ID счетов
            $arrBills = $billsMaterialsRepository->createQueryBuilder('bm')
            ->select('IDENTITY(bm.bill) AS bill')
            ->where('bm.material IN (:materials)')
            ->setParameter('materials', $materialsIds)
            ->getQuery()
            ->getResult()
            ;

            $billsIds = [];
            foreach ($arrBills as $bill) {if (!in_array($bill['bill'], $billsIds)) {$billsIds[] = $bill['bill'];}} unset($arrBills);

            //Получаем объекты счетов
            $instock[$i]->bills = $billsRepository->createQueryBuilder('b')
            ->where('b.id IN (:bills)')
            ->setParameter('bills', $billsIds)
            ->getQuery()
            ->getResult()
            ;

            foreach ($instock[$i]->bills as $bill) {
                //Подгружаем информацию о поставщике
                $provider = $providersRepository->findBy(array('inn' => $bill->getInn()));
                if (is_array($provider)) {$provider = array_shift($provider);}
                $bill->provider = $provider;
            }

            unset($materialsIds, $billsIds);
        }

        //Хлебные крошки
        $breadcrumbs = [];
        $breadcrumbs[0] = new \stdClass();
        $breadcrumbs[0]->href = '/applications/logistics/';
        $breadcrumbs[0]->title = 'Просмотр грузов';

        return $this->render('logistics/index.html.twig', [
            'title' => 'Просмотр грузов',
            'incoming' => $incoming,
            'instock' => $instock,
            'breadcrumbs' => $breadcrumbs
        ]);
    }

    /**
     * Получение информации для требований-накладных в старой базе
     * @Route("/applications/logistics/info", methods={"GET"})
     */
    public function getInfo(
        Request $request, 
        ApplicationsRepository $applicationsRepository,
        BillsMaterialsRepository $billsMaterialsRepository,
        BillsRepository $billsRepository,
        LogisticsRepository $logisticsRepository,
    ) {
        $secret = '4c261a55d6d9bf87b77e12292095b184';
        if ($secret != $request->query->get('secret')) {die();}

        //Получаем ID логистики
        $id = $request->query->get('id');

        $logistics = $logisticsRepository->findBy( array('id' => $id) );
        if (sizeof($logistics) == 0 || $logistics === null) {return new RedirectResponse('/applications');}
        if (is_array($logistics)) {$logistics = array_shift($logistics);}

        //Получаем счет
        $objBill = $logistics->getBill();
        if ($objBill === null) {
            $logistics_ = $logisticsRepository->findBy( array('id' => $logistics->getParent()) );
            if (is_array($logistics_)) {$logistics_ = array_shift($logistics_);}
            $objBill = $logistics_->getBill();
        }

        if ($objBill === null) {die();}

        //Нашли счет, получаем материалы
        $applications = [];
        $responsibles = [];
        $billMaterials = $billsMaterialsRepository->findBy(array('bill' => $objBill->getId()));
        foreach ($billMaterials as $billMaterial) {
            $exists = false;
            foreach ($applications as $application) {
                if ($application->getId() == $billMaterial->getMaterial()->getApplication()->getId()) {
                    $exists = true; break;
                }
            }

            if (!$exists) {
                $applications[] = $billMaterial->getMaterial()->getApplication();
                $responsibles[] = $billMaterial->getBill()->getUser();
            }
        }

        if (sizeof($applications) == sizeof($responsibles)) {
            $response = '';
            
            for ($i=0; $i < sizeof($applications); $i++) {
                if (!empty($response)) {$response .= '<br />';}
                $response .= 'Заявка: №'.$applications[$i]->getId();
                if (!empty($applications[$i]->getNumber())) {
                    $response .= '('.$applications[$i]->getNumber().')<br />';
                }
                $response .= 'Отправитель: '.$applications[$i]->getAuthor()->getShortUsername().'<br />';
                $response .= 'Исполнитель: '.$responsibles[$i]->getShortUsername();
            }
        }

        echo $response; die();
    }
}