<?php

namespace App\Repository;

use App\Entity\Providers;
use App\Entity\Stock;
use App\Entity\StockFiles;
use App\Entity\StockStockMaterials;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @method Stock|null find($id, $lockMode = null, $lockVersion = null)
 * @method Stock|null findOneBy(array $criteria, array $orderBy = null)
 * @method Stock[]    findAll()
 * @method Stock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockRepository extends ServiceEntityRepository
{
    private $entityManager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, Stock::class);
        $this->entityManager = $entityManager;
    }

    /**
     * @return Stock[] Returns an array of Stock objects
     */
    public function findLike($value, $orderBy = 's.way', $orderDirection = 'ASC')
    {
        return $this->createQueryBuilder('s')
            ->andWhere('LOWER(s.way) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy($orderBy, $orderDirection)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Stock[] Returns an array of Stock objects
     */
    public function getList($filter)
    {
        if ($filter->isFiltered) {$filter->resultsPerPage = 0; $filter->page = 1;}
        
        $query = $this->createQueryBuilder('s');

        //Фильтр по поставщику
        if ($filter->provider != null) {
            $query->andWhere('s.provider = :provider')
            ->setParameter('provider', $filter->provider)
            ;
        }

        //Фильтр по названию
        if ($filter->title != null) {
            $query->andWhere('LOWER(s.comment) LIKE LOWER(:title)')
            ->setParameter('title', '%'.mb_strtolower($filter->title).'%')
            ;
        }

        //Фильтр по дате поступления
        if ($filter->dateFrom != null) {
            $query->andWhere('s.date >= :dateFrom')
            ->setParameter('dateFrom', $filter->dateFrom->format('Y-m-d H:i:s'))
            ;
        }

        if ($filter->dateTo != null) {
            $query->andWhere('s.date <= :dateTo')
            ->setParameter('dateTo', $filter->dateTo->format('Y-m-d H:i:s'))
            ;
        }

        //Сортировка
        if ($filter->orderBy == 'e.username') {$query->innerJoin('a.author', 'e');}
        $query->orderBy($filter->orderBy, $filter->sort);

        //Подключаем постраничный вывод
        if ($filter->resultsPerPage > 0) {
            $paginator = new Paginator($query);
            $totalItems = (int)count($paginator);
            $pagesCount = (int)ceil($totalItems / $filter->resultsPerPage);

            $stockArray = $paginator
            ->getQuery()
            ->setFirstResult(($filter->page - 1) * $filter->resultsPerPage)
            ->setMaxResults($filter->resultsPerPage)->getResult(); 
        } else {
            $stockArray = $query->getQuery()->getResult();
            $pagesCount = 1;
        }

        $stockArray_ = $stockArray;
        $stockArray = [];
        foreach ($stockArray_ as $document) {
            $tmp = new \stdClass();
            $tmp->obj = $document;
            $tmp->sum = 0;

            //Получаем сумму материалов
            $objSSMs = $this->entityManager->getRepository(StockStockMaterials::class)->findBy( array('stock' => $document->getId()) );
            foreach ($objSSMs as $objSSM) {
                $tmp->sum += $objSSM->getTotal();
            }

            //Получаем поставщика
            $tmp->provider = 0;
            if ($document->getProvider() && !empty($document->getProvider())) {
                $tmp->provider = 'ИНН: '.$document->getProvider();
                $objProvider = $this->entityManager->getRepository(Providers::class)->findBy(array('inn' => $document->getProvider()));
                if ($objProvider) {
                    if (is_array($objProvider)) {$objProvider = array_shift($objProvider);}
                    $tmp->provider = $objProvider->getTitle().' ('.$tmp->provider .')';
                }
            }

            $stockArray[] = $tmp;
            unset($tmp);
        }

        //Подгружаем список ответственных срочность и общее количество
        for ($i=0; $i<sizeof($stockArray); $i++) {            
            //Подгружаем список файлов
            $files = [];
            $arrFiles = $this->entityManager->getRepository(StockFiles::class)->findBy(array('stock' => $stockArray[$i]->obj->getId()));
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
            $stockArray[$i]->files = $files;
        }

        return $stockArray;
    }

    public function findLikeComment($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('LOWER(s.comment) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}