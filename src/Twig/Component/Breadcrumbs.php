<?php

namespace App\Twig\Component;

use App\Helper\BreadcrumbsHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'breadcrumbs', template: 'component/breadcrumbs.html.twig')]
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