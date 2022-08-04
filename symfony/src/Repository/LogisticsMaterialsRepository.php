<?php

namespace App\Repository;

use App\Entity\LogisticsMaterials;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method LogisticsMaterials|null find($id, $lockMode = null, $lockVersion = null)
 * @method LogisticsMaterials|null findOneBy(array $criteria, array $orderBy = null)
 * @method LogisticsMaterials[]    findAll()
 * @method LogisticsMaterials[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LogisticsMaterialsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogisticsMaterials::class);
    }
}
