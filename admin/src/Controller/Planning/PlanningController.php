<?php

namespace Admin\Controller\Planning;

use App\Controller\BaseController;
use App\Repository\CourtRepository;
use App\Repository\DateRepository;
use App\Repository\GroupRepository;
use App\Service\SeasonContext;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\HttpFoundation\Response;

class PlanningController extends BaseController
{
    #[AdminRoute('/planning', name: 'planning')]
    public function planning(DateRepository $dateRepository, CourtRepository $courtRepository, GroupRepository $groupRepository, SeasonContext $seasonContext): Response
    {
        // yesterday too: scores are often encoded the day after
        $dates = $dateRepository->findFutureDates(daysBefore: 1);
        $courts = $courtRepository->findAll();
        $groups = $groupRepository->findBySeason($seasonContext->getSelected());
        // légende : nombre de matchs à programmer par poule
        $groupRepository->loadMatchs($groups);

        return $this->render('@admin/planning/planning.html.twig', [
            'dates' => $dates,
            'courts' => $courts,
            'groups' => $groups,
        ]);
    }

    #[AdminRoute('/planning/past', name: 'planning_past')]
    public function planningPast(DateRepository $dateRepository, CourtRepository $courtRepository, GroupRepository $groupRepository, SeasonContext $seasonContext): Response
    {
        $season = $seasonContext->getSelected();
        $dates = $dateRepository->findPastDates($season);
        $courts = $courtRepository->findAll();
        $groups = $groupRepository->findBySeason($season);
        // légende : nombre de matchs à programmer par poule
        $groupRepository->loadMatchs($groups);

        return $this->render('@admin/planning/past.html.twig', [
            'dates' => $dates,
            'courts' => $courts,
            'groups' => $groups,
        ]);
    }
}