<?php

namespace App\Repository;

use App\Entity\Units;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Units|null find($id, $lockMode = null, $lockVersion = null)
 * @method Units|null findOneBy(array $criteria, array $orderBy = null)
 * @method Units[]    findAll()
 * @method Units[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UnitsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Units::class);
    }

    public function findAll()
    {
        return $this->findBy(array(), array('title' => 'ASC'));
    }
}
