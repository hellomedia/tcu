<?php

namespace App\Service;

use App\Entity\Availability;
use App\Entity\Date;
use App\Entity\Registration;
use App\Entity\Season;
use App\Repository\AvailabilityRepository;
use App\Repository\DateRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Grille des disponibilités d'une inscription : dates à venir de la saison x heures de début des créneaux.
 *
 * Trois états par (date, heure) :
 *  - 'yes'     : disponible
 *  - 'no'      : pas disponible
 *  - 'unknown' : pas renseigné (pas de ligne Availability)
 *
 * Une date est "renseignée" quand toutes ses heures ont une réponse, "partielle" si certaines seulement
 * (créneau ajouté après la réponse du joueur), "non renseignée" sinon.
 */
class AvailabilityGrid
{
    public const YES = 'yes';
    public const NO = 'no';
    public const UNKNOWN = 'unknown';

    /** dates non renseignées à moins de 2 mois : rappel insistant (dashboard) */
    public const SOON = '+2 months';

    /** dates à plus de 3 mois : repliées sous "Plus tard" sur la page du joueur */
    public const LATER = '+3 months';

    /** @var array<int, Date[]> dates à venir par saison */
    private array $upcomingDates = [];

    /** @var array<int, array<string, bool>> réponses par inscription : [registrationId][dateId-H:i] => available */
    private array $answers = [];

    /** @var array<int, true> saisons dont toutes les réponses sont chargées */
    private array $seasonsLoaded = [];

    public function __construct(
        private DateRepository $dateRepository,
        private AvailabilityRepository $availabilityRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return Date[]
     */
    public function getUpcomingDates(Season $season): array
    {
        return $this->upcomingDates[$season->getId()] ??= $this->dateRepository->findUpcomingDates($season);
    }

    /**
     * Heures de début distinctes des créneaux d'une date (le terrain est indifférent)
     *
     * @return array<string, array{startsAt: \DateTimeImmutable, endsAt: \DateTimeImmutable, label: string}> indexé par H:i
     */
    public function getTimes(Date $date): array
    {
        $times = [];

        foreach ($date->getSlots() as $slot) {
            $key = $slot->getStartsAt()->format('H:i');
            $times[$key] ??= [
                'startsAt' => $slot->getStartsAt(),
                'endsAt' => $slot->getEndsAt(),
                'label' => $slot->getTimeRange(),
            ];
        }

        ksort($times);

        return $times;
    }

    /**
     * Grille complète d'une inscription, pour l'affichage / l'édition
     *
     * @return array{
     *     dates: list<array{date: Date, status: string, times: list<array{key: string, label: string, startsAt: \DateTimeImmutable, status: string}>, available: int, answered: int, soon: bool, later: bool}>,
     *     summary: array{status: string, answered: int, partial: int, total: int, missingSoon: int, later: int, missingLater: int}
     * }
     */
    public function build(Registration $registration): array
    {
        $answers = $this->getAnswers($registration);
        $dates = [];
        $answeredDates = 0;
        $partialDates = 0;
        $missingSoon = 0;
        $laterDates = 0;
        $missingLater = 0;
        $today = new \DateTimeImmutable('today');
        $soonLimit = $today->modify(self::SOON);
        $laterLimit = $today->modify(self::LATER);

        foreach ($this->getUpcomingDates($registration->getSeason()) as $date) {
            $times = [];
            $available = 0;
            $answered = 0;

            foreach ($this->getTimes($date) as $hm => $time) {
                $status = $this->statusFromAnswers($answers, $date, $hm);
                $available += $status === self::YES ? 1 : 0;
                $answered += $status !== self::UNKNOWN ? 1 : 0;
                $times[] = [
                    'key' => $hm,
                    'label' => $time['label'],
                    'startsAt' => $time['startsAt'],
                    'status' => $status,
                ];
            }

            $dateStatus = match (true) {
                $times === [] => 'none',
                $answered === \count($times) => 'answered',
                $answered > 0 => 'partial',
                default => 'none',
            };
            $answeredDates += $dateStatus === 'answered' ? 1 : 0;
            $partialDates += $dateStatus === 'partial' ? 1 : 0;

            $soon = $date->getDate() <= $soonLimit;
            $later = $date->getDate() > $laterLimit;
            $missing = $times !== [] && $dateStatus !== 'answered';
            $missingSoon += $soon && $missing ? 1 : 0;
            $laterDates += $later ? 1 : 0;
            $missingLater += $later && $missing ? 1 : 0;

            $dates[] = [
                'date' => $date,
                'status' => $dateStatus,
                'times' => $times,
                'available' => $available,
                'answered' => $answered,
                'soon' => $soon,
                'later' => $later,
            ];
        }

        $total = \count($dates);

        return [
            'dates' => $dates,
            'summary' => [
                'status' => match (true) {
                    $total === 0 => 'none',
                    $answeredDates === $total => 'complete',
                    $answeredDates > 0 || $partialDates > 0 => 'partial',
                    default => 'none',
                },
                'answered' => $answeredDates,
                'partial' => $partialDates,
                'total' => $total,
                'missingSoon' => $missingSoon,
                'later' => $laterDates,
                'missingLater' => $missingLater,
            ],
        ];
    }

    /**
     * Résumé seul (liste des inscriptions)
     *
     * @return array{status: string, answered: int, partial: int, total: int, missingSoon: int, later: int, missingLater: int}
     */
    public function summary(Registration $registration): array
    {
        return $this->build($registration)['summary'];
    }

    /**
     * Disponibilité d'une inscription pour une date et une heure : 'yes' | 'no' | 'unknown'
     */
    public function status(?Registration $registration, Date $date, \DateTimeInterface $startsAt): string
    {
        if ($registration === null) {
            return self::UNKNOWN;
        }

        return $this->statusFromAnswers($this->getAnswers($registration), $date, $startsAt->format('H:i'));
    }

    /**
     * Enregistre les réponses soumises.
     *
     * @param array<int, array<string, bool>> $submitted [dateId => [H:i => available]] pour les dates renseignées
     *                                                   (une date absente du tableau n'est pas modifiée)
     */
    public function save(Registration $registration, array $submitted): void
    {
        $existing = [];
        foreach ($this->availabilityRepository->findByRegistration($registration) as $availability) {
            $existing[$availability->getKey()] = $availability;
        }

        foreach ($this->getUpcomingDates($registration->getSeason()) as $date) {
            if (!\array_key_exists($date->getId(), $submitted)) {
                continue;
            }

            foreach ($this->getTimes($date) as $hm => $time) {
                $available = (bool) ($submitted[$date->getId()][$hm] ?? false);
                $key = Availability::key($date, $time['startsAt']);

                if (isset($existing[$key])) {
                    $existing[$key]->setAvailable($available);
                } else {
                    $availability = (new Availability())
                        ->setRegistration($registration)
                        ->setDate($date)
                        ->setStartsAt($time['startsAt'])
                        ->setAvailable($available);
                    $this->entityManager->persist($availability);
                }
            }
        }

        $this->entityManager->flush();

        unset($this->answers[$registration->getId()]);
    }

    /**
     * Réponses soumises par le formulaire (templates/availability/_grid.html.twig)
     *
     * @param array<int|string, mixed> $answered  answered[dateId] = 1 pour les dates renseignées
     * @param array<int|string, mixed> $available avail[dateId][H:i] = 1 pour les créneaux cochés
     *
     * @return array<int, array<string, bool>> [dateId => [H:i => available]], voir save()
     */
    public function parseSubmission(array $answered, array $available): array
    {
        $submitted = [];

        foreach ($available as $dateId => $times) {
            if (!\is_array($times)) {
                continue;
            }
            foreach ($times as $hm => $value) {
                // un créneau coché vaut réponse, même sans le marqueur "answered" (sans JS)
                $submitted[(int) $dateId][(string) $hm] = (bool) $value;
            }
        }

        foreach ($answered as $dateId => $value) {
            if ((string) $value === '1') {
                $submitted[(int) $dateId] ??= [];
            }
        }

        return $submitted;
    }

    /**
     * Charge d'un coup les réponses de tous les inscrits de la saison (modales de planning)
     */
    public function preloadSeason(Season $season): void
    {
        if (isset($this->seasonsLoaded[$season->getId()])) {
            return;
        }

        foreach ($this->availabilityRepository->findUpcomingBySeason($season) as $availability) {
            $this->answers[$availability->getRegistration()->getId()][$availability->getKey()] = $availability->isAvailable();
        }

        $this->seasonsLoaded[$season->getId()] = true;
    }

    /**
     * @return array<string, bool> [dateId-H:i => available]
     */
    private function getAnswers(Registration $registration): array
    {
        $id = $registration->getId();

        if (isset($this->seasonsLoaded[$registration->getSeason()->getId()])) {
            return $this->answers[$id] ?? [];
        }

        if (!isset($this->answers[$id])) {
            $this->answers[$id] = [];
            foreach ($this->availabilityRepository->findByRegistration($registration) as $availability) {
                $this->answers[$id][$availability->getKey()] = $availability->isAvailable();
            }
        }

        return $this->answers[$id];
    }

    /**
     * @param array<string, bool> $answers
     */
    private function statusFromAnswers(array $answers, Date $date, string $hm): string
    {
        $key = $date->getId() . '-' . $hm;

        if (!\array_key_exists($key, $answers)) {
            return self::UNKNOWN;
        }

        return $answers[$key] ? self::YES : self::NO;
    }
}
