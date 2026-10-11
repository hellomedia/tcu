<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\Player;
use App\Entity\Registration;
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

    /**
     * Tous les joueurs avec leur compte, par nom
     *
     * @param int[]|null $ids limite aux joueurs donnés
     * @return Player[] indexés par id
     */
    public function findAllWithAccount(?array $ids = null): array
    {
        $qb = $this->createQueryBuilder('p', 'p.id')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.lastname', 'ASC')
            ->addOrderBy('p.firstname', 'ASC');

        if ($ids !== null) {
            $qb->andWhere('p.id IN (:ids)')
                ->setParameter('ids', $ids);
        }

        return $qb->getQuery()->getResult();
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

    /**
     * Charge en une requête les matchs du joueur (Player::getMatchs()) avec leur créneau, leur date,
     * leur poule, leur résultat et leurs joueurs, au lieu de plusieurs requêtes par match
     */
    public function loadMatchs(?Player $player): void
    {
        if ($player === null) {
            return;
        }

        $this->createQueryBuilder('p')
            ->leftJoin('p.matchParticipations', 'mp')->addSelect('mp')
            ->leftJoin('mp.confirmationInfo', 'mpInfo')->addSelect('mpInfo')
            ->leftJoin('mp.match', 'm')->addSelect('m')
            ->leftJoin('m.group', 'g')->addSelect('g')
            // côtés inverses de OneToOne : à joindre, sinon une requête par match
            ->leftJoin('m.booking', 'b')->addSelect('b')
            ->leftJoin('m.result', 'r')->addSelect('r')
            ->leftJoin('b.slot', 's')->addSelect('s')
            ->leftJoin('s.date', 'd')->addSelect('d')
            ->leftJoin('m.participants', 'part')->addSelect('part')
            ->leftJoin('part.confirmationInfo', 'info')->addSelect('info')
            ->leftJoin('part.player', 'pl')->addSelect('pl')
            ->leftJoin('pl.user', 'u')->addSelect('u')
            ->andWhere('p = :player')
            ->setParameter('player', $player)
            ->getQuery()
            ->getResult()
        ;
    }

    public function groupStandings(Group $group): array
    {
        return $this->groupsStandings([$group])[$group->getId()] ?? [];
    }

    /**
     * Classements de plusieurs poules en une requête (au lieu d'une par poule)
     *
     * @param Group[] $groups
     *
     * @return array<int, list<array{player: Player, points: int, matchsPlayed: int}>> lignes de groupStandings(), par id de poule
     */
    public function groupsStandings(array $groups): array
    {
        if (!$groups) {
            return [];
        }

        $qb = $this->createQueryBuilder('p') // FROM Player p
            ->select([
                'p AS player',
                'g.id AS groupId',
                // total points per player in this group (0 if none)
                "COALESCE(SUM(CASE WHEN mp.side = 'A' THEN r.pointsA WHEN mp.side = 'B' THEN r.pointsB ELSE 0 END), 0) AS points",
                // number of matches played = number of matches where a result exists
                "COALESCE(COUNT(r.id), 0) AS matchsPlayed",
                // classement du joueur pour la saison de la poule (sans classement => à la fin)
                'COALESCE(MAX(ps.rankingOrder), -1) AS HIDDEN rankingOrder',
            ])
            // restrict players to the group membership
            ->join('p.groups', 'g')
            ->andWhere('g IN (:groups)')
            // côté inverse d'un OneToOne : sans jointure, une requête par joueur
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin(Registration::class, 'ps', 'WITH', 'ps.player = p AND ps.season = g.season')
            // LEFT JOIN into participants/matches/results so players with 0 still show
            ->leftJoin('p.matchParticipations', 'mp')     // if you don’t have this inverse, leftJoin MatchParticipant on player explicitly
            ->leftJoin('mp.match', 'm')
            ->leftJoin('m.result', 'r')
            ->andWhere('m.group = g')                   // count only matches in this group
            ->setParameter('groups', $groups)
            ->groupBy('p.id')
            ->addGroupBy('g.id')
            ->addGroupBy('u.id')
            ->orderBy('groupId', 'ASC')
            ->addOrderBy('points', 'DESC')
            ->addOrderBy('matchsPlayed', 'DESC')
            ->addOrderBy('rankingOrder', 'DESC')
            ->addOrderBy('p.lastname', 'ASC');

        $standings = [];
        foreach ($qb->getQuery()->getResult() as $row) { // arrays [player, groupId, points, matchsPlayed]
            $groupId = $row['groupId'];
            unset($row['groupId']);
            $standings[$groupId][] = $row;
        }

        return $standings;
    }
}
