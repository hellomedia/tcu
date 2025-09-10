<?php

namespace App\Controller;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ResultsController extends BaseController
{
    #[Route('/results', name: 'results')]
    public function planning(): Response
    {
        return $this->render('results/results.html.twig', [
        ]);
    }
}