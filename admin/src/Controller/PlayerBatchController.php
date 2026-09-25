<?php

namespace Admin\Controller;

use Admin\Form\PlayerBatchType;
use Admin\Mailer\InvitationMailer;
use App\Controller\BaseController;
use App\Entity\Player;
use App\Repository\PlayerRepository;
use App\Repository\UserRepository;
use App\Service\PlayerAccountManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Compléter les joueurs en une fois : nom, prénom, téléphone, email, n° d'affiliation
 * sur une seule page, un formulaire, un enregistrement.
 *
 * Par défaut, la page liste les joueurs à qui il manque un téléphone, un email (compte) ou un n° d'affiliation ;
 * les joueurs complétés disparaissent de la liste à l'enregistrement. "Tous les joueurs" pour corriger les noms.
 *
 * Un email sur un joueur sans compte crée le compte : PlayerAccountManager.
 */
#[IsGranted('ROLE_EDITOR')]
class PlayerBatchController extends BaseController
{
    private const FILTERS = ['tel', 'email', 'affiliation'];

    #[Route('/players/batch', name: 'admin_players_batch', methods: ['GET', 'POST'], defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function batch(Request $request, PlayerRepository $playerRepository, UserRepository $userRepository, EntityManagerInterface $entityManager, PlayerAccountManager $accountManager, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $filters = $this->getFilters($request);

        // à l'enregistrement, les joueurs sont ceux du formulaire envoyé, pas ceux du filtre :
        // la liste peut avoir changé entre l'affichage et l'envoi
        $submittedIds = array_keys($request->request->all('player_batch')['players'] ?? []);
        $players = $request->isMethod('POST')
            ? $playerRepository->findAllWithAccount($submittedIds)
            : $this->filter($playerRepository->findAllWithAccount(), $filters);

        $form = $this->createForm(PlayerBatchType::class, ['players' => $players], [
            'action' => $this->generateUrl('admin_players_batch', $this->filterQuery($filters)),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $accounts = 0;

            foreach ($players as $player) {
                $accounts += (int) $accountManager->sync($player);
            }

            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                // deux lignes avec le même nouveau n° d'affiliation ou le même nouvel email
                $this->addFlash('danger', 'Rien n\'a été enregistré : deux joueurs ont le même n° d\'affiliation ou le même email.');

                return $this->redirectToRoute('admin_players_batch', $this->filterQuery($filters));
            }

            $this->addFlash('success', sprintf('%d joueurs enregistrés%s.', count($players), $accounts ? sprintf(', %d comptes créés ou liés', $accounts) : ''));

            return $this->redirectToRoute('admin_players_batch', $this->filterQuery($filters));
        }

        return $this->render('@admin/player/batch.html.twig', [
            'form' => $form,
            'players' => $players,
            'filters' => $filters,
            // comptes créés pas encore invités : Admin\Controller\InvitationController
            'to_invite' => $userRepository->findPlayersToInvite(),
            'invitation_token' => $csrfTokenManager->getToken(InvitationController::CSRF_TOKEN_ID)->getValue(),
            'lifetime_days' => InvitationMailer::LIFETIME_DAYS,
        ]);
    }

    /**
     * Filtres de la page, dans l'URL. Sans paramètre : les joueurs à qui il manque quelque chose.
     *
     * @return array{tous: bool, tel: bool, email: bool, affiliation: bool}
     */
    private function getFilters(Request $request): array
    {
        $filters = ['tous' => $request->query->getBoolean('tous')];

        foreach (self::FILTERS as $name) {
            $filters[$name] = $request->query->getBoolean($name);
        }

        // rien de coché : tout ce qui manque
        if (!$filters['tous'] && !in_array(true, $filters, true)) {
            $filters = ['tous' => false] + array_fill_keys(self::FILTERS, true);
        }

        return $filters;
    }

    /**
     * Les filtres cochés, pour l'URL : ?tel=1&email=1
     */
    private function filterQuery(array $filters): array
    {
        return array_fill_keys(array_keys(array_filter($filters)), 1);
    }

    /**
     * @param Player[] $players
     * @return Player[]
     */
    private function filter(array $players, array $filters): array
    {
        if ($filters['tous']) {
            return $players;
        }

        return array_filter($players, fn(Player $player) =>
            ($filters['tel'] && !$player->getPhone())
            || ($filters['email'] && !$player->hasAccount())
            || ($filters['affiliation'] && !$player->getAffiliationNumber())
        );
    }
}
