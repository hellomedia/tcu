<?php

namespace App\Controller;

use App\Form\PlayerPickerForm;
use App\Repository\CourtRepository;
use App\Repository\DateRepository;
use App\Repository\GroupRepository;
use App\Repository\PlayerRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InterfacsController extends BaseController
{
    #[Route('/interfacs', name: 'interfacs')]
    public function homepage(): Response
    {
        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Interfacs', 'interfacs');
        
        return $this->render('interfacs/interfacs.html.twig', []);
    }

    #[Route('/interfacs/mes-matchs', name: 'interfacs_my_matchs', methods: ['GET'])]
    public function myMatchs(Request $request): Response
    {
        $form = $this->createForm(PlayerPickerForm::class);

        $form->handleRequest($request);

        $player = $form->get('player')?->getData();
    
        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Interfacs', 'interfacs');
        $this->addBreadcrumb('Mes matchs');

        return $this->render('interfacs/my_matchs.html.twig', [
            'form' => $form,
            'player' => $player,
        ]);
    }

    #[Route('/interfacs/poules', name: 'interfacs_groups')]
    public function groups(GroupRepository $repository, DateRepository $dateRepository, PlayerRepository $playerRepository): Response
    {
        $groups = $repository->findAll();

        foreach ($groups as $group) {
            $dates[$group->getId()] = $dateRepository->findDatesByGroup($group);
            $standings[$group->getId()] = $playerRepository->groupStandings($group);
        }

        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Interfacs', 'interfacs');
        $this->addBreadcrumb('Poules');

        return $this->render('interfacs/groups.html.twig', [
            'groups' => $groups,
            'standings' => $standings,
            'dates' => $dates,
        ]);
    }

    #[Route('/interfacs/planning', name: 'interfacs_planning')]
    public function planning(DateRepository $dateRepository, CourtRepository $courtRepository): Response
    {
        $dates = $dateRepository->findFutureDates();
        $courts = $courtRepository->findAll();

        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Interfacs', 'interfacs');
        $this->addBreadcrumb('Planning');

        return $this->render('interfacs/planning.html.twig', [
            'dates' => $dates,
            'courts' => $courts,
        ]);
    }

    #[Route('/interfacs/resultats', name: 'interfacs_results')]
    public function results(): Response
    {
        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Interfacs', 'interfacs');
        $this->addBreadcrumb('Résultats');

        return $this->render('interfacs/results.html.twig', []);
    }
}
