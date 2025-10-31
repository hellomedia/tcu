<?php

namespace Pack\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class LoginLinkSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $goto = (string) $request->query->get('goto', '');

        // Security: only allow relative paths within your site
        if ($goto !== '' && str_starts_with($goto, '/')) {
            return new RedirectResponse($goto);
        }

        $fallback = $this->urlGenerator->generate('homepage');

        return new RedirectResponse($fallback);
    }
}
