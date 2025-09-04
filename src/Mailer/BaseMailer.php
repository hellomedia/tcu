<?php

namespace App\Mailer;

use Doctrine\ORM\EntityManager;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class BaseMailer
{
    public function __construct(
        protected string $siteName,
        protected string $emailDomain,
        protected MailerInterface $mailer,
        protected UrlGeneratorInterface $router,
        protected TranslatorInterface $translator,
        protected EntityManager $entityManager,
        private BodyRendererInterface $bodyRenderer,
    ) {
    }

    protected function send(TemplatedEmail $email, ?string $from = 'postmaster'): void
    {
        $this->_setSender(
            emailMessage: $email,
            from: $from,
        );

        $baseContext = [
            'siteName' => $this->siteName,
            'siteUrl' => $this->generateAbsoluteUrl('homepage'),
        ];

        $email->context(array_merge($baseContext, $email->getContext()));

        // EMAIL PRE-RENDERING
        // pre-render email before sending it to the queue
        // NOTE
        // https://symfony.com/doc/current/mailer.html#sending-messages-async
        // When sending an email asynchronously (we do), its instance must be
        // serializable. This is always the case for Email instances, but when
        // sending a TemplatedEmail, you must ensure that the context is
        // serializable. **If you have non-serializable variables (we do)**,
        // like Doctrine entities, either replace them with more specific variables
        // or *** render the email before calling $mailer->send($email) ***
        $this->bodyRenderer->render($email);

        $this->mailer->send($email);
    }

    private function _setSender(TemplatedEmail $emailMessage, string $from): void
    {
        $senderEmail = $from . '@' . $this->emailDomain;
        $senderName = $this->siteName;

        $emailMessage->from(new Address($senderEmail, $senderName));
    }

    protected function getDomain(): string
    {
        return $this->emailDomain;
    }

    protected function generateAbsoluteUrl(string $route, array $parameters = []): string
    {
        return $this->router->generate(
            name: $route,
            parameters: $parameters,
            referenceType: UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
