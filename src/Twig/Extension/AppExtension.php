<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\AppExtensionRuntime;
use App\Twig\Runtime\AvailabilityRuntime;
use App\Twig\Runtime\GroupColorRuntime;
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
            new TwigFunction('is_account_area', [AppExtensionRuntime::class, 'isAccountArea']),
            new TwigFunction('current_section', [AppExtensionRuntime::class, 'currentSection']),
            new TwigFunction('page_name', [AppExtensionRuntime::class, 'pageName']),
            new TwigFunction('group_color', [GroupColorRuntime::class, 'groupColor']),
            new TwigFunction('availability_summary', [AvailabilityRuntime::class, 'availabilitySummary']),
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
