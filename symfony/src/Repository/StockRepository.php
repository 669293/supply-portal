<?php

namespace App\Repository;

use App\Entity\Stock;
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
     * @return Stock[] Returns an array of Materials objects
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
}