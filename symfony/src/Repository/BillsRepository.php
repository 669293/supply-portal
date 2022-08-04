<?php

namespace App\Repository;

use App\Entity\Bills;
use App\Entity\BillsStatuses;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Bills|null find($id, $lockMode = null, $lockVersion = null)
 * @method Bills|null findOneBy(array $criteria, array $orderBy = null)
 * @method Bills[]    findAll()
 * @method Bills[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BillsRepository extends ServiceEntityRepository
{
    private $entityManager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, Bills::class);
        $this->entityManager = $entityManager;
    }

    public function getStatus($bill_id): int
    {
        $status = $this->entityManager->getRepository(BillsStatuses::class)->createQueryBuilder('bs')
        ->select('IDENTITY(bs.status)')
        ->where('bs.bill = :bid')
        ->andWhere('bs.datetime = ('.
            $this->entityManager->getRepository(BillsStatuses::class)->createQueryBuilder('bs_')
            ->select('max(bs_.datetime)')
            ->where('bs_.bill = :bid')
            ->getDQL()
            .')')
        ->setParameter('bid', $bill_id)
        ->getQuery()
        ->getResult();

        if (is_array($status)) {
            if (sizeof($status) == 0) {return false;}
            $status = array_shift($status)[1];

            return (int)$status;
        }
    }

    public function findLikeNum($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('LOWER(b.num) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findLikeInn($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('LOWER(b.inn) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findLikeComment($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('LOWER(b.comment) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findLikeNote($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('LOWER(b.note) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findLikePath($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('LOWER(b.path) LIKE LOWER(:val)')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
