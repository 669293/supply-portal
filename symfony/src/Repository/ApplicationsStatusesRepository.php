<?php

namespace App\Repository;

use App\Entity\ApplicationsStatuses;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ApplicationsStatuses|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApplicationsStatuses|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApplicationsStatuses[]    findAll()
 * @method ApplicationsStatuses[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApplicationsStatusesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApplicationsStatuses::class);
    }
}
