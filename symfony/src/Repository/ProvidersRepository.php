<?php

namespace App\Repository;

use App\Entity\Providers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Providers|null find($id, $lockMode = null, $lockVersion = null)
 * @method Providers|null findOneBy(array $criteria, array $orderBy = null)
 * @method Providers[]    findAll()
 * @method Providers[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProvidersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Providers::class);
    }

    /**
     * @return Providers[] Returns an array of Providers objects
     */
    public function findLike($value, $orderBy = 'p.title', $orderDirection = 'ASC')
    {
        return $this->createQueryBuilder('p')
            ->andWhere('LOWER(p.title) LIKE LOWER(:val) or LOWER(p.inn) LIKE LOWER(:val)')
            ->setParameter('val', '%'.mb_strtolower($value).'%')
            ->orderBy($orderBy, $orderDirection)
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
}