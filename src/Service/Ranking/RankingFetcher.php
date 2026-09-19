<?php

namespace App\Service\Ranking;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Récupère le classement d'un joueur sur mon-classement-tennis.be à partir de son numéro d'affiliation.
 *
 * Pas d'accord avec ce site, pas d'API publique : on reste discret.
 * Une centaine de pages, quelques fois par an, une page à la fois avec une pause entre deux pages
 * (la pause est gérée par l'appelant), et un User-Agent qui dit qui on est.
 */
class RankingFetcher
{
    public const URL = 'https://mon-classement-tennis.be/joueur/%s';

    public function __construct(
        private HttpClientInterface $httpClient,
        private RankingPageParser $parser,
        #[Autowire('%site_name%')]
        private string $siteName,
        #[Autowire('%email_domain%')]
        private string $emailDomain,
    ) {
    }

    public static function getUrl(string $affiliationNumber): string
    {
        return sprintf(self::URL, rawurlencode($affiliationNumber));
    }

    /**
     * @throws RankingFetchException
     */
    public function fetch(string $affiliationNumber): FetchedRanking
    {
        if (!preg_match('/^\d+$/', $affiliationNumber)) {
            throw new RankingFetchException('Numéro d\'affiliation invalide.');
        }

        try {
            $response = $this->httpClient->request('GET', self::getUrl($affiliationNumber), [
                'timeout' => 15,
                'max_redirects' => 2,
                'headers' => [
                    'User-Agent' => sprintf('%s - club de tennis (%s) - classements de nos membres', $this->siteName, $this->emailDomain),
                    'Accept' => 'text/html',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 404) {
                throw new RankingFetchException('Joueur introuvable : numéro d\'affiliation incorrect ?');
            }

            if ($statusCode !== 200) {
                throw new RankingFetchException(sprintf('Le site a répondu avec le code %d.', $statusCode));
            }

            $html = $response->getContent();
        } catch (ExceptionInterface $e) {
            throw new RankingFetchException('Le site est injoignable : ' . $e->getMessage(), previous: $e);
        }

        return $this->parser->parse($html);
    }
}
