<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InterclubsController extends BaseController
{
    #[Route('/interclubs', name: 'interclubs')]
    public function homepage(): Response
    {
        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Interclubs', 'interclubs');
        
        return $this->render('interclubs/interclubs.html.twig', []);
    }
}
