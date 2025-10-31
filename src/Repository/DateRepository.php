<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Date;
use App\Entity\Group;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Date>
 */
class DateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Date::class);
    }

    /**
    * @return Date[] Returns an array of Date objects
    */
    public function findFutureDates(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.slots', 's')->addSelect('s')
            ->leftJoin('s.booking', 'b')->addSelect('b')
            ->andWhere('d.date >= CURRENT_DATE()')
            ->orderBy('d.date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
    * @return Date[] Returns an array of Date objects
    */
    public function findPastDates(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.slots', 's')->addSelect('s')
            ->leftJoin('s.booking', 'b')->addSelect('b')
            ->andWhere('d.date < CURRENT_DATE()')
            ->orderBy('d.date', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
    * @return Date[] Returns an array of Date objects
    */
    public function findFutureDatesWithAvailableSlots(): array
    {
        return $this->getFutureDatesWithAvailableSlotsQueryBuilder()
            ->getQuery()
            ->getResult()
        ;
    }

    public function getFutureDatesWithAvailableSlotsQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.slots', 's')->addSelect('s')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM ' . Booking::class . ' b WHERE b.slot = s
            )')
            ->andWhere('d.date >= CURRENT_DATE()')
            ->orderBy('d.date', 'ASC')
        ;
    }


    /**
     * @return Date[] Returns an array of Date objects*
     * 
     * UNUSED BECAUSE OF HEAVY LIMITATIONS
     * Using this results in a large number of extra queries (100+)
     * SEE COMMENTS below.
     * Solution is use a different approach: see FindDatesByGroups() below
     */
    public function findDatesByGroup(Group $group): array
    {
        // ******** IMPORTANT LIMITATION ********* 
        // DO NOT SELECT slots, bookings and matches
        // Or else, only the matches for the first group queried 
        // are hydrated in the date.
        // When the same date entity is seen by doctrine in another query,
        // it reuses the cached entity.
        // In the date entity already loaded by doctrine, the collections for
        // slots, bookings and matches will be the ones from the first query.
        // If we don't select the slots/bookings/matches in the query
        // date.matches is a proxy object.
        // date.matchsByGroup(group) triggers a query on matches, which returns **all the matchs**. 
        // Date::matches now contains all the matchs and date.matchsByGroup(group)
        // returns the expected matches for each group.
        return $this->createQueryBuilder('d')
            ->innerJoin('d.slots', 's') // do not addSelect()
            ->innerJoin('s.booking', 'b') // do not addSelect()
            ->innerJoin('b.match', 'm') // do not addSelect()
            ->andWhere('m.group = :group')
            ->setParameter('group', $group)
            ->orderBy('d.date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Fix for limitation above
     * 
     * 1) Use FindDatesByGroups(). It hydrates everything with full data.
     * 2) Use Date::hasMatchFromGroup() and Date::getMatchsByGroup() to filter
     */
    public function findDatesByGroups(array $groups): array
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.slots', 's')->addSelect('s')
            ->innerJoin('s.booking', 'b')->addSelect('b')
            ->innerJoin('b.match', 'm')->addSelect('m')
            ->andWhere('m.group IN (:groups)')
            ->setParameter('groups', $groups)
            ->innerJoin('m.participants', 'part')->addSelect('part')
            ->innerJoin('part.player', 'player')->addSelect('player')
            ->leftJoin('player.user', 'u')->addSelect('u')
            ->leftJoin('m.result', 'res')->addSelect('res')
            ->leftJoin('part.confirmationInfo', 'info')->addSelect('info')
            ->orderBy('d.date', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /** @return array<string, Date> keyed by 'Y-m-d' */
    public function findByYmd(array $ymds): array
    {
        // Normalize & de-dup to strict Y-m-d
        $dates = array_map(
            fn(string $s) => (new \DateTimeImmutable($s))->format('Y-m-d'),
            $ymds
        );
        $dates = array_values(array_unique($dates));
        if (!$dates) return [];

        // Boundaries via lexicographic sort (valid for Y-m-d)
        sort($dates);
        $min = new \DateTimeImmutable($dates[0]);
        $max = new \DateTimeImmutable($dates[\count($dates) - 1]);

        // Query in one range
        $result = $this->createQueryBuilder('d')
            ->andWhere('d.date BETWEEN :min AND :max')
            ->setParameter('min', $min, Types::DATE_IMMUTABLE)
            ->setParameter('max', $max, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult();

        // Keep only requested days; map by Y-m-d
        $wanted = array_flip($dates);
        $matching = [];
        foreach ($result as $entity) {
            $key = $entity->getDate()->format('Y-m-d');
            if (isset($wanted[$key])) {
                $matching[$key] = $entity;
            }
        }

        return $matching;
    }


    //    public function findOneBySomeField($value): ?Date
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
