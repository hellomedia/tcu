<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Court;
use App\Entity\Date;
use App\Entity\Practice;
use App\Entity\Slot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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
     * Slots of these courts, between these dates (included), lying entirely within the time window, with their booking
     *
     * @param Court[] $courts
     * @return Slot[]
     */
    public function findInWindow(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $courts, \DateTimeImmutable $startTime, \DateTimeImmutable $endTime): array
    {
        if (!$courts) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->innerJoin('s.date', 'd')->addSelect('d')
            ->leftJoin('s.booking', 'b')->addSelect('b')
            ->andWhere('d.date BETWEEN :startDate AND :endDate')
            ->andWhere('s.court IN (:courts)')
            ->andWhere('s.startsAt >= :startTime')
            ->andWhere('s.endsAt <= :endTime')
            ->setParameter('startDate', $startDate, Types::DATE_IMMUTABLE)
            ->setParameter('endDate', $endDate, Types::DATE_IMMUTABLE)
            ->setParameter('courts', $courts)
            ->setParameter('startTime', $startTime, Types::TIME_IMMUTABLE)
            ->setParameter('endTime', $endTime, Types::TIME_IMMUTABLE)
            ->addOrderBy('d.date', 'ASC')
            ->addOrderBy('s.startsAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * All slots of a date, with their booking (match, players) : for the time x court grid
     *
     * @return Slot[]
     */
    public function findByDateWithBookings(Date $date): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.court', 'c')->addSelect('c')
            ->leftJoin('s.booking', 'b')->addSelect('b')
            ->leftJoin('b.match', 'm')->addSelect('m')
            ->leftJoin('m.participants', 'part')->addSelect('part')
            ->leftJoin('part.player', 'player')->addSelect('player')
            ->leftJoin('b.practice', 'pr')->addSelect('pr')
            ->leftJoin('pr.groups', 'prg')->addSelect('prg')
            ->andWhere('s.date = :date')
            ->setParameter('date', $date)
            ->addOrderBy('s.startsAt', 'ASC')
            ->addOrderBy('s.endsAt', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Créneaux d'une date libres ou déjà occupés par cet entraînement (formulaire d'un entraînement)
     */
    public function getAvailableSlotsForPracticeQueryBuilder(Date $date, Practice $practice): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.date', 'd')->addSelect('d')
            ->innerJoin('s.court', 'c')->addSelect('c')
            ->andWhere('s.date = :date')
            ->setParameter('date', $date)
            ->addOrderBy('s.startsAt', 'ASC')
            ->addOrderBy('c.name', 'ASC')
        ;

        if ($practice->getId() === null) {
            $qb->andWhere('NOT EXISTS (SELECT 1 FROM ' . Booking::class . ' b WHERE b.slot = s)');
        } else {
            $qb->andWhere('NOT EXISTS (SELECT 1 FROM ' . Booking::class . ' b WHERE b.slot = s AND (b.practice IS NULL OR b.practice != :practice))')
                ->setParameter('practice', $practice);
        }

        return $qb;
    }

    /**
     * Why the explicit join on Booking::class ?
     * A single-valued association path expression to an inverse side
     * is not supported in DQL queries. Instead of "s.booking" use an explicit join.
     * 
     * 
     */
    public function getFutureAvailableSlotsQueryBuilder(Date $date, ?Slot $includeSlot = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.date', 'd')->addSelect('d')
            ->innerJoin('s.court', 'c')->addSelect('c')
            ->andWhere('s.date = :date')
            ->setParameter('date', $date)
            ->addOrderBy('s.startsAt', 'ASC')
            ->addOrderBy('c.name', 'ASC')
        ;

        $free = 'NOT EXISTS (SELECT 1 FROM ' . Booking::class . ' b WHERE b.slot = s)';

        if ($includeSlot === null) {
            $qb->andWhere($free);
        } else {
            // when moving a booking, its current slot stays selectable
            $qb->andWhere($free . ' OR s = :includeSlot')
                ->setParameter('includeSlot', $includeSlot);
        }

        return $qb;
    }
}
