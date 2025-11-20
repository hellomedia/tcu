<?php

namespace Pack\Security;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class BotDetector
{
    public function __construct(
        private $environment,
        private RequestStack $requestStack,
    ) {}

    /**
     * Detect bot from HTTP_ACCEPT_LANGUAGE and HTTP_USER_AGENT
     * Tag session with :
     *   - h for human
     *   - r for bot
     */
    public function isBot(): bool
    {
        $request = $this->requestStack->getMainRequest();
        $session = $this->requestStack->getSession();

        if ($session->has('type')) {
            return $session->get('type') == 'r';
        }

        // search engines don't set HTTP_ACCEPT_LANGUAGE
        // https://stackoverflow.com/questions/368570/search-engines-and-browser-accept-language
        if (empty($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {

            $session->set('type', 'r');

            return true;
        }

        $ua_string = $request->headers->get('HTTP_USER_AGENT');

        $robots = [
            'antibot',
            'appie1.1',
            'archive',
            'ask',
            'askjeeves',
            'baiduspider',
            'converacrawler',
            'deepIndex',
            'dloader',
            'exabot',
            'fast',
            'fluffy',
            'gigabot',
            'girafabot',
            'google',
            'google adsence',
            'googlebot',
            'googlebot-image',
            'grub.org',
            'henrilerobotmirago',
            'heritrix',
            'holmes',
            'httrack',
            'ia_archiver',
            'ichiro',
            'inktomi slurp',
            'java',
            'larbin',
            'lwp-trivial',
            'mediapartners-google',
            'mj12bot',
            'msnbot',
            'msnbot-media',
            'msiecrawler',
            'msrbot',
            'netresearchserver',
            'nimblecrawler',
            'nutch',
            'nutchcvs',
            'openbot',
            'openfind',
            'picsearch',
            'pompos',
            'psbot',
            'python-urllib',
            'sbider',
            'seekbot',
            'scooter',
            'shinchakubin',
            'slurp',
            'spider',
            'stackramber',
            'surveybot',
            'szukacz',
            'teoma',
            'voila',
            'voilabot',
            'voyager',
            'webcrawler',
            'xenu link sleuth',
            'yandex',
            'yahoo',
            'yahoo!',
            'yahoo-mmcrawler',
            'yahooseeker',
            'zyborg',
        ];

        foreach ($robots as $bot) {
            if ($ua_string != null && stripos($ua_string, $bot) !== false) {

                $session->set('type', 'r');

                return true;
            }
        }

        $session->set('type', 'h');

        return false;
    }

    public function isNotBot(): bool
    {
        return !$this->isBot();
    }

    public function detectBotOnRegistrationForm(FormInterface $form, Request $request): bool
    {
        return $this->isBot() || $this->_isSuspiciousRegistrationForm($form, $request);
    }

    public function detectBotOnLoginForm(?string $password): bool
    {
        return $this->isBot() || $this->_isSuspiciousLoginForm($password);
    }

    private function _isSuspiciousRegistrationForm(FormInterface $form, Request $request): bool
    {
        if ($this->environment == 'dev') {
            return false;
        }

        if ($form->isSubmitted() == false) {
            return false;
        }

        // Required field
        if ($form->get('plainPassword')->getData() === null) {
            $this->requestStack->getSession()->set('type', 'r');

            return true;
        }

        // Honeypot
        if ($form->get('occupation')->getData() != null) {
            $this->requestStack->getSession()->set('type', 'r');

            return true;
        }

        $secondsSincePageLoad = (int) (new \DateTime())->format('U') - (int) $request->request->get('time-check');

        if ($secondsSincePageLoad <= 2) {
            $this->requestStack->getSession()->set('type', 'r');

            return true;
        }

        if ($secondsSincePageLoad <= 3) {
            return true;
        }

        return false;
    }

    /**
     * Bots seem to want to login with null password:
     */
    private function _isSuspiciousLoginForm(?string $password): bool
    {
        if ($this->environment == 'dev') {
            return false;
        }

        if ($password === null) {

            $this->requestStack->getSession()->set('type', 'r');

            return true;
        }

        return false;
    }
}
