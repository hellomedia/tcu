<?php

namespace Admin\Controller;

use App\Controller\BaseController;
use App\Entity\Season;
use App\Enum\RegistrationStatus;
use App\Repository\GroupRepository;
use App\Repository\RegistrationRepository;
use App\Repository\SeasonRepository;
use App\Service\RegistrationPrefiller;
use App\Service\SeasonContext;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SeasonController extends BaseController
{
    #[IsGranted('ROLE_EDITOR')]
    #[Route('/seasons', name: 'admin_seasons', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function seasons(
        SeasonRepository $seasonRepository,
        GroupRepository $groupRepository,
        RegistrationRepository $registrationRepository,
        SeasonContext $seasonContext,
    ): Response
    {
        $seasons = $seasonRepository->findAll();

        $stats = [];
        foreach ($seasons as $season) {
            $stats[$season->getId()] = [
                'groups' => $groupRepository->count(['season' => $season]),
                'confirmed' => $registrationRepository->countByStatus($season, RegistrationStatus::CONFIRMED),
                'pending' => $registrationRepository->countByStatus($season, RegistrationStatus::PENDING),
                'dismissed' => $registrationRepository->countByStatus($season, RegistrationStatus::DISMISSED),
            ];
        }

        return $this->render('@admin/season/seasons.html.twig', [
            'seasons' => $seasons,
            'stats' => $stats,
            'selected' => $seasonContext->getSelected(),
        ]);
    }

    /**
     * Saison sur laquelle on travaille dans l'admin (session)
     * Ne change rien pour le site public
     */
    #[IsGranted('ROLE_EDITOR')]
    #[Route('/season/{id:season}/select', name: 'admin_season_select', methods: ['GET'])]
    public function select(Season $season, SeasonContext $seasonContext, Request $request): Response
    {
        $seasonContext->select($season);

        // retour à la page d'où l'on vient, si c'est une page de l'admin
        $referer = $request->headers->get('referer');
        if ($referer !== null && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('admin_seasons');
    }

    /**
     * Saison courante du club : change la saison affichée par défaut sur le site public
     */
    #[IsGranted('ROLE_MANAGER')]
    #[IsCsrfTokenValid('season-set-current', tokenKey: 'token')]
    #[Route('/season/{id:season}/set-current', name: 'admin_season_set_current', methods: ['POST'])]
    public function setCurrent(Season $season, SeasonRepository $seasonRepository, SeasonContext $seasonContext): Response
    {
        $seasonRepository->setCurrent($season);
        $seasonContext->select($season);

        return $this->redirectToRoute('admin_seasons');
    }

    #[IsGranted('ROLE_MANAGER')]
    #[IsCsrfTokenValid('season-prefill', tokenKey: 'token')]
    #[Route('/season/{id:season}/prefill-registrations', name: 'admin_season_prefill', methods: ['POST'])]
    public function prefill(Season $season, RegistrationPrefiller $prefiller, SeasonContext $seasonContext): Response
    {
        $prefiller->prefill($season);

        // les inscriptions à confirmer sont celles de la saison sélectionnée
        $seasonContext->select($season);

        return $this->redirectToRoute('admin_registration_index', [
            'filters' => ['status' => ['comparison' => '=', 'value' => RegistrationStatus::PENDING->value]],
        ]);
    }
}
