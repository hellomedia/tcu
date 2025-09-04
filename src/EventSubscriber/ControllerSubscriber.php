<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ControllerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private EntityManager $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController'
        ];
    }

    /**
     *  - Update user activity
     *  - Fire pre-execute method if it exists
     *  - Fire denyAccessIfAccountClosed method if it exists
     */
    public function onKernelController(ControllerEvent $event)
    {
        if (HttpKernelInterface::MAIN_REQUEST == $event->getRequestType()) {
            $controller = $event->getController();
            // when a controller class defines multiple action methods,
            // the controller is returned as [$controllerInstance, 'methodName']
            if (is_array($controller)) {
                $controller = $controller[0];
            }

            if (isset($controller)) {

                $this->_setTheme($event->getRequest());

                if (method_exists($controller, 'preExecute')) {
                    $controller->preExecute();
                }

                if (method_exists($controller, 'denyAccessIfAccountClosed')) {
                    // AGAINST EXISTING SESSION and other unintended cases
                    // But actually... this should be handled by User::isEqualTo() in UserSecurityTrait
                    // TODO: test and remove
                    $controller->denyAccessIfAccountClosed();
                }
            }
        }
    }

    private function _setTheme(Request $request): void
    {
        $theme = null;
        $setTheme = false;

        if ($request->query->has('theme')) {
            // theme set from query
            $theme = $request->query->get('theme');
            
            $setTheme = true;
        }

        if (!($theme)) {
            // theme retrieved from session
            $theme = $request->getSession()->get('theme');
        }

        if (!($theme)) {
            // theme set from headers
            $acceptHeader = $request->headers->get('accept');
            $theme = (str_contains($acceptHeader, 'prefers-color-scheme: dark')) ? 'dark' : 'light';

            $setTheme = true;
        }

        if (!($theme)) {
            // theme set to default
            $theme = 'light';

            $setTheme = true;
        }

        if ($setTheme) {

            $request->getSession()->set('theme', $theme);
            // Important: change in session data is NOT saved correctly inside an AJAX request (turbo drive)
            // A workaround would be to save manually with $this->requestStack->getSession()->save()
            // but could not get it to work.
            // The other solution is to force this request to be a full page load
            // In that case, Symfony handles saving the session data correctly.
            // https://chatgpt.com/share/67e236c1-f758-8012-a848-3b114a5b721e
        }

        $request->attributes->set('theme', $theme);
    }
}
