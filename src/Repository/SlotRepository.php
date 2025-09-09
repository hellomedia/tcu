<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Date;
use App\Entity\Slot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Slot>
 */
class SlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Slot::class);
    }

    //    /**
    //     * @return Slot[] Returns an array of Slot objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Slot
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Why the explicit join on Booking::class ?
     * A single-valued association path expression to an inverse side
     * is not supported in DQL queries. Instead of "s.booking" use an explicit join.
     * 
     * 
     */
    public function getFutureAvailableSlotsQueryBuilder(Date $date): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM ' . Booking::class . ' b WHERE b.slot = s
            )')
            ->innerJoin('s.date', 'd')->addSelect('d')
            ->andWhere('s.date = :date')
            ->setParameter('date', $date)
            ->addOrderBy('s.startsAt', 'ASC')
        ;
    }
}
