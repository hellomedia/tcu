<?php
namespace Pack\Security\Controller;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Show custom error pages (only use in prod)
 * 
 * wiring in framework.yaml
 * 
 *     when@prod:
 *         framework:
 *             error_controller: 'Pack\Security\Controller\ExceptionController::show'
 * 
 */
class ExceptionController extends AbstractController
{
    public function show(FlattenException $exception, DebugLoggerInterface $logger = null): Response
    {
        $statusCode = $exception->getStatusCode();

        // Pick your template however you want
        $template = match ($statusCode) {
            400 => '@security/exception/error400.html.twig',
            403 => '@security/exception/error403.html.twig',
            404 => '@security/exception/error404.html.twig',
            500 => '@security/exception/error500.html.twig',
            default => '@security/exception/error.html.twig',
        };

        return $this->render($template, [
            'status_code' => $statusCode,
            'exception' => $exception,
        ]);
    }
}
