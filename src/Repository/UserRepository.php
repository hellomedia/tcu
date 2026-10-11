<?php

namespace App\Repository;

use App\Entity\Season;
use App\Entity\User;
use App\Enum\RegistrationStatus;
use Pack\Security\Repository\Trait\UserSecurityRepositoryTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    use UserSecurityRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Comptes de joueurs créés par un admin, pas encore invités (Admin\Mailer\InvitationMailer),
     * limités aux inscrits aux interfacs d'une saison si elle est donnée (page Invitations de l'admin)
     *
     * @return User[]
     */
    public function findPlayersToInvite(?Season $season = null): array
    {
        // roles est une colonne json : le rôle se filtre en PHP
        $qb = $this->createQueryBuilder('u')
            ->join('u.player', 'p')
            ->addSelect('p')
            ->andWhere('u.invitedAt IS NULL')
            ->orderBy('p.lastname', 'ASC')
            ->addOrderBy('p.firstname', 'ASC');

        if ($season !== null) {
            // mêmes inscrits que RegistrationRepository::findInterfacsBySeason
            $qb->join('p.registrations', 'r')
                ->andWhere('r.season = :season')
                ->andWhere('r.interfacs = true')
                ->andWhere('r.status != :dismissed')
                ->setParameter('season', $season)
                ->setParameter('dismissed', RegistrationStatus::DISMISSED);
        }

        $users = $qb->getQuery()->getResult();

        return array_values(array_filter($users, fn(User $user) => $user->mustChoosePassword()));
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
