<?php

namespace App\Tests\Smoke;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Requests every page without route parameters, on each host and as each kind of user, and fails
 * only on a server error (5xx). Pages are discovered from the router, so new pages are covered.
 *
 * Deliberately no assertion on content or redirects: a page that answers 5xx is broken, anything
 * else is a product decision this test does not second-guess.
 */
class PagesTest extends WebTestCase
{
    /** Paths that cannot be requested this way: not pages, or not available on the CLI. */
    private const array EXCLUDED_PATHS = [
        '/login-check', // intercepted by the firewall on POST, the controller itself throws
        '/status/opcache', // OPcache is disabled on the CLI: opcache_get_status() returns false
    ];

    /**
     * - /_check/*: production server health checks (OPcache, php.ini, Sentry, messenger worker), meant to fail elsewhere
     * - .../autocomplete: EasyAdmin AJAX endpoints, they need query parameters
     */
    private const string EXCLUDED_PATH_PATTERN = '#^/_check/|/autocomplete$#';

    /**
     * @return iterable<string, array{string, ?string, string}>
     */
    public static function visitors(): iterable
    {
        yield 'site, anonymous' => ['HOST', null, 'main'];
        yield 'site, player' => ['HOST', 'joueur@tcuniversite.be', 'main'];
        yield 'admin, admin' => ['HOST_ADMIN', 'admin@tcuniversite.be', 'admin'];
    }

    #[DataProvider('visitors')]
    public function testPagesDoNotFail(string $hostVariable, ?string $email, string $firewall): void
    {
        $host = $_SERVER[$hostVariable];
        // The kernel loads the admin config depending on HTTP_HOST: it must be set before booting.
        $_SERVER['HTTP_HOST'] = $host;
        $client = static::createClient(server: ['HTTP_HOST' => $host, 'HTTPS' => 'on']);

        if ($email !== null) {
            $user = static::getContainer()->get(EntityManagerInterface::class)
                ->getRepository(User::class)->findOneBy(['email' => $email]);
            self::assertNotNull($user, "Fixture user $email not found");
            $client->loginUser($user, $firewall);
        }

        $failures = [];
        $paths = $this->pagePaths($hostVariable === 'HOST_ADMIN');
        self::assertNotEmpty($paths);

        foreach ($paths as $path) {
            $client->request('GET', $path);
            $response = $client->getResponse();
            if ($response->getStatusCode() >= 500) {
                preg_match('/<title>(.*?)<\/title>/s', (string) $response->getContent(), $title);
                $message = html_entity_decode(trim(preg_replace('/\s+/', ' ', $title[1] ?? '')));
                $failures[] = sprintf('%d %s  %s', $response->getStatusCode(), $path, $message);
            }
        }

        self::assertSame([], $failures, sprintf("Pages failing on %s:\n%s", $host, implode("\n", $failures)));
    }

    /**
     * GET routes without parameters of one host: admin routes have a host requirement, site routes none.
     *
     * @return list<string>
     */
    private function pagePaths(bool $admin): array
    {
        $paths = [];
        foreach (static::getContainer()->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            if (str_starts_with($name, '_')
                || ($route->getHost() !== '') !== $admin
                || str_contains($route->getPath(), '{')
                || ($route->getMethods() !== [] && !\in_array('GET', $route->getMethods(), true))
                || \in_array($route->getPath(), self::EXCLUDED_PATHS, true)
                || preg_match(self::EXCLUDED_PATH_PATTERN, $route->getPath())
            ) {
                continue;
            }
            $paths[] = $route->getPath();
        }

        return array_values(array_unique($paths));
    }
}
