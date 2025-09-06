<?php

namespace App\Twig\Component;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'footer', template: 'component/footer.html.twig')]
class Footer extends AbstractController
{

}