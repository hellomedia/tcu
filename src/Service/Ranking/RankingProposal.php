<?php

namespace App\Service\Ranking;

use App\Enum\Ranking;

/**
 * Ce que la récupération propose pour une inscription
 */
final class RankingProposal
{
    // le classement change
    public const CHANGE = 'change';
    // même classement, mais sa source change (ex: le prévisionnel devient officiel)
    public const CONFIRM = 'confirm';
    // rien à faire
    public const SAME = 'same';
    // doute : on n'enregistre rien (pas de numéro, joueur introuvable, nom différent, classement inconnu...)
    public const PROBLEM = 'problem';

    public function __construct(
        public readonly string $status,
        public readonly ?Ranking $ranking = null,
        public readonly ?string $siteName = null,
        public readonly ?string $remark = null,
    ) {
    }

    public function isApplicable(): bool
    {
        return in_array($this->status, [self::CHANGE, self::CONFIRM], true);
    }
}
