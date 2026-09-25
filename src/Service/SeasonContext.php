<?php

namespace App\Service;

use App\Entity\Season;
use App\Repository\SeasonRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Saison avec laquelle on travaille
 *
 * - getCurrent() : la saison courante du club (une seule, définie dans l'admin)
 * - getInterfacsSeason() : la saison affichée par défaut sur les pages interfacs du site
 * - getSelected() : la saison sélectionnée dans l'admin (session), par défaut la saison courante.
 *   Permet de préparer la saison suivante ou de consulter une ancienne saison.
 */
class SeasonContext
{
    private const SESSION_KEY = 'admin_selected_season';

    private ?Season $current = null;
    private bool $currentLoaded = false;

    public function __construct(
        private SeasonRepository $seasonRepository,
        private RequestStack $requestStack,
    ) {
    }

    public function getCurrent(): ?Season
    {
        if (!$this->currentLoaded) {
            $this->current = $this->seasonRepository->findCurrent();
            $this->currentLoaded = true;
        }

        return $this->current;
    }

    /**
     * La saison courante si elle a des poules et est publiée.
     * Sinon, la dernière saison publiée qui en avait :
     * en été, on continue à afficher les interfacs de l'hiver précédent.
     */
    public function getInterfacsSeason(): ?Season
    {
        $current = $this->getCurrent();

        if ($current === null) {
            return null;
        }

        return $this->seasonRepository->findWithGroups(upTo: $current, publishedOnly: true)[0] ?? $current;
    }

    /**
     * Saison courante d'interfacs en préparation, pas encore publiée :
     * le site affiche une page d'attente à la place des poules et du planning (InterfacsController)
     */
    public function getUnpublishedInterfacsSeason(): ?Season
    {
        $current = $this->getCurrent();

        return $current !== null && $current->hasInterfacs() && !$current->isPublished() ? $current : null;
    }

    /**
     * Saisons consultables sur le site : celles qui ont des poules et sont publiées, jusqu'à la saison courante
     *
     * @return Season[]
     */
    public function getPublicInterfacsSeasons(): array
    {
        $current = $this->getCurrent();

        return $current ? $this->seasonRepository->findWithGroups(upTo: $current, publishedOnly: true) : [];
    }

    public function isPublic(Season $season): bool
    {
        return in_array($season, $this->getPublicInterfacsSeasons(), true);
    }

    public function getSelected(): ?Season
    {
        $session = $this->requestStack->getMainRequest()?->hasSession() ? $this->requestStack->getSession() : null;
        $id = $session?->get(self::SESSION_KEY);

        if ($id !== null) {
            $season = $this->seasonRepository->find($id);

            if ($season !== null) {
                return $season;
            }
        }

        return $this->getCurrent();
    }

    public function select(Season $season): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $season->getId());
    }

    public function isSelected(Season $season): bool
    {
        return $this->getSelected() === $season;
    }
}
