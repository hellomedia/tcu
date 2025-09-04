<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Listing;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends BaseController
{
    #[Route('/my/dashboard', name: 'dashboard')]
    public function dashboard(EntityManager $entityManager): Response
    {
        $listingCount = $entityManager->getRepository(Listing::class)->countListings($this->getUser());

        $this->addBreadcrumb('dashboard', 'dashboard');

        return $this->render('admin/dashboard.html.twig', [
            'listingCount' => $listingCount,
        ]);
    }
}
