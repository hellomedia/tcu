<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\AppExtensionRuntime;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;
use Twig\TwigTest;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {

    }

    /**
     * NB: Simple globals can be set in config/packages/twig.yaml
     */
    public function getGlobals(): array
    {
        // set in ControllerSubscriber
        $theme = $this->requestStack->getCurrentRequest()?->attributes->get('theme', 'light');

        return [
            'theme' => $theme,
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_current_page', [AppExtensionRuntime::class, 'isCurrentPage']),
        ];
    }

    public function getTests(): array
    {
        return [
            new TwigTest('instanceOf', [$this, 'isInstanceOf']),
        ];
    }

    public function isInstanceOf($var, $class): bool
    {
        return $var instanceof $class;
    }
}
