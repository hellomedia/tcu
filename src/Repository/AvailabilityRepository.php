<?php

namespace App\Repository;

use App\Entity\Availability;
use App\Entity\Registration;
use App\Entity\Season;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Availability>
 */
class AvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Availability::class);
    }

    /**
     * @return Availability[]
     */
    public function findByRegistration(Registration $registration): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.date', 'd')->addSelect('d')
            ->andWhere('a.registration = :registration')
            ->setParameter('registration', $registration)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Toutes les réponses des inscrits d'une saison pour ses dates à venir
     * (les dates ne sont pas liées à une saison : on se limite à la période de la saison)
     *
     * @return Availability[]
     */
    public function findUpcomingBySeason(Season $season): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.date', 'd')->addSelect('d')
            ->innerJoin('a.registration', 'r')->addSelect('r')
            ->andWhere('r.season = :season')
            ->setParameter('season', $season)
            ->andWhere('d.date >= CURRENT_DATE()')
            ->andWhere('d.date BETWEEN :startsOn AND :endsOn')
            ->setParameter('startsOn', $season->getStartsOn(), Types::DATE_IMMUTABLE)
            ->setParameter('endsOn', $season->getEndsOn(), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult()
        ;
    }
}
