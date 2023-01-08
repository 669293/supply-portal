<?php

namespace App\Repository;

use App\Entity\StockTransport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @method StockTransport|null find($id, $lockMode = null, $lockVersion = null)
 * @method StockTransport|null findOneBy(array $criteria, array $orderBy = null)
 * @method StockTransport[]    findAll()
 * @method StockTransport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StockTransportRepository extends ServiceEntityRepository
{
    private $entityManager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, StockTransport::class);
        $this->entityManager = $entityManager;
    }
}