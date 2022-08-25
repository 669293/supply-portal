<?php

namespace App\Repository;

use App\Entity\ResponsibleLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ResponsibleLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method ResponsibleLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method ResponsibleLog[]    findAll()
 * @method ResponsibleLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ResponsibleLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResponsibleLog::class);
    }
}
