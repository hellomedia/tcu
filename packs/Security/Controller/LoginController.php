<?php

namespace Pack\Security\Controller;

use App\Controller\BaseController;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginController extends BaseController
{
    use TargetPathTrait;

    #[Route(path: '/login', name: 'login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $session = $request->getSession();
        $forceFullAuthentication = $session->has('force_full_authentication');

        if ($this->isGranted('IS_AUTHENTICATED') && !$forceFullAuthentication) {
            return $this->redirectToRoute('admin_dashboard');
        }

        // force target path from query
        // on top of automated one which does not seem to kick in here
        if ($request->query->has('_target_path')) {
            $this->saveTargetPath(
                $session,
                'main',
                $request->query->get('_target_path')
            );
        }

        // login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last email entered by user
        $lastEmail = $authenticationUtils->getLastUsername();

        return $this->render('@security/login/login.html.twig', [
            'last_email' => $lastEmail,
            'error' => $error,
            'force_full_authentication' => $forceFullAuthentication,
        ]);
    }

    /**
     * Called from security/login.html.twig.
     *
     * Do not show password reset link if account disabled
     * 
     * NB: If no last_email is defined, $email = "" ==> show reset password
     */
    public function showPasswordResetLink(string $email, UserRepository $userRepository): Response
    {
        // If last_email is empty string ==> show reset password
        if ($email == '') {
            return $this->render('@security/login/show_password_reset_link.html.twig', [
                'show' => true,
            ]);
        }

        $show = true;

        $user = $userRepository->findUserByIdentifier($email);

        // do not show if account does not exist
        if (!$user) {
            $show = false;
        }

        // do not show if account disabled
        if ($user && ($user->isAccountNonConfirmed() || $user->isAccountDisabledByAdmin())) {
            $show = false;
        }

        return $this->render('@security/login/show_password_reset_link.html.twig', [
            'show' => $show,
        ]);
    }

    /**
     * Called from security/login.html.twig.
     *
     * Do not show registration link to existing users
     */
    public function showRegistrationLink(string $email, UserRepository $userRepository)
    {
        $show = true;

        $user = $userRepository->findUserByIdentifier($email);

        // do not show if account exists
        if ($user) {
            $show = false;
        }

        return $this->render('@security/login/show_registration_link.html.twig', [
            'show' => $show,
        ]);
    }
}
