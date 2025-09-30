<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CoursController extends BaseController
{
    #[Route('/cours', name: 'cours')]
    public function homepage(): Response
    {
        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Cours', 'cours');
        
        return $this->render('cours/cours.html.twig', []);
    }
}
