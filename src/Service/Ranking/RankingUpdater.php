<?php

namespace App\Service\Ranking;

use App\Entity\Registration;
use App\Enum\Ranking;
use App\Enum\RankingSource;

/**
 * Récupère le classement du joueur d'une inscription et dit ce qu'il faut en faire.
 * Utilisé par l'admin (Admin\Controller\RankingController) et par la commande app:rankings:fetch.
 *
 * Une fois par saison :
 * 1) tant que les nouveaux classements ne sont pas officiels : classement prévisionnel
 * 2) quand ils sont officiels : classement officiel
 *
 * En cas de doute, on ne propose rien : c'est à un admin de vérifier.
 */
class RankingUpdater
{
    public function __construct(
        private RankingFetcher $fetcher,
    ) {
    }

    public static function getSource(bool $previsional): RankingSource
    {
        return $previsional ? RankingSource::PREVISIONAL : RankingSource::OFFICIAL;
    }

    /**
     * Ne modifie rien. Fait une requête vers mon-classement-tennis.be : à espacer.
     */
    public function propose(Registration $registration, bool $previsional): RankingProposal
    {
        $player = $registration->getPlayer();

        if ($player->getAffiliationNumber() === null) {
            return new RankingProposal(RankingProposal::PROBLEM, remark: 'Pas de numéro d\'affiliation');
        }

        try {
            $fetched = $this->fetcher->fetch($player->getAffiliationNumber());
        } catch (RankingFetchException $e) {
            return new RankingProposal(RankingProposal::PROBLEM, remark: $e->getMessage());
        }

        $ranking = $fetched->get($previsional);

        if (!$this->isSamePerson($player->getLastname(), $fetched->playerName)) {
            return new RankingProposal(RankingProposal::PROBLEM, $ranking, $fetched->playerName, 'Nom différent : vérifier le numéro d\'affiliation');
        }

        if ($ranking === null) {
            $raw = $fetched->getRaw($previsional);
            $remark = match (true) {
                $raw !== null => sprintf('Classement inconnu : "%s"', $raw),
                $previsional => 'Pas de classement prévisionnel sur la page',
                default => 'Pas de classement sur la page',
            };

            return new RankingProposal(RankingProposal::PROBLEM, null, $fetched->playerName, $remark);
        }

        $status = match (true) {
            $ranking !== $registration->getRanking() => RankingProposal::CHANGE,
            $registration->getRankingSource() !== self::getSource($previsional) => RankingProposal::CONFIRM,
            default => RankingProposal::SAME,
        };

        return new RankingProposal($status, $ranking, $fetched->playerName);
    }

    public function apply(Registration $registration, Ranking $ranking, bool $previsional, \DateTimeImmutable $fetchedAt): void
    {
        $registration->setRankingFrom($ranking, self::getSource($previsional), $fetchedAt);
    }

    /**
     * Le nom de famille du joueur doit se retrouver dans le nom affiché sur la page
     */
    private function isSamePerson(?string $lastname, string $pageName): bool
    {
        if ($lastname === null || trim($lastname) === '') {
            return true; // rien à comparer
        }

        $normalize = function (string $text): string {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);

            return preg_replace('/[^a-z]/', '', $text);
        };

        return str_contains($normalize($pageName), $normalize($lastname));
    }
}
