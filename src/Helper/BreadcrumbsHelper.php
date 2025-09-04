<?php 

namespace App\Helper;

use Symfony\Component\HttpFoundation\RequestStack;

class BreadcrumbsHelper
{
    public function __construct(
        private RequestStack $requestStack,
    )
    {
    }

    private array $breadcrumbs = [];

    public function addBreadcrumb(string $item, ?string $route = null, ?array $routeParams = [], ?bool $isAdmin = null): void
    {
        $this->breadcrumbs[] = [
            'item' => $item,
            'route' => $route,
            'routeParams' => $routeParams,
        ];
    }

    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
    }
}
