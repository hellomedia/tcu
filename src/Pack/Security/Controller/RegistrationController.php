<?php

namespace App\Pack\Security\Controller;

use App\Controller\BaseController;
use App\Entity\User;
use App\Enum\AccountLanguage;
use App\Event\AccountCreatedEvent;
use App\Pack\Security\Exception\EmailAlreadyVerifiedException;
use App\Pack\Security\Form\RegistrationForm;
use App\Pack\Security\BotDetector;
use App\Pack\Security\EmailVerifier;
use App\Pack\Security\LoginFormAuthenticator;
use App\Pack\Security\Mailer\SecurityMailer;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends BaseController
{
    use TargetPathTrait;

    /**
     * NB: custom path ( /_register_, not "/register") to keep away simple bots
     * 
     * NB2: when defining i18n routes, they have to be defined in ALL LOCALES
     * set in the locales config, otherwise an error is thrown while trying
     * to create a path for the language in which the route is not defined
     */
    #[Route(path: '/_register_', name: 'registration')]
    public function register(Request $request, TranslatorInterface $translator, EventDispatcherInterface $dispatcher, LoggerInterface $logger, EntityManager $entityManager, SecurityMailer $mailer, BotDetector $botDetector): Response
    {
        $session = $request->getSession();

        if ($request->query->has('_target_path')) {
            $this->saveTargetPath(
                session: $session,
                firewallName: 'main',
                uri: $request->query->get('_target_path')
            );
        }

        $user = new User();

        $user->setAccountLanguage(AccountLanguage::tryFrom($request->getLocale()));

        $form = $this->createForm(
            type: RegistrationForm::class,
            data: $user,
        );

        $form->handleRequest($request);

        // Unconfirmed account: redirect to validation email page
        if ($form->isSubmitted()) {
            
            $existingUser = $entityManager->getRepository(User::class)->findUserByIdentifier($user->getEmail());

            if ($existingUser && $existingUser->isNotVerified()) {
                $session->set('unverified_email', $existingUser->getEmail());

                return $this->redirectToRoute('resend_validation_email');
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {

            if ($botDetector->detectBotOnRegistrationForm($form, $request)) {
                return $this->render('@security/registration/register.html.twig', [
                    'form' => $form,
                ]);
            }

            // Temporarily set password to empty string to avoid error when persisting to DB.
            // Password is hashed in AccountCreatedSubscriber.
            $user->setPassword('');

            $user->setRolesAfterRegistration();

            $entityManager->persist($user);

            // This try / catch catches a doctrine (database) uniqueConstraint violation
            // As opposed to a symfony (form validation) uniqueEntity violation
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException $exception) {

                // should not get here if form validation is set correctly

                $form->get('email')->addError(new FormError($translator->trans('registration.email_already_used', domain: 'security')));

                $logger->error($exception->getMessage());

                return $this->render('@security/registration/register.html.twig', [
                    'form' => $form,
                ]);
            }

            $mailer->sendRegistrationValidationLink($user);

            $dispatcher->dispatch(new AccountCreatedEvent(
                user: $user,
                ip: $request->getClientIp(),
                password: $form->get('plainPassword')->getData(),
            ));

            $session->set('unverified_email', $user->getEmail());

            return $this->redirectToRoute('registration_check_your_email');
        }

        return $this->render('@security/registration/register.html.twig', [
            'form' => $form,
        ]);
    }

    // #[Route(path: '/testverif', name: 'registration_test')]
    // public function testVerificationLink(SecurityMailer $mailer) {

    //     $user = $this->getUser();

    //     $mailer->sendRegistrationValidationLink($user);

    //     return new Response(status: Response::HTTP_NO_CONTENT);
    // }

    /**
     * Expects 'unverified_email' session attribute to be set upstream
     */
    #[Route(path: '/check-your-email', name: 'registration_check_your_email')]
    public function checkYourEmail(Request $request, EmailVerifier $emailVerifier, bool $deliverabilityIssues)
    {
        $email = $request->getSession()->get('unverified_email');

        if ($email == null) {
            return $this->redirectToRoute('dashboard');
        }

        try {
            $user = $emailVerifier->getUnverifiedUserByEmail($email);
        } catch (EmailAlreadyVerifiedException $exception) {
            $this->addFlash('success', 'registration.email_already_verified', domain: 'security');
            return $this->redirectToRoute('dashboard');
        }

        if ($deliverabilityIssues && \str_contains($email, 'gmail')) {
            $gmailJunkWarning = true;
        }

        return $this->render('@security/registration/check_your_email.html.twig', [
            'email' => $email,
            'gmail_junk_warning' => $gmailJunkWarning ?? false,
        ]);
    }

    #[Route(path: '/verify/email', name: 'verify_email')]
    public function verifyUserEmail(Request $request, Security $security, LoginFormAuthenticator $loginFormAuthenticator, EmailVerifier $emailVerifier): Response
    {
        $session = $request->getSession();

        try {
            $user = $emailVerifier->getUnverifiedUser($request);
        } catch (EmailAlreadyVerifiedException $exception) {
            $this->addFlash('success', 'registration.email_already_verified', domain: 'security');
            return $this->redirectToRoute('dashboard');
        }

        try {
            $emailVerifier->handleEmailValidation($request, $user);
            $session->remove('unverified_email');
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('error', $exception->getReason());
            $session->set('unverified_email', $user->getEmail());
            return $this->redirectToRoute('resend_validation_email');
        }

        // calls LoginFormAuthenticator::authenticate
        // then LoginFormAuthenticator::onAuthenticationSuccess
        $redirectResponse = $security->login(
            user: $user,
            authenticatorName: $loginFormAuthenticator::class,
        );

        $this->addFlash('success', 'registration.account_created.success', domain: 'security');

        return $redirectResponse;
    }

    /**
     * Expects 'unverified_email' session attribute set upstream
     */
    #[Route(path: '/verify/resend', name: 'resend_validation_email')]
    public function resendValidationEmail(Request $request, SecurityMailer $mailer, EmailVerifier $emailVerifier): Response
    {
        $session = $request->getSession();

        $email = $session->get('unverified_email');

        if ($email == null) {
            return $this->redirectToRoute('dashboard');
        }

        try {
            $user = $emailVerifier->getUnverifiedUserByEmail($email);
        } catch (EmailAlreadyVerifiedException $exception) {
            $this->addFlash('success', 'registration.email_already_verified', domain: 'security');
            return $this->redirectToRoute('dashboard');
        }

        if (isset($_POST['email']) && $_POST['email'] == $session->get('unverified_email')) {

            $mailer->sendRegistrationValidationLink($user);

            return $this->redirectToRoute('registration_check_your_email');
        }

        return $this->render('@security/registration/resend_validation_email.html.twig', [
            'email' => $email
        ]);
    }
}
