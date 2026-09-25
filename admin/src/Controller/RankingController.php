<?php

namespace Admin\Controller;

use App\Controller\BaseController;
use App\Entity\Registration;
use App\Enum\Ranking;
use App\Repository\RegistrationRepository;
use App\Service\Ranking\RankingProposal;
use App\Service\Ranking\RankingUpdater;
use App\Service\SeasonContext;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Récupération des classements sur mon-classement-tennis.be, pour les inscriptions de la saison sélectionnée.
 *
 * 1) la page liste les inscriptions
 * 2) "Lancer" : la page demande les classements un joueur à la fois (fetch), avec une pause entre deux joueurs.
 *    Rien n'est modifié : les classements récupérés sont gardés en session.
 * 3) un admin vérifie l'aperçu, puis "Appliquer" enregistre les classements cochés (apply),
 *    à partir de la session : la page ne peut pas envoyer un classement qui n'a pas été récupéré.
 *
 * Une centaine de pages prend 2 à 3 minutes : trop long pour une seule requête, d'où le joueur par joueur.
 */
#[IsGranted('ROLE_EDITOR')]
class RankingController extends BaseController
{
    private const SESSION_KEY = 'rankings_fetch';
    private const SESSION_LAST_FETCH = 'rankings_last_fetch';

    // on reste discret : pause minimum entre deux pages du site, quoi que fasse le navigateur
    private const MIN_DELAY = 1.0;

    private const MODES = ['previsionnel' => true, 'officiel' => false];

    #[Route('/rankings/{mode}', name: 'admin_rankings', requirements: ['mode' => 'previsionnel|officiel'], methods: ['GET'], defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function index(string $mode, Request $request, RegistrationRepository $repository, SeasonContext $seasonContext): Response
    {
        $season = $seasonContext->getSelected();

        if ($season === null) {
            throw $this->createNotFoundException('Pas de saison.');
        }

        $registrations = array_filter(
            $repository->findBySeasonIndexedByPlayer($season),
            fn(Registration $registration) => !$registration->isDismissed(),
        );
        usort($registrations, fn(Registration $a, Registration $b) => [$a->getPlayer()->getLastname(), $a->getPlayer()->getFirstname()] <=> [$b->getPlayer()->getLastname(), $b->getPlayer()->getFirstname()]);

        // nouvelle récupération
        $request->getSession()->set(self::SESSION_KEY, ['season' => $season->getId(), 'mode' => $mode, 'items' => []]);

        return $this->render('@admin/ranking/fetch.html.twig', [
            'season' => $season,
            'mode' => $mode,
            'previsional' => self::MODES[$mode],
            'source' => RankingUpdater::getSource(self::MODES[$mode]),
            'registrations' => $registrations,
        ]);
    }

    /**
     * Un joueur à la fois. Ne modifie rien.
     */
    #[IsCsrfTokenValid('rankings-fetch', tokenKey: 'token')]
    #[Route('/rankings/{mode}/fetch/{id:registration}', name: 'admin_rankings_fetch', requirements: ['mode' => 'previsionnel|officiel'], methods: ['POST'])]
    public function fetch(string $mode, Registration $registration, Request $request, RankingUpdater $updater, SeasonContext $seasonContext): JsonResponse
    {
        $session = $request->getSession();
        $store = $session->get(self::SESSION_KEY);

        if ($registration->getSeason() !== $seasonContext->getSelected()
            || !is_array($store) || $store['season'] !== $registration->getSeason()->getId() || $store['mode'] !== $mode
        ) {
            return new JsonResponse(['status' => RankingProposal::PROBLEM, 'remark' => 'La saison ou le type de classement a changé : recharger la page.'], Response::HTTP_CONFLICT);
        }

        if ($registration->getPlayer()->getAffiliationNumber() !== null) {
            $wait = self::MIN_DELAY - (microtime(true) - (float) $session->get(self::SESSION_LAST_FETCH, 0));
            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
        }

        $proposal = $updater->propose($registration, self::MODES[$mode]);

        $session->set(self::SESSION_LAST_FETCH, microtime(true));

        if ($proposal->isApplicable()) {
            $store['items'][$registration->getId()] = [
                'ranking' => $proposal->ranking->value,
                'fetchedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ];
        } else {
            unset($store['items'][$registration->getId()]);
        }
        $session->set(self::SESSION_KEY, $store);

        return new JsonResponse([
            'status' => $proposal->status,
            'ranking' => $proposal->ranking?->value,
            'siteName' => $proposal->siteName,
            'remark' => $proposal->remark,
        ]);
    }

    /**
     * Enregistre les classements cochés, à partir de ce qui a été récupéré (session)
     */
    #[IsCsrfTokenValid('rankings-apply', tokenKey: 'token')]
    #[Route('/rankings/{mode}/apply', name: 'admin_rankings_apply', requirements: ['mode' => 'previsionnel|officiel'], methods: ['POST'])]
    public function apply(string $mode, Request $request, RegistrationRepository $repository, RankingUpdater $updater, SeasonContext $seasonContext, EntityManagerInterface $entityManager): Response
    {
        $session = $request->getSession();
        $store = $session->get(self::SESSION_KEY);
        $season = $seasonContext->getSelected();

        if ($season === null || !is_array($store) || $store['season'] !== $season->getId() || $store['mode'] !== $mode) {
            return $this->redirectToRoute('admin_rankings', ['mode' => $mode]);
        }

        $ids = array_map('intval', $request->request->all('registrations'));

        foreach ($ids as $id) {
            $item = $store['items'][$id] ?? null;
            $registration = $item ? $repository->find($id) : null;
            $ranking = $item ? Ranking::tryFrom($item['ranking']) : null;

            if ($registration === null || $ranking === null || $registration->getSeason() !== $season || $registration->isDismissed()) {
                continue;
            }

            $updater->apply($registration, $ranking, self::MODES[$mode], new \DateTimeImmutable($item['fetchedAt']));
        }

        $entityManager->flush();
        $session->remove(self::SESSION_KEY);

        return $this->redirectToRoute('admin_registration_index');
    }
}
