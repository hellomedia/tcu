<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Player>
 */
class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    //    /**
    //     * @return Player[] Returns an array of Player objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Player
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function groupStandings(Group $group): array
    {
        $qb = $this->createQueryBuilder('p') // FROM Player p
            ->select([
                'p AS player',
                // total points per player in this group (0 if none)
                "COALESCE(SUM(CASE WHEN mp.side = 'A' THEN r.pointsA WHEN mp.side = 'B' THEN r.pointsB ELSE 0 END), 0) AS points",
                // number of matches played = number of matches where a result exists
                "COALESCE(COUNT(r.id), 0) AS matchsPlayed",
            ])
            // restrict players to the group membership
            ->join('p.groups', 'g')
            ->andWhere('g = :group')
            // LEFT JOIN into participants/matches/results so players with 0 still show
            ->leftJoin('p.matchParticipations', 'mp')     // if you don’t have this inverse, leftJoin MatchParticipant on player explicitly
            ->leftJoin('mp.match', 'm')
            ->leftJoin('m.result', 'r')
            ->andWhere('m.group = :group')              // count only matches in this group
            ->setParameter('group', $group)
            ->groupBy('p.id')
            ->orderBy('points', 'DESC')
            ->addOrderBy('matchsPlayed', 'DESC')
            ->addOrderBy('p.rankingOrder', 'DESC')
            ->addOrderBy('p.lastname', 'ASC');

        return $qb->getQuery()->getResult(); // returns arrays [player, points, matchsPlayed]
    }
}
