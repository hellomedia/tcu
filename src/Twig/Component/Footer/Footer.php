<?php

namespace App\Twig\Component\Footer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'footer', template: '@component/Footer/footer.html.twig')]
class Footer extends AbstractController
{

}