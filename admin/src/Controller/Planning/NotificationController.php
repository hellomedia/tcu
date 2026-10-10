<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use Admin\Mailer\ConfirmationMailer;
use App\Controller\BaseController;
use App\Entity\Player;
use App\Service\MatchNotifications;
use App\Service\SeasonContext;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Page Notifications : qui doit encore être prévenu (nouveaux matchs, horaires modifiés, annulations),
 * qui n'a pas confirmé. Envois manuels, un joueur à la fois ou tous d'un coup.
 */
class NotificationController extends BaseController
{
    public function __construct(
        private EntityManager $entityManager,
        private MatchNotifications $notifications,
        private ConfirmationMailer $mailer,
        private SeasonContext $seasonContext,
    ) {
    }

    #[Route('/planning/notifications', name: 'admin_planning_notifications', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function index(): Response
    {
        $season = $this->seasonContext->getSelected();
        $rows = $season ? $this->notifications->overview($season) : [];

        $toNotify = 0;
        $toRemind = 0;
        foreach ($rows as $row) {
            if ($row['player']->getUser() === null) {
                continue;
            }
            if ($row['collected']['pending'] > 0) {
                $toNotify++;
            } elseif ($row['collected']['unconfirmed'] !== []) {
                $toRemind++;
            }
        }

        return $this->render('@admin/notification/index.html.twig', [
            'season' => $season,
            'rows' => $rows,
            'to_notify' => $toNotify,
            'to_remind' => $toRemind,
        ]);
    }

    #[IsCsrfTokenValid('notify', tokenKey: 'token')]
    #[Route('/planning/notifications/{id:player}/send', name: 'admin_planning_notifications_send', methods: ['POST'])]
    public function send(Player $player): Response
    {
        $sent = $this->notify([$player]);

        $this->addFlash($sent ? 'success' : 'warning', $sent ? sprintf('Email envoyé à %s', $player) : sprintf('Rien à envoyer à %s', $player));

        return $this->redirectToRoute('admin_planning_notifications');
    }

    #[IsCsrfTokenValid('notify', tokenKey: 'token')]
    #[Route('/planning/notifications/{id:player}/remind', name: 'admin_planning_notifications_remind', methods: ['POST'])]
    public function remind(Player $player): Response
    {
        $sent = $this->remindPlayers([$player]);

        $this->addFlash($sent ? 'success' : 'warning', $sent ? sprintf('Rappel envoyé à %s', $player) : sprintf('Rien à rappeler à %s', $player));

        return $this->redirectToRoute('admin_planning_notifications');
    }

    #[IsCsrfTokenValid('notify', tokenKey: 'token')]
    #[Route('/planning/notifications/send-all', name: 'admin_planning_notifications_send_all', methods: ['POST'])]
    public function sendAll(): Response
    {
        $sent = $this->notify($this->notifications->getPlayers($this->seasonContext->getSelected()));

        $this->addFlash('success', sprintf('%d email%s envoyé%s', $sent, $sent > 1 ? 's' : '', $sent > 1 ? 's' : ''));

        return $this->redirectToRoute('admin_planning_notifications');
    }

    #[IsCsrfTokenValid('notify', tokenKey: 'token')]
    #[Route('/planning/notifications/remind-all', name: 'admin_planning_notifications_remind_all', methods: ['POST'])]
    public function remindAll(): Response
    {
        $sent = $this->remindPlayers($this->notifications->getPlayers($this->seasonContext->getSelected()));

        $this->addFlash('success', sprintf('%d rappel%s envoyé%s', $sent, $sent > 1 ? 's' : '', $sent > 1 ? 's' : ''));

        return $this->redirectToRoute('admin_planning_notifications');
    }

    /**
     * @param Player[] $players
     *
     * @return int emails sent
     */
    private function notify(array $players): int
    {
        $season = $this->seasonContext->getSelected();
        $now = new DateTimeImmutable();
        $sent = 0;

        foreach ($players as $player) {
            if ($this->mailer->sendScheduleNotification($player, $season, $this->notifications)) {
                $this->notifications->markNotified($this->notifications->collect($player, $season), $now);
                $sent++;
            }
        }

        $this->entityManager->flush();

        return $sent;
    }

    /**
     * @param Player[] $players
     */
    private function remindPlayers(array $players): int
    {
        $season = $this->seasonContext->getSelected();
        $now = new DateTimeImmutable();
        $sent = 0;

        foreach ($players as $player) {
            if ($this->mailer->sendReminder($player, $season, $this->notifications)) {
                $this->notifications->markReminded($this->notifications->collect($player, $season), $now);
                $sent++;
            }
        }

        $this->entityManager->flush();

        return $sent;
    }
}
