<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Group;
use App\Entity\Season;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Group>
 */
class GroupRepository extends ServiceEntityRepository
{
    /**
     * Ordre d'affichage des poules (Group::$displayOrder), puis par nom
     */
    public const ORDER = ['displayOrder' => 'ASC', 'name' => 'ASC'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    public function findAll(): array
    {
        return parent::findBy(criteria: [], orderBy: self::ORDER);
    }

    /**
     * Ordre d'affichage pour une nouvelle poule : après les poules existantes de la saison
     */
    public function nextDisplayOrder(?Season $season): int
    {
        if ($season === null) {
            return 1;
        }

        $max = $this->createQueryBuilder('g')
            ->select('MAX(g.displayOrder)')
            ->andWhere('g.season = :season')
            ->setParameter('season', $season)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }

    /**
     * Poules d'une saison, avec les joueurs, leur compte et leurs inscriptions (classement par saison)
     * pour éviter les requêtes supplémentaires (Player::$user, côté inverse d'un OneToOne,
     * coûte une requête par joueur s'il n'est pas joint)
     *
     * @return Group[]
     */
    public function findBySeason(?Season $season): array
    {
        if ($season === null) {
            return [];
        }

        return $this->createQueryBuilder('g')
            ->leftJoin('g.players', 'p')->addSelect('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            // pas de condition sur la saison dans la jointure :
            // la collection Player::seasons doit rester complète
            ->leftJoin('p.registrations', 'ps')->addSelect('ps')
            ->andWhere('g.season = :season')
            ->setParameter('season', $season)
            ->addOrderBy('g.displayOrder', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->addOrderBy('p.lastname', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Charge en une requête les joueurs des poules, avec leur compte et leurs inscriptions,
     * pour des poules déjà chargées sans eux (comme findBySeason())
     *
     * @param Group[] $groups
     */
    public function loadPlayers(array $groups): void
    {
        if (!$groups) {
            return;
        }

        $this->createQueryBuilder('g')
            ->leftJoin('g.players', 'p')->addSelect('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('p.registrations', 'ps')->addSelect('ps')
            ->andWhere('g IN (:groups)')
            ->setParameter('groups', $groups)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Charge en une requête les matchs des poules (avec leur créneau, leur résultat et leurs joueurs),
     * au lieu de plusieurs requêtes par poule et par match à l'accès de Group::$matchs
     *
     * @param Group[] $groups
     */
    public function loadMatchs(array $groups): void
    {
        if (!$groups) {
            return;
        }

        $this->createQueryBuilder('g')
            ->leftJoin('g.matchs', 'm')->addSelect('m')
            // côtés inverses de OneToOne : à joindre, sinon une requête par match
            ->leftJoin('m.booking', 'b')->addSelect('b')
            ->leftJoin('m.result', 'r')->addSelect('r')
            ->leftJoin('b.slot', 's')->addSelect('s')
            ->leftJoin('s.date', 'd')->addSelect('d')
            ->leftJoin('s.court', 'c')->addSelect('c')
            ->leftJoin('m.participants', 'part')->addSelect('part')
            ->leftJoin('part.confirmationInfo', 'info')->addSelect('info')
            ->leftJoin('part.player', 'p')->addSelect('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->andWhere('g IN (:groups)')
            ->setParameter('groups', $groups)
            ->getQuery()
            ->getResult()
        ;
    }

    //    /**
    //     * @return Group[] Returns an array of Group objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('g')
    //            ->andWhere('g.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('g.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Group
    //    {
    //        return $this->createQueryBuilder('g')
    //            ->andWhere('g.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function getGroupsWithNonProgrammedMatchesQueryBuilder(?Season $season = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('g')
            ->innerJoin('g.matchs', 'm')->addSelect('m')
            // explicit join instead of where('m.booking IS NULL')
            // because single-valued association path expression to an inverse side is not supported in DQL queries
            ->leftJoin('m.booking', 'b')->addSelect('b')
            ->andWhere('b.id IS NULL')
            ->addOrderBy('g.displayOrder', 'ASC')
            ->addOrderBy('g.name', 'ASC')
        ;

        if ($season !== null) {
            $qb->andWhere('g.season = :season')
                ->setParameter('season', $season);
        }

        return $qb;
    }

    public function getSeasonQueryBuilder(?Season $season): QueryBuilder
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.season = :season')
            ->setParameter('season', $season)
            ->addOrderBy('g.displayOrder', 'ASC')
            ->addOrderBy('g.name', 'ASC')
        ;
    }

}
