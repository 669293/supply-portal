<?php

namespace App\Repository;

use App\Entity\Applications;
use App\Entity\ApplicationsStatuses;
use App\Entity\Bills;
use App\Entity\BillsMaterials;
use App\Entity\BillsStatuses;
use App\Entity\Files;
use App\Entity\Materials;
use App\Entity\TypesOfEquipment;
use App\Entity\Users;
use App\Entity\StatusesOfApplications;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @method Applications|null find($id, $lockMode = null, $lockVersion = null)
 * @method Applications|null findOneBy(array $criteria, array $orderBy = null)
 * @method Applications[]    findAll()
 * @method Applications[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApplicationsRepository extends ServiceEntityRepository
{
    private $entityManager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, Applications::class);
        $this->entityManager = $entityManager;
    }

    /**
     * @return Applications[] Returns an array of Applications objects
     */
    public function getList($filter)
    {
        if ($filter->isFiltered) {$filter->resultsPerPage = 0; $filter->page = 1;}
        
        $query = $this->createQueryBuilder('a');

        //Фильтр по отправителю
        if ($filter->author != null) {
            $query->andWhere('a.author = :author')->setParameter('author', $filter->author);
        }

        //Фильтр по ответственному
        if ($filter->responsible != null) {
            $query->join('App\Entity\Materials', 'm', 'WITH' ,'m.application=a.id')
            ->andWhere('m.responsible = :responsible')
            ->setParameter('responsible', $filter->responsible)
            ;
        }

        //Фильтр по подразделению
        //Получаем список ответственных в подразделении
        if ($filter->office != null) {
            $responsibles = $this->entityManager->getRepository(Users::class)->createQueryBuilder('u')
            ->select('u.id')
            ->where('u.office = :office')
            ->setParameter('office', $filter->office->getId())
            ->getQuery()
            ->getResult();

            $responsiblesIDs = [];
            foreach ($responsibles as $responsible) {
                $responsiblesIDs[] = $responsible['id'];
            }

            $query->join('App\Entity\Materials', 'm2', 'WITH' ,'m2.application=a.id')
            ->andWhere('m2.responsible IN ('.implode(',', $responsiblesIDs).')')
            ;
        }

        //Подзапрос возвращает ID статуса
        $statusQuery = $this->entityManager->getRepository(ApplicationsStatuses::class)->createQueryBuilder('aps')
        ->select('IDENTITY(aps.status)')
        ->where('aps.application = a.id')
        ->andWhere('aps.datetime = ('.
            $this->entityManager->getRepository(ApplicationsStatuses::class)->createQueryBuilder('aps_')
            ->select('max(aps_.datetime)')
            ->where('aps_.application = a.id')
            ->getDQL()
            .')')
        ->groupBy('aps.status')
        ->getDQL()
        ;
        //Подзапрос возвращает дату/время статуса
        $statusDatetimeQuery = $this->entityManager->getRepository(ApplicationsStatuses::class)->createQueryBuilder('aps__')
        ->select('max(aps__.datetime)')
        ->where('aps__.application = a.id')
        ->getDQL()
        ;

        $query->addSelect(['s.title AS status_title', 's.id AS status_id', 's.classList AS status_class', '('.$statusDatetimeQuery.') AS status_datetime'])
        ->join('App\Entity\StatusesOfApplications', 's', 'WITH' ,'s.id=('.$statusQuery.')')
        ;

        //Фильтр по статусу
        if ($filter->done) {
            //Выполненные заявки
            $query
            ->andWhere('(s.id = 3 OR s.id = 5)')
            ;
        } else {
            //Активные заявки
            $query
            ->andWhere('s.id != 3') //Выполнена
            ->andWhere('s.id != 5') //Отменена
            ;
        }

        if ($filter->status != null) {
            $query->andWhere('s.id = :status')
            ->setParameter('status', $filter->status->getId())
            ;
        }

        //Фильтр по названию
        if ($filter->title != null) {
            $query->andWhere('LOWER(a.title) LIKE LOWER(:title)')
            ->setParameter('title', '%'.mb_strtolower($filter->title).'%')
            ;
        }

        //Фильтр по дате поступления
        if ($filter->dateFrom != null) {
            $query->andWhere('a.date_create >= :dateFrom')
            ->setParameter('dateFrom', $filter->dateFrom->format('Y-m-d H:i:s'))
            ;
        }

        if ($filter->dateTo != null) {
            $query->andWhere('a.date_create <= :dateTo')
            ->setParameter('dateTo', $filter->dateTo->format('Y-m-d H:i:s'))
            ;
        }

        //Фильтр по дате закрытия
        if ($filter->dateCloseFrom != null) {
            $query->andWhere('a.date_create >= :dateFrom')
            ->setParameter('dateFrom', $filter->dateCloseFrom->format('Y-m-d H:i:s'))
            ;
        }

        if ($filter->dateCloseTo != null) {
            $query->andWhere('a.date_create <= :dateTo')
            ->setParameter('dateTo', $filter->dateCloseTo->format('Y-m-d H:i:s'))
            ;
        }

        //Фильтр по годовым заявкам
        if ($filter->year != -1) {
            if ($filter->year == 1) {
                $query->andWhere('a.isYear = TRUE');
            } else {
                $query->andWhere('a.isYear = FALSE');
            }
        }

        //Сортировка
        if ($filter->orderBy == 'e.username') {$query->innerJoin('a.author', 'e');}
        $query->orderBy($filter->orderBy, $filter->sort);

        //Подключаем постраничный вывод
        if ($filter->resultsPerPage > 0 && $filter->office == null) {
            $paginator = new Paginator($query);
            $totalItems = (int)count($paginator);
            $pagesCount = (int)ceil($totalItems / $filter->resultsPerPage);

            $applicationsArray = $paginator
            ->getQuery()
            ->setFirstResult(($filter->page - 1) * $filter->resultsPerPage)
            ->setMaxResults($filter->resultsPerPage)->getResult(); 
        } else {
            $applicationsArray = $query->getQuery()->getResult();
            $pagesCount = 1;
        }

        //Подгружаем список ответственных срочность и общее количество
        for ($i=0; $i<sizeof($applicationsArray); $i++) {            
            $responsibleQuery = $this->entityManager->getRepository(Materials::class)->createQueryBuilder('m')
            ->select(['IDENTITY(m.responsible) AS id', 'm.urgency AS urgency', 'm.isDeleted AS is_deleted', 'm.impossible AS impossible'])
            ->where('m.application = :app')
            ->setParameter('app', $applicationsArray[$i][0]->getId())
            ;

            $rows = $responsibleQuery->getQuery()->getResult();

            $responsibles_id = [];

            $urgency = false; //Срочность
            $deleted_rows = 0; //Количество удаленных строк
            $impossible = 0; //Количество материалов невозможных к поставке
            for ($j=0; $j<sizeof($rows); $j++) {
                $exists = false;
                foreach ($responsibles_id as $responsible_id) {
                    if ($responsible_id == $rows[$j]['id']) {$exists = true; break;}
                }

                if (!$exists && $rows[$j]['id'] !== null) {$responsibles_id[] = $rows[$j]['id'];}
                if ($rows[$j]['urgency']) {
                    if (!$rows[$j]['is_deleted']) {
                        $urgency = true;
                    }
                }

                if ($rows[$j]['is_deleted']) {$deleted_rows++;} //Увеличиваем количество удаленных строк
                if ($rows[$j]['impossible']) {$impossible++;} //Увеличиваем количество материалов невозможных к поставке
            }

            //Ответственные
            $responsibles = [];

            if (sizeof($responsibles_id) > 0) {
                //Подключаем список пользователей
                $usersQuery = $this->entityManager->getRepository(Users::class)->createQueryBuilder('u')
                ->where('u.id IN ('.implode(',', $responsibles_id).')')
                ->orderBy('u.username', 'ASC')
                ;

                $users = $usersQuery->getQuery()->getResult();
            
                foreach ($users as $user) {
                    $responsibles[] = [
                        'id' => $user->getId(),
                        'username' => $user->getShortUsername()
                    ];
                }
            }

            //Количество позиций в заявке
            $applicationsArray[$i]['materials_count'] = sizeof($rows);
            $applicationsArray[$i]['materials_deleted'] = $deleted_rows;
            $applicationsArray[$i]['materials_impossible'] = $impossible;

            $applicationsArray[$i]['responsibles'] = $responsibles;
            $applicationsArray[$i]['urgency'] = $urgency;
            $applicationsArray[$i]['pages_count'] = $pagesCount;

            unset($userClass, $responsibles, $responsibles_id);

            //Подгружаем список файлов
            $files = [];
            $arrFiles = $this->entityManager->getRepository(Files::class)->findBy(array('application' => $applicationsArray[$i][0]->getId()));
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
            $applicationsArray[$i]['files'] = $files;

            //Подгружаем список счетов
            $bills = [];
            $arrBills = [];
            $arrMaterials = $this->entityManager->getRepository(Materials::class)->findBy(array('application' => $applicationsArray[$i][0]->getId()));
            foreach ($arrMaterials as $material) {
                $arrBillsMaterials = $this->entityManager->getRepository(BillsMaterials::class)->findBy(array('material' => $material->getId()));
                foreach ($arrBillsMaterials as $billMaterial) {
                    $arrBills[] = $billMaterial->getBill()->getId();
                }
            }

            $arrBills = array_unique($arrBills);
            foreach ($arrBills as $billId) {
                $bill = $this->entityManager->getRepository(Bills::class)->findBy(array('id' => $billId))[0];

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
                $status = $this->entityManager->getRepository(BillsStatuses::class)->findBy(array('bill' => $billId), array('datetime' => 'DESC'))[0];

                $params['class'] = $status->getStatus()->getClassBtn(); 
                $params['icon'] = $status->getStatus()->getIcon();
                $params['status'] = $status->getStatus()->getTitle();
                $params['style'] = $status->getStatus()->getClassText();

                $bills[] = $params; unset($params);
            }

            unset($arrBills, $arrMaterials, $arrBillsMaterials);
            $applicationsArray[$i]['bills'] = $bills;
        }

        return $applicationsArray;
    }

    public function getResponsibles($application_id): array
    {
        $responsibleQuery = $this->entityManager->getRepository(Materials::class)->createQueryBuilder('m')
        ->select(['IDENTITY(m.responsible) AS id', 'm.urgency AS urgency', 'm.isDeleted AS is_deleted'])
        ->where('m.application = :app')
        ->setParameter('app', $application_id)
        ;

        $rows = $responsibleQuery->getQuery()->getResult();
        
        $responsibles_id = [];

        for ($j=0; $j<sizeof($rows); $j++) {
            $exists = false;
            foreach ($responsibles_id as $responsible_id) {
                if ($responsible_id == $rows[$j]['id']) {$exists = true; break;}
            }

            if (!$exists && $rows[$j]['id'] !== null) {$responsibles_id[] = $rows[$j]['id'];}
        }

        if (sizeof($responsibles_id) == 0) {return [];}

        //Подключаем список пользователей
        $usersQuery = $this->entityManager->getRepository(Users::class)->createQueryBuilder('u')
        ->where('u.id IN ('.implode(',', $responsibles_id).')')
        ->orderBy('u.username', 'ASC')
        ;

        $users = $usersQuery->getQuery()->getResult();

        return $users;
    }

    public function getUrgency($application_id): bool
    {
        $materialsQuery = $this->entityManager->getRepository(Materials::class)->createQueryBuilder('m')
        ->select(['m.urgency AS urgency', 'm.isDeleted AS is_deleted'])
        ->where('m.application = :app')
        ->setParameter('app', $application_id)
        ;

        $rows = $materialsQuery->getQuery()->getResult();

        $urgency = false; //Срочность
        for ($j=0; $j<sizeof($rows); $j++) {

            if ($rows[$j]['urgency']) {
                if (!$rows[$j]['is_deleted']) {
                    $urgency = true;
                    break;
                }
            }
        }

        return $urgency;
    }

    public function getStatus($application_id): int
    {
        $status = $this->entityManager->getRepository(ApplicationsStatuses::class)->createQueryBuilder('aps')
        ->select('IDENTITY(aps.status)')
        ->where('aps.application = :aid')
        ->andWhere('aps.datetime = ('.
            $this->entityManager->getRepository(ApplicationsStatuses::class)->createQueryBuilder('aps_')
            ->select('max(aps_.datetime)')
            ->where('aps_.application = :aid')
            ->getDQL()
            .')')
        ->setParameter('aid', $application_id)
        ->getQuery()
        ->getResult();

        if (is_array($status)) {
            if (sizeof($status) == 0) {return false;}
            $status = array_shift($status)[1];

            return (int)$status;
        }
    }

    public function findLike($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('LOWER(a.title) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findLikeComment($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('LOWER(a.comment) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findLikeNumber($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('LOWER(a.number) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}