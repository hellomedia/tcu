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
     * Poules d'une saison, avec les joueurs et leurs inscriptions (classement par saison)
     * pour éviter les requêtes supplémentaires
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
