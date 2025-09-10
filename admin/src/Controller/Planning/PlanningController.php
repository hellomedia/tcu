<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use App\Controller\BaseController;
use App\Repository\CourtRepository;
use App\Repository\DateRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PlanningController extends BaseController
{
    #[Route('/planning/matchs', name: 'admin_planning_matchs', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function matchs(DateRepository $dateRepository, CourtRepository $courtRepository): Response
    {
        $dates = $dateRepository->findFutureDates();
        $courts = $courtRepository->findAll();

        return $this->render('@admin/planning/matchs.html.twig', [
            'dates' => $dates,
            'courts' => $courts,
        ]);
    }

    #[Route('/planning', name: 'admin_planning', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function planning(DateRepository $dateRepository, CourtRepository $courtRepository): Response
    {
        $dates = $dateRepository->findFutureDates();
        $courts = $courtRepository->findAll();

        return $this->render('@admin/planning/planning.html.twig', [
            'dates' => $dates,
            'courts' => $courts,
        ]);
    }
}