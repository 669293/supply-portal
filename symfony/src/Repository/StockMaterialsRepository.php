<?php

namespace App\Repository;

use App\Entity\StockMaterials;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @method StockMaterials|null find($id, $lockMode = null, $lockVersion = null)
 * @method StockMaterials|null findOneBy(array $criteria, array $orderBy = null)
 * @method StockMaterials[]    findAll()
 * @method StockMaterials[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockMaterialsRepository extends ServiceEntityRepository
{
    private $entityManager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, StockMaterials::class);
        $this->entityManager = $entityManager;
    }

    /**
     * @return StockMaterials[] Returns an array of StockMaterials objects
     */
    public function findLike($value, $orderBy = 'sm.title', $orderDirection = 'ASC')
    {
        return $this->createQueryBuilder('sm')
            ->andWhere('LOWER(sm.title) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy($orderBy, $orderDirection)
            ->getQuery()
            ->getResult()
        ;
    }
}