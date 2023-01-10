<?php

namespace App\Repository;

use App\Entity\StockBalance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @method StockBalance|null find($id, $lockMode = null, $lockVersion = null)
 * @method StockBalance|null findOneBy(array $criteria, array $orderBy = null)
 * @method StockBalance[]    findAll()
 * @method StockBalance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockBalanceRepository extends ServiceEntityRepository
{
    private $entityManager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, StockBalance::class);
        $this->entityManager = $entityManager;
    }
}