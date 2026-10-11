<?php

namespace App\Twig\Runtime;

use App\Helper\BreadcrumbsHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

class AppExtensionRuntime implements RuntimeExtensionInterface
{
    /** pages of the account area (Mon TCU): account band, active states in the menus */
    public const ACCOUNT_ROUTES = ['dashboard', 'my_matchs', 'my_group', 'my_availabilities', 'password_choose'];

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
