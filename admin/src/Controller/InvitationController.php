<?php

namespace Admin\Controller;

use Admin\Mailer\InvitationMailer;
use App\Controller\BaseController;
use App\Entity\Player;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Invitations des personnes dont un admin a créé le compte (PlayerAccountManager) :
 * email avec un lien de connexion qui mène à "Choisir mon mot de passe" (Admin\Mailer\InvitationMailer).
 *
 * Envoyées à la demande d'un admin, depuis la liste des joueurs (PlayerCrudController)
 * ou la page "Compléter les joueurs" (PlayerBatchController), jamais à la création du compte :
 * le temps de vérifier les emails, et pour ne pas envoyer 50 emails à cause d'un enregistrement.
 */
#[IsGranted('ROLE_EDITOR')]
class InvitationController extends BaseController
{
    public const CSRF_TOKEN_ID = 'invitation';

    /**
     * Un joueur, même déjà invité (lien expiré, email corrigé)
     */
    #[Route('/players/{id:player}/invite', name: 'admin_player_invite', requirements: ['id' => '\d+'], methods: ['GET'], defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function invite(Player $player, Request $request, InvitationMailer $mailer, EntityManagerInterface $entityManager): Response
    {
        $this->checkToken($request);

        $user = $player->getUser();

        if ($user === null || !$user->mustChoosePassword()) {
            $this->addFlash('warning', sprintf('%s n\'a pas de compte à inviter.', $player->getName()));
        } else {
            $mailer->sendInvitation($user);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Invitation envoyée à %s (%s).', $player->getName(), $user->getEmail()));
        }

        return $this->redirectBack($request);
    }

    /**
     * Tous les comptes de joueurs pas encore invités
     */
    #[Route('/players/invite-pending', name: 'admin_players_invite_pending', methods: ['GET'], defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function invitePending(Request $request, UserRepository $userRepository, InvitationMailer $mailer, EntityManagerInterface $entityManager): Response
    {
        $this->checkToken($request);

        $users = $userRepository->findPlayersToInvite();

        foreach ($users as $user) {
            $mailer->sendInvitation($user);
        }

        $entityManager->flush();

        $this->addFlash($users ? 'success' : 'warning', match (count($users)) {
            0 => 'Aucun compte à inviter.',
            1 => sprintf('Invitation envoyée à %s.', $users[0]->getEmail()),
            default => sprintf('%d invitations envoyées.', count($users)),
        });

        return $this->redirectBack($request);
    }

    // liens GET (actions de la liste des joueurs) : le jeton est dans l'URL
    private function checkToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->query->get('token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function redirectBack(Request $request): Response
    {
        $referer = $request->headers->get('referer');

        return $referer && str_starts_with($referer, $request->getSchemeAndHttpHost())
            ? $this->redirect($referer)
            : $this->redirectToRoute('admin_player_index');
    }
}
