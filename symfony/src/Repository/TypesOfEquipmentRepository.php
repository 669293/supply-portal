<?php

namespace App\Repository;

use App\Entity\TypesOfEquipment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method TypesOfEquipment|null find($id, $lockMode = null, $lockVersion = null)
 * @method TypesOfEquipment|null findOneBy(array $criteria, array $orderBy = null)
 * @method TypesOfEquipment[]    findAll()
 * @method TypesOfEquipment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @method TypesOfEquipment[]    findLike($value)
 */
class TypesOfEquipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypesOfEquipment::class);
    }

    // /**
    //  * @return TypesOfEquipment[] Returns an array of TypesOfEquipment objects
    //  */
    public function findLike($value, $orderBy = 't.title', $orderDirection = 'ASC')
    {
        return $this->createQueryBuilder('t')
            ->andWhere('LOWER(t.title) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy($orderBy, $orderDirection)
            ->setMaxResults(10)
            ->distinct()
            ->getQuery()
            ->getResult()
        ;
    }
}
