<?php

namespace Admin\Controller;

use App\Controller\BaseController;
use App\Entity\Registration;
use App\Service\AvailabilityGrid;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Disponibilités d'un inscrit, éditables par l'admin (même grille que le joueur)
 */
class AvailabilityController extends BaseController
{
    #[Route('/registration/{id:registration}/availabilities', name: 'admin_registration_availabilities', methods: ['GET'], defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function edit(Registration $registration, AvailabilityGrid $grid): Response
    {
        return $this->render('@admin/availability/edit.html.twig', [
            'registration' => $registration,
            'grid' => $grid->build($registration),
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

        return $this->redirectToRoute('admin_registration_availabilities', ['id' => $registration->getId()]);
    }
}
