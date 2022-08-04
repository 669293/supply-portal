<?php

namespace App\Repository;

use App\Entity\BillsMaterials;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method BillsMaterials|null find($id, $lockMode = null, $lockVersion = null)
 * @method BillsMaterials|null findOneBy(array $criteria, array $orderBy = null)
 * @method BillsMaterials[]    findAll()
 * @method BillsMaterials[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BillsMaterialsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BillsMaterials::class);
    }
}
