<?php

namespace App\Repository;

use App\Entity\Registration;
use App\Entity\Season;
use App\Enum\RegistrationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Registration>
 */
class RegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Registration::class);
    }

    /**
     * @return array<int, Registration> inscriptions de la saison, indexées par id du joueur
     */
    public function findBySeasonIndexedByPlayer(Season $season): array
    {
        $registrations = $this->createQueryBuilder('ps')
            ->join('ps.player', 'p')->addSelect('p')
            // toutes les inscriptions du joueur : Player::seasons est initialisée sans requête supplémentaire
            ->leftJoin('p.registrations', 'all_ps')->addSelect('all_ps')
            ->andWhere('ps.season = :season')
            ->setParameter('season', $season)
            ->getQuery()
            ->getResult()
        ;

        $indexed = [];
        foreach ($registrations as $registration) {
            $indexed[$registration->getPlayer()->getId()] = $registration;
        }

        return $indexed;
    }

    public function countByStatus(Season $season, RegistrationStatus $status): int
    {
        return $this->count(['season' => $season, 'status' => $status]);
    }
}
