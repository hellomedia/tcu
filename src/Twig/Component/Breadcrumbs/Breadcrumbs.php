<?php

namespace App\Twig\Component\Breadcrumbs;

use App\Helper\BreadcrumbsHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'breadcrumbs', template: '@component/Breadcrumbs/breadcrumbs.html.twig')]
class Breadcrumbs extends AbstractController
{
    public function __construct(
        private BreadcrumbsHelper $breadcrumbsHelper
    )
    {
    }

    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbsHelper->getBreadcrumbs();
    }
}