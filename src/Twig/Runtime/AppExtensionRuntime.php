<?php

namespace App\Twig\Runtime;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

class AppExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {}

    public function isCurrentPage(string $route, array $parameters = []): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return false;
        }

        if ($route !== $request->attributes->get('_route')) {
            return false;
        }

        foreach ($parameters as $key => $value) {
            if ($request->attributes->get($key) !== $value) {
                return false;
            }
        }

        return true;
    }
}
