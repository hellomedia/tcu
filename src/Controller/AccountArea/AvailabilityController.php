<?php

namespace App\Controller\AccountArea;

use App\Controller\BaseController;
use App\Service\AvailabilityGrid;
use App\Service\SeasonContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Disponibilités du joueur pour les matchs d'interfacs de la saison en cours
 */
#[IsGranted('ROLE_USER')]
class AvailabilityController extends BaseController
{
    #[Route('/mes/disponibilites', name: 'my_availabilities', methods: ['GET'])]
    public function index(SeasonContext $seasonContext, AvailabilityGrid $grid): Response
    {
        $this->addBreadcrumb('Mon TCU', 'dashboard');
        $this->addBreadcrumb('Mes disponibilités');

        $season = $seasonContext->getCurrent();
        $registration = $this->getUser()->getPlayer()?->getRegistration($season);

        if ($season === null || !$season->hasInterfacs() || $registration === null || !$registration->isInterfacs()) {
            return $this->render('account_area/interfacs/availability/not_registered.html.twig', [
                'season' => $season,
            ]);
        }

        return $this->render('account_area/interfacs/availability/my_availabilities.html.twig', [
            'season' => $season,
            'registration' => $registration,
            'grid' => $grid->build($registration),
        ]);
    }

    #[IsCsrfTokenValid('availabilities', tokenKey: 'token')]
    #[Route('/mes/disponibilites', name: 'my_availabilities_save', methods: ['POST'])]
    public function save(Request $request, SeasonContext $seasonContext, AvailabilityGrid $grid): Response
    {
        $season = $seasonContext->getCurrent();
        $registration = $this->getUser()->getPlayer()?->getRegistration($season);

        if ($registration === null || !$registration->isInterfacs()) {
            throw $this->createAccessDeniedException();
        }

        $grid->save($registration, $grid->parseSubmission(
            $request->request->all('answered'),
            $request->request->all('avail'),
        ));

        // autosave (availability_autosave_controller) : summaries only, the date cards are being edited
        if ($this->isRequestForStream($request)) {
            $response = $this->render('account_area/interfacs/availability/_save_streams.html.twig', [
                'grid' => $grid->build($registration),
            ]);
            $response->headers->set('Content-Type', 'text/vnd.turbo-stream.html; charset=utf-8');

            return $response;
        }

        $this->addFlash('success', 'Disponibilités enregistrées');

        return $this->redirectToRoute('my_availabilities');
    }
}
