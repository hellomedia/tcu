<?php

namespace Admin\Controller\Planning;

use Admin\Mailer\ConfirmationMailer;
use App\Controller\BaseController;
use App\Entity\MatchParticipant;
use App\Entity\ParticipantConfirmationInfo;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

class ConfirmationController extends BaseController
{
    #[IsCsrfTokenValid('send-email', tokenKey: 'token')]
    #[Route('/confirmation/{id:participant}/send-email', name: 'confirmation_send_email', methods: ['POST'])]
    public function sendEmail(MatchParticipant $participant, ConfirmationMailer $mailer, EntityManager $entityManager): Response
    {
        $confirmationInfo = $participant->getConfirmationInfo();

        if (!$confirmationInfo) {
            $confirmationInfo = (new ParticipantConfirmationInfo())->setParticipant($participant);
            $entityManager->persist($confirmationInfo);
            $participant->setConfirmationInfo($confirmationInfo);
        }

        $currentConfirmationInfo = $confirmationInfo;

        if ($participant->getUser()) {

            $player = $participant->getPlayer();
            $unconfirmedScheduledMatchs = $player->getUnconfirmedScheduledMatchs();

            if ($mailer->sendScheduleNotification($participant)) {

                foreach ($unconfirmedScheduledMatchs as $match) {
                    $participant = $match->getParticipant($player->getUser());
                    $confirmationInfo = $participant->getConfirmationInfo();

                    if (!$confirmationInfo) {
                        $confirmationInfo = (new ParticipantConfirmationInfo())->setParticipant($participant);
                        $entityManager->persist($confirmationInfo);
                        $participant->setConfirmationInfo($confirmationInfo);
                    }
                    
                    $confirmationInfo->setIsEmailSent(true);
                    $confirmationInfo->setEmailSentAt(new DateTimeImmutable());
                }
            }
        }

        $entityManager->flush();
        
        return $this->render('@admin/confirmation/send_notification_email_success.html.twig', [
            'data' => $currentConfirmationInfo,
        ]);
    }
}