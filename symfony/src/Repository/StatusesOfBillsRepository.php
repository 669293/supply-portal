<?php

namespace App\Repository;

use App\Entity\StatusesOfBills;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method StatusesOfBills|null find($id, $lockMode = null, $lockVersion = null)
 * @method StatusesOfBills|null findOneBy(array $criteria, array $orderBy = null)
 * @method StatusesOfBills[]    findAll()
 * @method StatusesOfBills[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatusesOfBillsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StatusesOfBills::class);
    }
}
