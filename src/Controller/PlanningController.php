<?php

namespace App\Controller;

use App\Controller\BaseController;
use App\Repository\CourtRepository;
use App\Repository\DateRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PlanningController extends BaseController
{
    #[Route('/planning', name: 'planning')]
    public function planning(DateRepository $dateRepository, CourtRepository $courtRepository): Response
    {
        $dates = $dateRepository->findFutureDates();
        $courts = $courtRepository->findAll();

        return $this->render('planning/planning.html.twig', [
            'dates' => $dates,
            'courts' => $courts,
        ]);
    }
}