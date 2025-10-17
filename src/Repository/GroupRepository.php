<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Group;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Group>
 */
class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    public function findAll(): array
    {
        return parent::findBy(criteria: [], orderBy: [
            'name' => 'ASC',
        ]);
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

    public function getGroupsWithNonProgrammedMatchesQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('g')
            ->innerJoin('g.matchs', 'm')->addSelect('m')
            // explicit join instead of where('m.booking IS NULL')
            // because single-valued association path expression to an inverse side is not supported in DQL queries
            ->leftJoin('m.booking', 'b')->addSelect('b')
            ->andWhere('b.id IS NULL')
            ->addOrderBy('g.name', 'ASC')
        ;
    }

}
