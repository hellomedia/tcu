<?php

namespace App\Controller\AccountArea;

use App\Controller\BaseController;
use App\Entity\Practice;
use App\Entity\PracticeParticipant;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Réponse d'un joueur à un entraînement de sa poule : j'y serai / je ne viens pas (voir le tableau de bord)
 */
#[IsGranted('ROLE_USER')]
class PracticeController extends BaseController
{
    #[IsCsrfTokenValid('practice-answer', 'token')]
    #[Route('/mes/entrainements/{id:practice}/reponse', name: 'my_practice_answer', methods: ['POST'])]
    public function answer(Practice $practice, Request $request, EntityManager $entityManager): Response
    {
        $player = $this->getUser()->getPlayer();

        if ($player === null || !$practice->concerns($player)) {
            throw $this->createAccessDeniedException('Cet entraînement ne concerne pas ta poule.');
        }

        if ($practice->isCancelled() || !$practice->isUpcoming()) {
            $this->addFlash('warning', 'Cet entraînement n\'est plus à venir.');

            return $this->redirectToRoute('dashboard');
        }

        $participant = $practice->getParticipant($player);

        if ($participant === null) {
            $participant = (new PracticeParticipant())->setPlayer($player);
            $practice->addParticipant($participant);
            $entityManager->persist($participant);
        }

        // 'yes' / 'no' ; anything else clears the answer
        $participant->answer(match ($request->request->get('coming')) {
            'yes' => true,
            'no' => false,
            default => null,
        });

        $entityManager->flush();

        $this->addFlash('success', match ($participant->isComing()) {
            true => 'C\'est noté, à bientôt sur le court !',
            false => 'C\'est noté, merci d\'avoir répondu.',
            null => 'Réponse effacée.',
        });

        return $this->redirectToRoute('dashboard', ['_fragment' => 'practice-' . $practice->getId()]);
    }
}
