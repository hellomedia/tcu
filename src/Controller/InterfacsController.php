<?php

namespace App\Controller;

use App\Entity\Season;
use App\Repository\CourtRepository;
use App\Repository\DateRepository;
use App\Repository\GroupRepository;
use App\Repository\PlayerRepository;
use App\Repository\SeasonRepository;
use App\Service\SeasonContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InterfacsController extends BaseController
{
    /**
     * Pas de page d'accueil : les pages interfacs ont une bande d'onglets (Poules, Planning, Mes interfacs)
     */
    #[Route('/interfacs', name: 'interfacs')]
    public function homepage(): Response
    {
        return $this->redirectToRoute('interfacs_groups');
    }

    /**
     * Saison courante pas encore publiée : page d'attente à la place des poules, du planning et des matchs
     */
    private function comingSoon(SeasonContext $seasonContext): ?Response
    {
        $season = $seasonContext->getUnpublishedInterfacsSeason();

        if ($season === null) {
            return null;
        }

        return $this->render('interfacs/coming_soon.html.twig', [
            'season' => $season,
            'seasons' => $seasonContext->getPublicInterfacsSeasons(),
        ]);
    }

    /**
     * Ancienne recherche publique des matchs d'un joueur : les matchs sont dans Mon TCU (connexion requise)
     */
    #[Route('/interfacs/mes-matchs', name: 'interfacs_my_matchs', methods: ['GET'])]
    public function myMatchs(): Response
    {
        return $this->redirectToRoute('dashboard', status: Response::HTTP_MOVED_PERMANENTLY);
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
            // pas de saison publiée : page d'attente
            if ($comingSoon = $this->comingSoon($seasonContext)) {
                $this->addBreadcrumb('Homepage', 'homepage');
                $this->addBreadcrumb('Interfacs', 'interfacs');

                return $comingSoon;
            }

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
        $standings = $playerRepository->groupsStandings($groups);

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
    public function planning(DateRepository $dateRepository, CourtRepository $courtRepository, GroupRepository $groupRepository, SeasonContext $seasonContext): Response
    {
        if ($comingSoon = $this->comingSoon($seasonContext)) {
            $this->addBreadcrumb('Homepage', 'homepage');
            $this->addBreadcrumb('Interfacs', 'interfacs');

            return $comingSoon;
        }

        $dates = $dateRepository->findFutureDates();
        $courts = $courtRepository->findAll();

        // légende des couleurs : les poules qui ont un match dans le planning, dans l'ordre des poules
        $planned = [];
        foreach ($dates as $date) {
            foreach ($date->getSlots() as $slot) {
                if ($group = $slot->getMatch()?->getGroup()) {
                    $planned[$group->getId()] = $group;
                }
            }
        }
        $seasons = [];
        foreach ($planned as $group) {
            $seasons[$group->getSeason()->getId()] = $group->getSeason();
        }
        $groups = [];
        foreach ($seasons as $season) {
            foreach ($groupRepository->getSeasonQueryBuilder($season)->getQuery()->getResult() as $group) {
                if (isset($planned[$group->getId()])) {
                    $groups[] = $group;
                }
            }
        }

        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Interfacs', 'interfacs');
        $this->addBreadcrumb('Planning');

        return $this->render('interfacs/planning.html.twig', [
            'dates' => $dates,
            'courts' => $courts,
            'groups' => $groups,
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
