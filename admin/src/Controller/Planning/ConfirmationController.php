<?php

namespace Admin\Controller\Planning;

use Admin\Mailer\ConfirmationMailer;
use App\Controller\BaseController;
use App\Entity\MatchParticipant;
use App\Entity\ParticipantConfirmationInfo;
use App\Service\MatchNotifications;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Emails depuis la modale "Infos de confirmation" d'un match (un joueur à la fois)
 */
class ConfirmationController extends BaseController
{
    public function __construct(
        private EntityManager $entityManager,
        private MatchNotifications $notifications,
        private ConfirmationMailer $mailer,
    ) {
    }

    /**
     * Notification : tout ce qui est en attente pour ce joueur (ce match et les autres)
     */
    #[IsCsrfTokenValid('send-email', tokenKey: 'token')]
    #[Route('/confirmation/{id:participant}/send-email', name: 'confirmation_send_email', methods: ['POST'])]
    public function sendEmail(MatchParticipant $participant): Response
    {
        $player = $participant->getPlayer();
        $season = $participant->getMatch()->getSeason();
        $updated = [];

        if ($this->mailer->sendScheduleNotification($player, $season, $this->notifications)) {
            $updated = $this->notifications->markNotified($this->notifications->collect($player, $season), new DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $this->renderInfo($participant, $updated);
    }

    /**
     * Rappel des matchs communiqués et pas encore confirmés
     */
    #[IsCsrfTokenValid('send-reminder', tokenKey: 'token')]
    #[Route('/confirmation/{id:participant}/send-reminder', name: 'confirmation_send_reminder', methods: ['POST'])]
    public function sendReminder(MatchParticipant $participant): Response
    {
        $player = $participant->getPlayer();
        $season = $participant->getMatch()->getSeason();
        $updated = [];

        if ($this->mailer->sendReminder($player, $season, $this->notifications)) {
            $updated = $this->notifications->markReminded($this->notifications->collect($player, $season), new DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $this->renderInfo($participant, $updated);
    }

    /**
     * @param MatchParticipant[] $updatedParticipants
     */
    private function renderInfo(MatchParticipant $participant, array $updatedParticipants): Response
    {
        // the modal row needs an info object even when nothing was sent
        $info = $participant->getConfirmationInfo() ?? (new ParticipantConfirmationInfo())->setParticipant($participant);

        return $this->render('@admin/confirmation/send_notification_email_success.html.twig', [
            'data' => $info,
            'updated_participants' => $updatedParticipants,
        ]);
    }
}
