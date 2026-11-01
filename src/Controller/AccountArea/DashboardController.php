<?php

namespace App\Controller\AccountArea;

use App\Controller\BaseController;
use App\Entity\Date;
use App\Entity\InterfacMatch;
use App\Entity\Practice;
use App\Repository\PlayerRepository;
use App\Repository\PracticeRepository;
use App\Service\AvailabilityGrid;
use App\Service\SeasonContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends BaseController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(SeasonContext $seasonContext, AvailabilityGrid $availabilityGrid, PlayerRepository $playerRepository, PracticeRepository $practiceRepository): Response
    {
        $this->addBreadcrumb('Mes interfacs', 'dashboard');

        $player = $this->getUser()->getPlayer();

        // disponibilités : seulement pour un joueur inscrit aux interfacs de la saison en cours (comme Mes disponibilités)
        $season = $seasonContext->getCurrent();
        $registration = $season?->hasInterfacs() ? $player?->getRegistration($season) : null;
        $availabilities = $registration?->isInterfacs() ? $availabilityGrid->summary($registration) : null;

        // poules et place au classement, pas affichées tant que la saison n'est pas publiée (comme Ma poule)
        $groups = [];
        if ($player !== null && $seasonContext->getUnpublishedInterfacsSeason() === null) {
            foreach ($player->getGroupsForSeason($seasonContext->getInterfacsSeason()) as $group) {
                $standings = $playerRepository->groupStandings($group);
                foreach ($standings as $index => $row) {
                    if ($row['player'] === $player) {
                        $groups[] = [
                            'group' => $group,
                            'position' => $index + 1,
                            'players' => \count($standings),
                            'points' => $row['points'],
                        ];
                    }
                }
            }
        }

        $playerRepository->loadMatchs($player);
        $upcoming = $player?->getUpcomingMatchs();

        // entraînements à venir des poules du joueur (annulés compris : ils restent affichés barrés)
        $practices = $player ? $practiceRepository->findUpcomingForPlayer($player) : [];
        $practicesToAnswer = \count(array_filter($practices, fn (Practice $practice) => !$practice->isCancelled() && $practice->getAnswerOf($player) === null));

        // agenda : matchs et entraînements à venir ensemble, par jour puis par heure
        $agenda = [];
        foreach ($upcoming ?? [] as $match) {
            \assert($match instanceof InterfacMatch);
            $agenda[$match->getDate()->getDate()->format('Y-m-d')][] = ['type' => 'match', 'match' => $match, 'time' => $match->startsAt()->format('H:i')];
        }
        foreach ($practices as $practice) {
            $agenda[$practice->getDay()->format('Y-m-d')][] = ['type' => 'practice', 'practice' => $practice, 'time' => substr((string) $practice->getTimeRange(), 0, 5)];
        }
        ksort($agenda);
        $days = [];
        foreach ($agenda as $ymd => $items) {
            usort($items, fn (array $a, array $b) => $a['time'] <=> $b['time']);
            $days[] = ['label' => Date::formatDay(new \DateTimeImmutable($ymd)), 'items' => $items];
        }

        return $this->render('account_area/dashboard/dashboard.html.twig', [
            'player' => $player,
            'agenda' => $days,
            'upcoming' => $upcoming?->count() ?? 0,
            'to_confirm' => $player?->getUnconfirmedUpcomingMatchs()->count() ?? 0,
            'practices' => $practices,
            'practices_to_answer' => $practicesToAnswer,
            'groups' => $groups,
            'availabilities' => $availabilities,
            // aucun match planifié cette saison : le joueur attend son premier match
            'first_match_pending' => $season !== null && $player?->getScheduledMatchs($season)->isEmpty(),
        ]);
    }
}
