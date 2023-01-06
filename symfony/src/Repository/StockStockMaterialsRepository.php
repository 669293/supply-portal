<?php

namespace App\Repository;

use App\Entity\StockStockMaterials;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @method StockStockMaterials|null find($id, $lockMode = null, $lockVersion = null)
 * @method StockStockMaterials|null findOneBy(array $criteria, array $orderBy = null)
 * @method StockStockMaterials[]    findAll()
 * @method StockStockMaterials[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockStockMaterialsRepository extends ServiceEntityRepository
{
    private $entityManager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, StockStockMaterials::class);
        $this->entityManager = $entityManager;
    }
}