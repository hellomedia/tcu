<?php

namespace Pack\Security\Controller;

use App\Controller\BaseController;
use Symfony\Component\Routing\Attribute\Route;

class LoginLinkController extends BaseController
{
    #[Route('/login-link/check', name: 'login_link_check')]
    public function check(): never
    {
        throw new \LogicException('Intercepted by login_link.');
    }
}
