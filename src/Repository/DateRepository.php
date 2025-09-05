<?php

namespace App\Repository;

use App\Entity\Date;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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
            ->leftJoin('d.slots', 's')
            ->addSelect('s')
            ->andWhere('d.date >= :today')
            ->setParameter('today', (new DateTimeImmutable('today')), Types::DATE_IMMUTABLE)
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
