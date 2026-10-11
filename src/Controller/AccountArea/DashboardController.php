<?php

namespace App\Controller\AccountArea;

use App\Controller\BaseController;
use App\Repository\PlayerRepository;
use App\Service\AvailabilityGrid;
use App\Service\SeasonContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends BaseController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(SeasonContext $seasonContext, AvailabilityGrid $availabilityGrid, PlayerRepository $playerRepository): Response
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

        return $this->render('account_area/dashboard/dashboard.html.twig', [
            'player' => $player,
            'next_match' => $upcoming?->first() ?: null,
            'upcoming' => $upcoming?->count() ?? 0,
            'to_confirm' => $player?->getUnconfirmedUpcomingMatchs()->count() ?? 0,
            'groups' => $groups,
            'availabilities' => $availabilities,
            // aucun match planifié cette saison : le joueur attend son premier match
            'first_match_pending' => $season !== null && $player?->getScheduledMatchs($season)->isEmpty(),
        ]);
    }
}
