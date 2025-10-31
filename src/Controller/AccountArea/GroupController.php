<?php

namespace App\Controller\AccountArea;

use App\Controller\BaseController;
use App\Repository\DateRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class GroupController extends BaseController
{
    #[Route('/ma/poule', name: 'my_group')]
    public function index(DateRepository $dateRepository, PlayerRepository $playerRepository): Response
    {
        $this->addBreadcrumb('Dashboard', 'dashboard');
        $this->addBreadcrumb('Ma poule');

        $groups = $this->getUser()->getPlayer()->getGroups();
        $dates = $dateRepository->findDatesByGroups($groups->toArray());

        foreach ($groups as $group) {
            $standings[$group->getId()] = $playerRepository->groupStandings($group);
        }

        return $this->render('account_area/interfacs/group/my_group.html.twig', [
            'groups' => $groups,
            'dates' => $dates,
            'standings' => $standings,
        ]);
    }
}