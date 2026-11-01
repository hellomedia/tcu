<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\Player;
use App\Entity\Practice;
use App\Entity\Season;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Practice>
 */
class PracticeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Practice::class);
    }

    /**
     * Entraînements d'une saison avec leurs créneaux, leurs poules (et leurs joueurs) et les réponses,
     * du plus proche au plus lointain
     *
     * @return Practice[]
     */
    public function findBySeason(Season $season): array
    {
        return $this->withEverything($this->createQueryBuilder('p'))
            ->andWhere('p.season = :season')
            ->setParameter('season', $season)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Entraînements d'un joueur : ceux liés à une de ses poules de la saison (une seule fois,
     * même quand deux de ses poules sont liées au même entraînement)
     *
     * @return Practice[]
     */
    public function findForPlayer(Player $player, Season $season): array
    {
        $ids = $this->createQueryBuilder('p')
            ->select('DISTINCT p.id')
            ->innerJoin('p.groups', 'g')
            ->innerJoin('g.players', 'pl')
            ->andWhere('pl = :player')
            ->andWhere('p.season = :season')
            ->setParameter('player', $player)
            ->setParameter('season', $season)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        if ($ids === []) {
            return [];
        }

        return $this->withEverything($this->createQueryBuilder('p'))
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Entraînements à venir (non annulés ou annulés) d'un joueur, toutes saisons : espace joueur
     *
     * @return Practice[]
     */
    public function findUpcomingForPlayer(Player $player): array
    {
        $ids = $this->createQueryBuilder('p')
            ->select('DISTINCT p.id')
            ->innerJoin('p.groups', 'g')
            ->innerJoin('g.players', 'pl')
            ->innerJoin('p.bookings', 'b')
            ->innerJoin('b.slot', 's')
            ->innerJoin('s.date', 'd')
            ->andWhere('pl = :player')
            ->andWhere('d.date >= CURRENT_DATE()')
            ->setParameter('player', $player)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        if ($ids === []) {
            return [];
        }

        return $this->withEverything($this->createQueryBuilder('p'))
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Entraînements liés à une poule
     *
     * @return Practice[]
     */
    public function findByGroup(Group $group): array
    {
        return $this->withEverything($this->createQueryBuilder('p'))
            ->innerJoin('p.groups', 'filterGroup')
            ->andWhere('filterGroup = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Créneaux (avec date et terrain), poules (avec joueurs et leur compte), réponses : tout en une requête.
     * Tri par date puis heure (les entraînements sans créneau en dernier).
     */
    private function withEverything(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->leftJoin('p.bookings', 'b')->addSelect('b')
            ->leftJoin('b.slot', 's')->addSelect('s')
            ->leftJoin('s.date', 'd')->addSelect('d')
            ->leftJoin('s.court', 'c')->addSelect('c')
            ->leftJoin('p.groups', 'g')->addSelect('g')
            ->leftJoin('g.players', 'gp')->addSelect('gp')
            ->leftJoin('gp.user', 'gpu')->addSelect('gpu')
            ->leftJoin('p.participants', 'part')->addSelect('part')
            ->leftJoin('part.player', 'partPlayer')->addSelect('partPlayer')
            ->addOrderBy('d.date', 'ASC')
            ->addOrderBy('s.startsAt', 'ASC')
            ->addOrderBy('p.id', 'ASC')
        ;
    }
}
