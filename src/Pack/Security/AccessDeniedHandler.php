<?php

namespace App\Pack\Security;

use App\Translation\TranslatableHtmlMessage;
use App\Translation\TranslatableMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Twig\Environment;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private Environment $twig
    )
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $title = new TranslatableMessage(
            message: 'access_denied.title',
            domain: 'security',
        );
    
        $message = new TranslatableHtmlMessage(
            message: 'access_denied.message',
            domain: 'security',
        );

        return new Response(
            content: $this->twig->render('@exception/error403.html.twig', [
                'title' => $title,
                'message' => $message
            ]),
            status: 403
        );
    }
}
