<?php

namespace Admin\Controller;

use Admin\Mailer\InvitationMailer;
use App\Controller\BaseController;
use App\Entity\Player;
use App\Repository\RegistrationRepository;
use App\Repository\SeasonRepository;
use App\Repository\UserRepository;
use App\Service\SeasonContext;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Invitations des personnes dont un admin a créé le compte (PlayerAccountManager) :
 * email avec un lien de connexion qui mène à "Choisir mon mot de passe" (Admin\Mailer\InvitationMailer).
 *
 * Envoyées à la demande d'un admin, depuis la page Invitations (index), la liste des joueurs (PlayerCrudController)
 * ou la page "Compléter les joueurs" (PlayerBatchController), jamais à la création du compte :
 * le temps de vérifier les emails, et pour ne pas envoyer 50 emails à cause d'un enregistrement.
 */
#[IsGranted('ROLE_EDITOR')]
class InvitationController extends BaseController
{
    public const CSRF_TOKEN_ID = 'invitation';

    /** sections de la page Invitations, dans l'ordre d'affichage */
    private const STATUSES = ['to_invite', 'invited', 'active', 'no_account'];

    /**
     * Inscrits aux interfacs de la saison sélectionnée, par état de leur compte :
     * à inviter, invités sans mot de passe choisi, actifs, sans compte (pas d'email)
     */
    #[Route('/invitations', name: 'admin_invitations', methods: ['GET'], defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function index(SeasonContext $seasonContext, RegistrationRepository $registrationRepository, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $season = $seasonContext->getSelected();
        $sections = array_fill_keys(self::STATUSES, []);
        $expiredBefore = new \DateTimeImmutable(sprintf('-%d days', InvitationMailer::LIFETIME_DAYS));

        if ($season !== null) {
            foreach ($registrationRepository->findInterfacsBySeason($season) as $registration) {
                $user = $registration->getPlayer()->getUser();
                $status = match (true) {
                    $user === null => 'no_account',
                    !$user->mustChoosePassword() => 'active',
                    $user->getInvitedAt() === null => 'to_invite',
                    default => 'invited',
                };

                $sections[$status][] = [
                    'registration' => $registration,
                    'expired' => $status === 'invited' && $user->getInvitedAt() < $expiredBefore,
                ];
            }
        }

        return $this->render('@admin/invitation/index.html.twig', [
            'season' => $season,
            'sections' => $sections,
            'token' => $csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue(),
            'to_invite' => array_map(fn (array $row) => $row['registration']->getPlayer()->getUser(), $sections['to_invite']),
            'lifetime_days' => InvitationMailer::LIFETIME_DAYS,
        ]);
    }

    /**
     * Un joueur, même déjà invité (lien expiré, email corrigé)
     */
    #[Route('/players/{id:player}/invite', name: 'admin_player_invite', requirements: ['id' => '\d+'], methods: ['GET'], defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function invite(Player $player, Request $request, InvitationMailer $mailer, EntityManagerInterface $entityManager, SeasonContext $seasonContext): Response
    {
        $this->checkToken($request);

        $user = $player->getUser();

        if ($user === null || !$user->mustChoosePassword()) {
            $this->addFlash('warning', sprintf('%s n\'a pas de compte à inviter.', $player->getName()));
        } else {
            $mailer->sendInvitation($user, $seasonContext->getSelected());
            $entityManager->flush();

            $this->addFlash('success', sprintf('Invitation envoyée à %s (%s).', $player->getName(), $user->getEmail()));
        }

        return $this->redirectBack($request);
    }

    /**
     * Tous les comptes de joueurs pas encore invités, ou ceux des inscrits aux interfacs d'une saison (?season=, page Invitations)
     */
    #[Route('/players/invite-pending', name: 'admin_players_invite_pending', methods: ['GET'], defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function invitePending(Request $request, UserRepository $userRepository, SeasonRepository $seasonRepository, SeasonContext $seasonContext, InvitationMailer $mailer, EntityManagerInterface $entityManager): Response
    {
        $this->checkToken($request);

        $season = null;
        if ($request->query->has('season')) {
            $season = $seasonRepository->find($request->query->getInt('season')) ?? throw $this->createNotFoundException();
        }
        $users = $userRepository->findPlayersToInvite($season);

        // version interfacs de l'email pour les inscrits aux interfacs de la saison (Admin\Mailer\InvitationMailer)
        $emailSeason = $season ?? $seasonContext->getSelected();
        foreach ($users as $user) {
            $mailer->sendInvitation($user, $emailSeason);
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
