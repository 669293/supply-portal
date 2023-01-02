<?php

namespace App\Repository;

use App\Entity\StockApplicationsMaterials;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @method StockApplicationsMaterials|null find($id, $lockMode = null, $lockVersion = null)
 * @method StockApplicationsMaterials|null findOneBy(array $criteria, array $orderBy = null)
 * @method StockApplicationsMaterials[]    findAll()
 * @method StockApplicationsMaterials[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockApplicationsMaterialsRepository extends ServiceEntityRepository
{
    private $entityManager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, StockApplicationsMaterials::class);
        $this->entityManager = $entityManager;
    }
}