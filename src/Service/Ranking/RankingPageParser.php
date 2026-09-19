<?php

namespace App\Service\Ranking;

use App\Enum\Ranking;

/**
 * Lit les classements sur la page d'un joueur : https://mon-classement-tennis.be/joueur/{numéro d'affiliation}
 *
 * Structure de la page (septembre 2026) :
 * - classement officiel : dans le h1, "nom prénom - C15.1"
 * - classement prévisionnel : bloc "Classement prévisionnel" avec 3 <span> :
 *   le titre, le classement ("B0"), puis une phrase ("34.04 points manquants pour B-2/6").
 *   On prend le span dont le texte est exactement un classement : la phrase ne peut pas être prise par erreur.
 *
 * Numéro d'affiliation inconnu : page "Joueur non trouvé", avec un code 200.
 *
 * Pas d'API (/api/ est interdit par leur robots.txt) : si la structure de la page change,
 * le parser ne trouve plus rien et le signale, il ne devine pas.
 */
class RankingPageParser
{
    private const PREVISIONAL_LABEL = 'Classement prévisionnel';

    public function parse(string $html): FetchedRanking
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // force utf-8 : sans ça DOMDocument lit la page en latin1
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);

        // numéro d'affiliation inconnu : le site répond 200 avec une page "Joueur non trouvé" (pas une 404)
        $pageTitle = $xpath->query('//title')->item(0)?->textContent ?? '';
        if (str_contains(mb_strtolower($pageTitle), 'joueur non trouvé')) {
            throw new RankingFetchException('Joueur introuvable sur le site : numéro d\'affiliation incorrect ?');
        }

        $title = $this->normalizeSpaces($xpath->query('//h1')->item(0)?->textContent ?? '');

        if ($title === '') {
            throw new RankingFetchException('Titre de la page introuvable : la structure de la page a changé ?');
        }

        // "sauveur nicolas - C15.1" : le classement suit le dernier " - " (un nom peut contenir un tiret)
        $position = mb_strrpos($title, ' - ');
        $playerName = $position === false ? $title : mb_substr($title, 0, $position);
        $officialRaw = $position === false ? null : trim(mb_substr($title, $position + 3));

        $previsionalRaw = null;
        $label = $xpath->query(sprintf('//span[normalize-space(.)="%s"]', self::PREVISIONAL_LABEL))->item(0);

        if ($label !== null) {
            // le bloc = le parent du parent du titre
            $block = $label->parentNode?->parentNode;
            $leaves = $block ? $xpath->query('.//span[not(*)]', $block) : [];

            foreach ($leaves as $leaf) {
                $text = $this->normalizeSpaces($leaf->textContent);

                if ($text !== self::PREVISIONAL_LABEL && $this->toRanking($text) !== null) {
                    $previsionalRaw = $text;
                    break;
                }
            }

            // classement inconnu de l'app (série A...) : on garde le texte du 2e span pour le signaler
            if ($previsionalRaw === null && $leaves instanceof \DOMNodeList && $leaves->length >= 2) {
                $previsionalRaw = $this->normalizeSpaces($leaves->item(1)->textContent);
            }
        }

        return new FetchedRanking(
            playerName: $playerName,
            official: $this->toRanking($officialRaw),
            officialRaw: $officialRaw,
            previsional: $this->toRanking($previsionalRaw),
            previsionalRaw: $previsionalRaw,
        );
    }

    /**
     * Le site écrit "B-15.1", "B-15.2", "B-15.4". L'app : "B-15/1", "B-15/2", "B-15/4".
     * Les autres classements s'écrivent de la même façon.
     */
    public function toRanking(?string $raw): ?Ranking
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return Ranking::tryFrom($raw)
            ?? Ranking::tryFrom(preg_replace('~^B-15\.(\d)$~', 'B-15/$1', $raw));
    }

    private function normalizeSpaces(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
