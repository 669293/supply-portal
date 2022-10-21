<?php

namespace App\Repository;

use App\Entity\StockFiles;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method StockFiles|null find($id, $lockMode = null, $lockVersion = null)
 * @method StockFiles|null findOneBy(array $criteria, array $orderBy = null)
 * @method StockFiles[]    findAll()
 * @method StockFiles[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockFilesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockFiles::class);
    }
}
