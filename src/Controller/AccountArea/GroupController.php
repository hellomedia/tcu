<?php

namespace App\Controller\AccountArea;

use App\Controller\BaseController;
use App\Repository\DateRepository;
use App\Repository\PlayerRepository;
use App\Service\SeasonContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class GroupController extends BaseController
{
    #[Route('/ma/poule', name: 'my_group')]
    public function index(DateRepository $dateRepository, PlayerRepository $playerRepository, SeasonContext $seasonContext): Response
    {
        $this->addBreadcrumb('Dashboard', 'dashboard');
        $this->addBreadcrumb('Ma poule');

        // saison en préparation, pas encore publiée : même page d'attente que le site (InterfacsController)
        if ($upcoming = $seasonContext->getUnpublishedInterfacsSeason()) {
            return $this->render('account_area/interfacs/group/coming_soon.html.twig', [
                'season' => $upcoming,
                'seasons' => $seasonContext->getPublicInterfacsSeasons(),
            ]);
        }

        $season = $seasonContext->getInterfacsSeason();
        $groups = $this->getUser()->getPlayer()->getGroupsForSeason($season);
        $dates = $dateRepository->findDatesByGroups($groups->toArray());

        $standings = [];
        foreach ($groups as $group) {
            $standings[$group->getId()] = $playerRepository->groupStandings($group);
        }

        return $this->render('account_area/interfacs/group/my_group.html.twig', [
            'groups' => $groups,
            'dates' => $dates,
            'standings' => $standings,
            'season' => $season,
        ]);
    }
}