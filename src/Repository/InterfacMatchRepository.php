<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\InterfacMatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InterfacMatch>
 */
class InterfacMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InterfacMatch::class);
    }

    //    /**
    //     * @return InterfacMatch[] Returns an array of InterfacMatch objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?InterfacMatch
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function getNonProgammedMatchsQueryBuilder(Group $group): QueryBuilder
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.booking', 'b')->addSelect('b')
            ->andWhere('b.id IS NULL')
            ->andWhere('m.group = :group')
            ->setParameter('group', $group)
            // Add joins and selects to avoid extra queries
            ->join('m.participants', 'part')->addSelect('part')
            ->join('part.player', 'player')->addSelect('player')
            ->leftJoin('player.matchParticipations', 'playermatchpart')->addSelect('playermatchpart')
            ->leftJoin('playermatchpart.match', 'matches')->addSelect('matches')
            ->leftJoin('matches.booking', 'booking')->addSelect('booking')
            ->leftJoin('booking.slot', 'slot')->addSelect('slot')
            ->leftJoin('slot.date', 'date')->addSelect('date')
        ;
    }
}
