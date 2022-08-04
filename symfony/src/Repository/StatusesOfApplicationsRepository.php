<?php

namespace App\Repository;

use App\Entity\StatusesOfApplications;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method StatusesOfApplications|null find($id, $lockMode = null, $lockVersion = null)
 * @method StatusesOfApplications|null findOneBy(array $criteria, array $orderBy = null)
 * @method StatusesOfApplications[]    findAll()
 * @method StatusesOfApplications[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatusesOfApplicationsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StatusesOfApplications::class);
    }

    public function getStatusesForActiveFilter()
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.id != 3')
            ->andWhere('s.id != 5')
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }

    public function getStatusesForDoneFilter()
    {
        return $this->createQueryBuilder('s')
            ->andWhere('(s.id = 3 OR s.id = 5)')
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }
}
