<?php

namespace App\Twig\Runtime;

use App\Helper\BreadcrumbsHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

class AppExtensionRuntime implements RuntimeExtensionInterface
{
    /** pages of the account area (Mon TCU): account band, active states in the menus */
    public const ACCOUNT_ROUTES = ['dashboard', 'my_matchs', 'my_group', 'my_availabilities', 'password_choose'];

    /** sections with a band of tabs (layout/banded.html.twig): route prefix => name and route of the section */
    private const SECTIONS = [
        'interfacs' => ['name' => 'Interfacs', 'route' => 'interfacs_groups'],
    ];

    public function __construct(
        private RequestStack $requestStack,
        private BreadcrumbsHelper $breadcrumbsHelper,
    ) {}

    /**
     * Name of the page = last breadcrumb (heading of the account pages, which have no breadcrumbs)
     */
    public function pageName(): ?string
    {
        $breadcrumbs = $this->breadcrumbsHelper->getBreadcrumbs();

        return $breadcrumbs === [] ? null : end($breadcrumbs)['item'];
    }

    public function isAccountArea(): bool
    {
        return \in_array($this->requestStack->getCurrentRequest()?->attributes->get('_route'), self::ACCOUNT_ROUTES, true);
    }

    /**
     * Section of the current page, shown next to the site name in the top bar on small screens
     * (the band of the section only has its tabs)
     *
     * @return array{name: string, route: string}|null
     */
    public function currentSection(): ?array
    {
        if ($this->isAccountArea()) {
            return ['name' => 'Mes interfacs', 'route' => 'dashboard'];
        }

        $route = (string) $this->requestStack->getCurrentRequest()?->attributes->get('_route');

        foreach (self::SECTIONS as $prefix => $section) {
            if ($route === $prefix || str_starts_with($route, $prefix . '_')) {
                return $section;
            }
        }

        return null;
    }

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
