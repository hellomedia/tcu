<?php

namespace App\Controller\AccountArea;

use App\Controller\BaseController;
use App\Service\SeasonContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends BaseController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(SeasonContext $seasonContext): Response
    {
        $this->addBreadcrumb('Dashboard', 'dashboard');

        $player = $this->getUser()->getPlayer();

        return $this->render('account_area/dashboard/dashboard.html.twig', [
            'next_match' => $player?->getUpcomingMatchs()->first() ?: null,
            'to_confirm' => $player?->getUnconfirmedUpcomingMatchs()->count() ?? 0,
            // pas de poule affichée tant que la saison n'est pas publiée (comme Ma poule)
            'groups' => $seasonContext->getUnpublishedInterfacsSeason() ? [] : $player?->getGroupsForSeason($seasonContext->getInterfacsSeason())->toArray() ?? [],
        ]);
    }
}
