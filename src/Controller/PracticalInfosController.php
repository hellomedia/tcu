<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PracticalInfosController extends BaseController
{
    #[Route('/infos-pratiques', name: 'practical_infos')]
    public function homepage(): Response
    {
        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Infos pratiques', 'practical_infos');

        return $this->render('practical_infos/practical_infos.html.twig', []);
    }

    #[Route('/infos-pratiques/devenir-membre', name: 'practical_infos_membership')]
    public function membership(): Response
    {
        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Infos pratiques', 'practical_infos');
        $this->addBreadcrumb('Devenir membre');

        return $this->render('practical_infos/membership.html.twig', []);
    }

    #[Route('/infos-pratiques/reservations', name: 'practical_infos_bookings')]
    public function bookings(): Response
    {
        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Infos pratiques', 'practical_infos');
        $this->addBreadcrumb('Réservations');

        return $this->render('practical_infos/bookings.html.twig', []);
    }
}
