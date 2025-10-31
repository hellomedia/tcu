<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\InterfacMatch;
use App\Entity\User;
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

    public function getNonScheduledMatchsQueryBuilder(Group $group): QueryBuilder
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

    /**
     * Upcoming matchs = scheduled in future
     */
    public function findUpcomingMatchs(User $user): array
    {
        $qb = $this->createQueryBuilder('m')
            ->join('m.booking', 'b')->addSelect('b') // INNER JOIN
            ->join('m.participants', 'part')->addSelect('part')
            ->join('part.player', 'player')->addSelect('player')
            ->join('player.user', 'u')->addSelect('u')
            ->andWhere('u = :user')
            ->setParameter('user', $user)
            ->leftJoin('m.result', 'result')->addSelect('result') // don't understand why but avoids extra queries
            ->join('b.slot', 'slot')->addSelect('slot')
            ->join('slot.date', 'date')->addSelect('date')
            ->andWhere('date.date >= CURRENT_DATE()')
            ->join('part.confirmationInfo', 'info')->addSelect('info')
            ->addOrderBy('date.date', 'ASC')
            // add other participants
            ->join('m.participants', 'otherparticipants')->addSelect('otherparticipants')
            ->leftJoin('otherparticipants.confirmationInfo', 'otherinfos')->addSelect('otherinfos') // leftJoin. might not exist.
            ->join('otherparticipants.player', 'otherplayers')->addSelect('otherplayers')
        ;

        return $qb->getQuery()->getResult();
    }

    public function findNonScheduledMatchs(User $user): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.booking', 'b')->addSelect('b')
            ->andWhere('b.id IS NULL')
            ->join('m.participants', 'part')->addSelect('part')
            ->join('part.player', 'player')->addSelect('player')
            ->join('player.user', 'u')->addSelect('u')
            ->andWhere('u = :user')
            ->setParameter('user', $user)
            ->leftJoin('m.result', 'result')->addSelect('result') // don't understand why but avoids extra queries
            // add other participants
            ->join('m.participants', 'otherparticipants')->addSelect('otherparticipants')
            ->leftJoin('otherparticipants.confirmationInfo', 'otherinfos')->addSelect('otherinfos') // leftJoin. might not exist
            ->join('otherparticipants.player', 'otherplayers')->addSelect('otherplayers')
        ;

        return $qb->getQuery()->getResult();
    }
}
