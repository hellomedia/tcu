<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\Season;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Season>
 */
class SeasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Season::class);
    }

    /**
     * De la plus récente à la plus ancienne
     *
     * @return Season[]
     */
    public function findAll(): array
    {
        return parent::findBy(criteria: [], orderBy: [
            'startsOn' => 'DESC',
        ]);
    }

    public function findCurrent(): ?Season
    {
        return $this->findOneBy(['current' => true]);
    }

    public function findOneBySlug(string $slug): ?Season
    {
        $parsed = Season::parseSlug($slug);

        if ($parsed === null) {
            return null;
        }

        return $this->findOneBy($parsed);
    }

    /**
     * Une seule saison courante à la fois
     */
    public function setCurrent(Season $season): void
    {
        $em = $this->getEntityManager();

        $em->wrapInTransaction(function () use ($season) {
            foreach ($this->findBy(['current' => true]) as $current) {
                $current->setCurrent(false);
            }

            $season->setCurrent(true);
        });
    }

    /**
     * Saisons qui ont des poules, de la plus récente à la plus ancienne
     *
     * @param Season|null $upTo ne pas aller au-delà de cette saison
     *                          (une saison future en préparation n'est pas publique)
     *
     * @return Season[]
     */
    public function findWithGroups(?Season $upTo = null, bool $publishedOnly = false): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('EXISTS (
                SELECT 1 FROM ' . Group::class . ' g WHERE g.season = s
            )')
            ->orderBy('s.startsOn', 'DESC')
        ;

        if ($upTo !== null) {
            $qb->andWhere('s.startsOn <= :upTo')
                ->setParameter('upTo', $upTo->getStartsOn(), Types::DATE_IMMUTABLE);
        }

        if ($publishedOnly) {
            $qb->andWhere('s.published = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Saisons précédentes, de la plus récente à la plus ancienne
     *
     * @return Season[]
     */
    public function findPrevious(Season $season): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.startsOn < :startsOn')
            ->setParameter('startsOn', $season->getStartsOn(), Types::DATE_IMMUTABLE)
            ->orderBy('s.startsOn', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
