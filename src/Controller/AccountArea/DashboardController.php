<?php

namespace App\Controller\AccountArea;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends BaseController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(): Response
    {
        $this->addBreadcrumb('Dashboard', 'dashboard');

        return $this->render('account_area/dashboard/dashboard.html.twig');
    }
}