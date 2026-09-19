<?php

namespace App\Controller;

use App\Entity\Season;
use App\Form\PlayerPickerForm;
use App\Repository\CourtRepository;
use App\Repository\DateRepository;
use App\Repository\GroupRepository;
use App\Repository\PlayerRepository;
use App\Repository\SeasonRepository;
use App\Service\SeasonContext;
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
    public function myMatchs(Request $request, SeasonContext $seasonContext): Response
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
            'season' => $seasonContext->getInterfacsSeason(),
        ]);
    }

    /**
     * /interfacs/poules => saison par défaut
     * /interfacs/poules/hiver-2025-2026 => archives
     */
    #[Route('/interfacs/poules/{slug}', name: 'interfacs_groups', requirements: ['slug' => Season::SLUG_REGEX])]
    public function groups(
        GroupRepository $repository,
        DateRepository $dateRepository,
        PlayerRepository $playerRepository,
        SeasonRepository $seasonRepository,
        SeasonContext $seasonContext,
        ?string $slug = null,
    ): Response
    {
        if ($slug === null) {
            $season = $seasonContext->getInterfacsSeason();
        } else {
            $season = $seasonRepository->findOneBySlug($slug);

            // une saison future en préparation n'est pas publique
            if ($season === null || !$seasonContext->isPublic($season)) {
                throw $this->createNotFoundException();
            }
        }

        $groups = $repository->findBySeason($season);
        $dates = $dateRepository->findDatesByGroups($groups);

        $standings = [];
        foreach ($groups as $group) {
            $standings[$group->getId()] = $playerRepository->groupStandings($group);
        }

        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Interfacs', 'interfacs');
        $this->addBreadcrumb('Poules');

        return $this->render('interfacs/groups.html.twig', [
            'groups' => $groups,
            'standings' => $standings,
            'dates' => $dates,
            'season' => $season,
            'seasons' => $seasonContext->getPublicInterfacsSeasons(),
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
