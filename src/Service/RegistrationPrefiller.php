<?php

namespace App\Service;

use App\Entity\Registration;
use App\Entity\Season;
use App\Enum\RankingSource;
use App\Enum\RegistrationStatus;
use App\Repository\RegistrationRepository;
use App\Repository\SeasonRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Pré-remplit les inscriptions d'une nouvelle saison à partir des saisons précédentes.
 *
 * Les inscriptions créées sont "à confirmer" (RegistrationStatus::PENDING) :
 * un admin les vérifie (nouveau classement, activités) puis les confirme, ou les écarte.
 * Une inscription écartée est conservée : elle n'est pas recréée si le pré-remplissage est relancé.
 *
 * - joueurs : ceux de la dernière saison du même type (Hiver => Hiver précédent),
 *   car ce ne sont pas les mêmes joueurs en été et en hiver.
 *   S'il n'y a pas encore de saison du même type : ceux de la dernière saison, quel que soit son type.
 * - activités : reprises de cette saison. Interfacs uniquement en hiver, interclubs uniquement en été :
 *   d'un type de saison à l'autre, seuls les cours sont repris.
 * - classement : repris de l'inscription la plus récente du joueur, tous types de saison confondus
 *   (le classement change à chaque saison, été comme hiver).
 *   Il est de toute façon à mettre à jour pour la nouvelle saison.
 *
 * Les joueurs déjà inscrits pour la saison ne sont pas modifiés :
 * le pré-remplissage peut être relancé sans risque.
 */
class RegistrationPrefiller
{
    public function __construct(
        private SeasonRepository $seasonRepository,
        private RegistrationRepository $registrationRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int nombre d'inscriptions créées
     */
    public function prefill(Season $season): int
    {
        $previousSeasons = $this->seasonRepository->findPrevious($season);

        if (!$previousSeasons) {
            return 0;
        }

        // dernière saison du même type, à défaut dernière saison
        $previous = $previousSeasons[0];
        $source = $previous;
        foreach ($previousSeasons as $candidate) {
            if ($candidate->getType() === $season->getType()) {
                $source = $candidate;
                break;
            }
        }

        // y compris les inscriptions écartées, pour ne pas les recréer
        $existing = $this->registrationRepository->findBySeasonIndexedByPlayer($season);

        // un joueur écarté d'une saison précédente n'y était pas inscrit
        $notDismissed = fn(Registration $registration) => !$registration->isDismissed();
        $fromSource = array_filter($this->registrationRepository->findBySeasonIndexedByPlayer($source), $notDismissed);
        $fromPrevious = $source === $previous ? $fromSource : array_filter($this->registrationRepository->findBySeasonIndexedByPlayer($previous), $notDismissed);

        $created = 0;

        foreach ($fromSource as $playerId => $sourceRegistration) {
            if (isset($existing[$playerId])) {
                continue;
            }

            // classement le plus récent : celui de la dernière saison si le joueur y était inscrit avec un classement
            $ranking = ($fromPrevious[$playerId] ?? null)?->getRanking() ?? $sourceRegistration->getRanking();

            $registration = (new Registration())
                ->setSeason($season)
                ->setRankingFrom($ranking, RankingSource::PREVIOUS_SEASON)
                ->setInterfacs($season->hasInterfacs() ? $sourceRegistration->isInterfacs() : null)
                ->setInterclubs($season->hasInterclubs() ? $sourceRegistration->isInterclubs() : null)
                ->setCours($sourceRegistration->isCours())
                ->setStatus(RegistrationStatus::PENDING)
            ;

            $sourceRegistration->getPlayer()->addRegistration($registration);

            $this->entityManager->persist($registration);
            $created++;
        }

        $this->entityManager->flush();

        return $created;
    }
}
