<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends BaseController
{
    #[Route('/', name: 'homepage')]
    public function homepage(): Response
    {
        $this->addBreadcrumb('homepage', 'homepage');
        
        return $this->render('default/homepage.html.twig', []);
    }
}
