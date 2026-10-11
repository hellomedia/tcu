<?php

namespace App\Controller\AccountArea;

use App\Controller\BaseController;
use App\Service\AvailabilityGrid;
use App\Service\SeasonContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends BaseController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(SeasonContext $seasonContext, AvailabilityGrid $availabilityGrid): Response
    {
        $this->addBreadcrumb('Dashboard', 'dashboard');

        $player = $this->getUser()->getPlayer();

        // disponibilités : seulement pour un joueur inscrit aux interfacs de la saison en cours (comme Mes disponibilités)
        $season = $seasonContext->getCurrent();
        $registration = $season?->hasInterfacs() ? $player?->getRegistration($season) : null;
        $availabilities = $registration?->isInterfacs() ? $availabilityGrid->summary($registration) : null;

        return $this->render('account_area/dashboard/dashboard.html.twig', [
            'next_match' => $player?->getUpcomingMatchs()->first() ?: null,
            'to_confirm' => $player?->getUnconfirmedUpcomingMatchs()->count() ?? 0,
            // pas de poule affichée tant que la saison n'est pas publiée (comme Ma poule)
            'groups' => $seasonContext->getUnpublishedInterfacsSeason() ? [] : $player?->getGroupsForSeason($seasonContext->getInterfacsSeason())->toArray() ?? [],
            'availabilities' => $availabilities,
        ]);
    }
}
