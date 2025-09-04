<?php

namespace App\Pack\Security;

use App\Repository\UserRepository;
use App\Pack\Security\Exception\EmailNotVerifiedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * As defined in AbstractLoginFormAuthenticator::supports(),
 * this LoginFormAuthenticator kicks in to handle login form
 * when a POST request is sent to login path.
 * (so for this firewall, login check path = login path)
 */
class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    final public const LOGIN_ROUTE = 'login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private BotDetector $botDetector,
        private UserRepository $userRepository,
    ) {
    }

    /**
    * Authenticator checks if :
    * - user exists (normally, retrieved from user provider but we use a callable)
    * - login credentials are good
    *
    * In addition, User checker (Security\UserChecker) is called
    * to perform additional checks (disabled, locked...) pre-auth and post-auth
    * to determine if user is allowed to login
     */
    public function authenticate(Request $request): Passport
    {
        $email = $request->request->getString('email');
        $password = $request->request->getString('password');

        if ($email === '') {
            throw new BadCredentialsException('Invalid username.');
        }

        if (\strlen($email) > UserBadge::MAX_USERNAME_LENGTH) {
            throw new BadCredentialsException('Invalid username.');
        }

        if ($this->botDetector->detectBotOnLoginForm($password)) {
            throw new BadCredentialsException();
        }

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email, function ($userIdentifier) {
                // use a callable so we can thrown a CustomUserMessageAuthenticationException
                // to give a clear message when account does not exist.
                // This is the only exception that allows us to give a custom error message
                // inside the authentication system of Symfony.
                // If we throw a UserNotFound exception, it gets intercepted and replaced by Symfony
                // to the generic BadCredentialsException for security reasons.
                $user = $this->userRepository->findUserByIdentifier($userIdentifier);
                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Username could not be found.');
                }
                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                new RememberMeBadge()
            ]
        );
    }

    /**
     * Also called from UserAuthenticator->authenticate()
     * when authenticating manually after email validation
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $session = $request->getSession();

        if ($session->has('force_full_authentication')) {
            $session->remove('force_full_authentication');
        }

        if ($targetPathFromSession = $this->getTargetPath($session, $firewallName)) {
            $this->removeTargetPath($session, $firewallName);
            return new RedirectResponse($targetPathFromSession);
        }

        return new RedirectResponse($this->urlGenerator->generate('dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof EmailNotVerifiedException) {

            $session = $request->getSession();

            $session->set('unverified_email', $session->get(SecurityRequestAttributes::LAST_USERNAME));

            return new RedirectResponse($this->urlGenerator->generate('resend_validation_email'));
        }

        return parent::onAuthenticationFailure($request, $exception);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
