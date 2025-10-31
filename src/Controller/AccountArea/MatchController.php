<?php

namespace App\Controller\AccountArea;

use App\Controller\BaseController;
use App\Entity\InterfacMatch;
use App\Entity\ParticipantConfirmationInfo;
use App\Repository\InterfacMatchRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MatchController extends BaseController
{
    #[Route('/mes/matchs', name: 'my_matchs')]
    public function index(InterfacMatchRepository $matchRepository): Response
    {
        $this->addBreadcrumb('Dashboard', 'dashboard');
        $this->addBreadcrumb('Mes matchs à venir');

        $scheduledMatchs = $matchRepository->findUpcomingMatchs($this->getUser());
        $nonScheduledMatchs = $matchRepository->findNonScheduledMatchs($this->getUser());

        return $this->render('account_area/interfacs/match/my_matchs.html.twig', [
            'scheduled_matchs' => $scheduledMatchs,
            'non_scheduled_matchs' => $nonScheduledMatchs,
        ]);
    }

    #[IsGranted('EDIT', 'match')]
    #[IsCsrfTokenValid('confirm-schedule', 'token')]
    #[Route('/mes/matchs/{id:match}/confirmer', name: 'my_matchs_confirm_schedule', methods: ['POST'])]
    public function confirmSchedule(InterfacMatch $match, EntityManager $entityManager): Response
    {
        $user = $this->getUser();
        $participant = $match->getParticipant($user);
        $confirmationInfo = $participant->getConfirmationInfo();

        if (!$confirmationInfo) {
            $confirmationInfo = (new ParticipantConfirmationInfo())->setParticipant($participant);
            $entityManager->persist($confirmationInfo);
            $participant->setConfirmationInfo($confirmationInfo);
        }
        
        $confirmationInfo->setIsConfirmedByPlayer(true);
        $confirmationInfo->setConfirmedByPlayerAt(new DateTimeImmutable());

        $entityManager->flush();

        $feedback = 'Horaire confirmé';

        return $this->render('account_area/interfacs/match/confirm_schedule_success.html.twig', [
            'feedback' => $feedback,
            'match' => $match,
        ]);

    }
}