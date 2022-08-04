<?php

namespace App\Repository;

use App\Entity\Logistics;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Logistics|null find($id, $lockMode = null, $lockVersion = null)
 * @method Logistics|null findOneBy(array $criteria, array $orderBy = null)
 * @method Logistics[]    findAll()
 * @method Logistics[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LogisticsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Logistics::class);
    }

    // /**
    //  * @return Logistics[] Returns an array of Logistics objects
    //  */
    public function findWayLike($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('LOWER(l.way) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('l.way', 'ASC')
            ->setMaxResults(10)
            ->distinct()
            ->getQuery()
            ->getResult()
        ;
    }

    // /**
    //  * @return Logistics[] Returns an array of Logistics objects
    //  */
    public function findTrackLike($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('LOWER(l.track) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('l.track', 'ASC')
            ->setMaxResults(10)
            ->distinct()
            ->getQuery()
            ->getResult()
        ;
    }
}
