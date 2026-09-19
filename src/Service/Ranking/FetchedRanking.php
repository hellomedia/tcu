<?php

namespace App\Service\Ranking;

use App\Enum\Ranking;

/**
 * Classements d'un joueur lus sur sa page mon-classement-tennis.be
 */
final class FetchedRanking
{
    public function __construct(
        // nom affiché sur la page : permet de vérifier que le numéro d'affiliation est bien celui du joueur
        public readonly string $playerName,
        // classement officiel actuel
        public readonly ?Ranking $official,
        public readonly ?string $officialRaw,
        // classement prévisionnel : estimation du prochain classement, tant qu'il n'est pas officiel
        public readonly ?Ranking $previsional,
        public readonly ?string $previsionalRaw,
    ) {
    }

    public function get(bool $previsional): ?Ranking
    {
        return $previsional ? $this->previsional : $this->official;
    }

    /**
     * Texte lu sur la page, utile quand il ne correspond à aucun classement connu (ex: série A)
     */
    public function getRaw(bool $previsional): ?string
    {
        return $previsional ? $this->previsionalRaw : $this->officialRaw;
    }
}
