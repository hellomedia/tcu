<?php

namespace App\Service;

use App\Entity\Group;
use App\Entity\Season;
use App\Repository\GroupRepository;

/**
 * Couleur d'une poule, pour la repérer d'un coup d'œil sur le planning.
 *
 * Déterminée par la position de la poule dans l'ordre d'affichage de sa saison
 * (pas de couleur stockée en base). Tons sourds : pas de vert/bleu/rouge purs,
 * pour ne pas se confondre avec les pastilles de confirmation (gris / vert / bleu).
 */
class GroupColors
{
    public const PALETTE = [
        '#e07a5f', // terracotta
        '#8e6bbf', // violet
        '#e9b44c', // ambre
        '#4ea8a1', // sarcelle
        '#c75b9b', // magenta
        '#a47148', // brun
        '#8a9a3b', // olive
        '#6c8ebf', // ardoise
    ];

    /** @var array<int, array<int, string>> couleur par id de poule, par id de saison */
    private array $cache = [];

    public function __construct(
        private GroupRepository $groupRepository,
    ) {
    }

    public function colorOf(Group $group): string
    {
        $season = $group->getSeason();

        if ($season === null) {
            return self::PALETTE[0];
        }

        $colors = $this->colorsOfSeason($season);

        return $colors[$group->getId()] ?? self::PALETTE[0];
    }

    /**
     * @return array<int, string> couleur par id de poule
     */
    public function colorsOfSeason(Season $season): array
    {
        $seasonId = $season->getId();

        if (!isset($this->cache[$seasonId])) {
            $this->cache[$seasonId] = [];
            $groups = $this->groupRepository->findBy(['season' => $season], GroupRepository::ORDER);

            foreach ($groups as $index => $group) {
                $this->cache[$seasonId][$group->getId()] = self::PALETTE[$index % \count(self::PALETTE)];
            }
        }

        return $this->cache[$seasonId];
    }
}
