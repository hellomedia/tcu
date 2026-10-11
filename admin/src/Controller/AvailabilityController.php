<?php

namespace Admin\Controller;

use App\Controller\BaseController;
use App\Entity\Registration;
use App\Repository\GroupRepository;
use App\Repository\RegistrationRepository;
use App\Service\AvailabilityGrid;
use App\Service\SeasonContext;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Disponibilités des inscrits : vue d'ensemble de la saison (page Dispos),
 * grille d'un inscrit éditable par l'admin (même grille que le joueur)
 */
class AvailabilityController extends BaseController
{
    /** ?from=dispos sur la grille d'un inscrit : retour à la page Dispos plutôt qu'aux inscriptions */
    private const FROM_OVERVIEW = 'dispos';

    /**
     * Inscrits aux interfacs de la saison sélectionnée, par poule, avec l'état de leurs dispos
     */
    #[Route('/availabilities', name: 'admin_availabilities', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function index(SeasonContext $seasonContext, RegistrationRepository $registrationRepository, GroupRepository $groupRepository, AvailabilityGrid $grid): Response
    {
        $season = $seasonContext->getSelected();
        $sections = [];
        $counts = ['complete' => 0, 'partial' => 0, 'none' => 0];

        if ($season !== null) {
            // réponses de tous les inscrits en une requête
            $grid->preloadSeason($season);

            // une section par poule, dans l'ordre des poules, puis les inscrits sans poule
            foreach ($groupRepository->getSeasonQueryBuilder($season)->getQuery()->getResult() as $group) {
                $sections[$group->getId()] = ['group' => $group, 'rows' => []];
            }
            $withoutGroup = [];

            foreach ($registrationRepository->findInterfacsBySeason($season) as $registration) {
                $row = ['registration' => $registration, 'summary' => $grid->summary($registration)];
                $counts[$row['summary']['status']]++;

                $groups = $registration->getPlayer()->getGroupsForSeason($season);
                if ($groups->isEmpty()) {
                    $withoutGroup[] = $row;
                }
                foreach ($groups as $group) {
                    $sections[$group->getId()]['rows'][] = $row;
                }
            }

            $sections = array_values(array_filter($sections, fn (array $section) => $section['rows'] !== []));
            if ($withoutGroup !== []) {
                $sections[] = ['group' => null, 'rows' => $withoutGroup];
            }
        }

        return $this->render('@admin/availability/index.html.twig', [
            'season' => $season,
            'sections' => $sections,
            'counts' => $counts,
            'has_dates' => $season !== null && $grid->getUpcomingDates($season) !== [],
            'from' => self::FROM_OVERVIEW,
        ]);
    }

    #[Route('/registration/{id:registration}/availabilities', name: 'admin_registration_availabilities', methods: ['GET'], defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function edit(Registration $registration, Request $request, AvailabilityGrid $grid): Response
    {
        $from = $this->from($request);

        return $this->render('@admin/availability/edit.html.twig', [
            'registration' => $registration,
            'grid' => $grid->build($registration),
            'from' => $from,
            'back_url' => $from !== null ? $this->generateUrl('admin_availabilities') : $this->generateUrl('admin_registration_index'),
        ]);
    }

    #[IsCsrfTokenValid('availabilities', tokenKey: 'token')]
    #[Route('/registration/{id:registration}/availabilities', name: 'admin_registration_availabilities_save', methods: ['POST'], defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function save(Registration $registration, Request $request, AvailabilityGrid $grid): Response
    {
        $grid->save($registration, $grid->parseSubmission(
            $request->request->all('answered'),
            $request->request->all('avail'),
        ));

        $this->addFlash('success', 'Disponibilités enregistrées');

        return $this->redirectToRoute('admin_registration_availabilities', array_filter([
            'id' => $registration->getId(),
            'from' => $this->from($request),
        ]));
    }

    private function from(Request $request): ?string
    {
        return $request->query->get('from') === self::FROM_OVERVIEW ? self::FROM_OVERVIEW : null;
    }
}
