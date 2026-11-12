<?php

/**
 * Smoke-test pages of a Symfony app from the CLI, optionally logged in, without a browser,
 * a web server or symfony/browser-kit. Boots the app's own Kernel and handles real requests.
 *
 * Usage (from the project root):
 *   SMOKE_HOST=example.test php docs/upgrade-kit/smoke.php / /login /some/page
 *   SMOKE_HOST=admin.example.test SMOKE_USER=admin@example.test SMOKE_FIREWALL=admin \
 *       php docs/upgrade-kit/smoke.php / /user /user/1/edit
 *
 * Environment variables:
 *   SMOKE_HOST          Host header to send (required; matters for host-dependent kernels/routes)
 *   SMOKE_USER          Log in as this user (looked up by SMOKE_USER_FIELD); omit for anonymous
 *   SMOKE_FIREWALL      Firewall name to authenticate on (default: main)
 *   SMOKE_USER_CLASS    User entity class (default: App\Entity\User)
 *   SMOKE_USER_FIELD    Field to look the user up by (default: email)
 *   SMOKE_KERNEL        Kernel class (default: App\Kernel)
 *   SMOKE_ENV           Kernel environment (default: dev)
 *   SMOKE_DEBUG         0 to boot without debug, like prod error pages (default: 1)
 *   SMOKE_DUMP          Directory to save each response body into (optional)
 *   SMOKE_COOKIES       1 to also print the names of the cookies each response sets (optional)
 *
 * Output: one line per path: status, path, redirect target, and for 5xx the page <title>
 * (in dev that is the exception message). Full stack traces are in var/log/<env>.log.
 *
 * How the login works: the same thing KernelBrowser::loginUser() does — a security token
 * serialized into a session (mock file storage), and that session's cookie sent with each
 * request. The kernel is subclassed to enable framework.test (which exposes private services
 * via test.service_container) and to use its own cache dir, so the app's cache is untouched.
 */

$projectDir = getcwd();
$host = getenv('SMOKE_HOST') ?: exit("Set SMOKE_HOST (e.g. SMOKE_HOST=example.test)\n");
$kernelClass = getenv('SMOKE_KERNEL') ?: 'App\\Kernel';
$env = getenv('SMOKE_ENV') ?: 'dev';
$debug = getenv('SMOKE_DEBUG') === false ? true : (bool) getenv('SMOKE_DEBUG');
$userId = getenv('SMOKE_USER') ?: null;
$firewall = getenv('SMOKE_FIREWALL') ?: 'main';
$dumpDir = getenv('SMOKE_DUMP') ?: null;

// Host-dependent kernels read HTTP_HOST while booting: set it before and after Dotenv.
$_SERVER['HTTP_HOST'] = $host;
require $projectDir . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv($projectDir . '/.env');
$_SERVER['HTTP_HOST'] = $host;

$smokeKernelClass = 'SmokeKernel_' . md5($kernelClass);
// Written to a real file, not eval()'d: MicroKernelTrait::loadRoutes() needs the kernel's file name.
$smokeKernelFile = sys_get_temp_dir() . '/' . $smokeKernelClass . '.php';
file_put_contents($smokeKernelFile, sprintf(<<<'PHP'
<?php
class %s extends %s
{
    public static string $projectDirOverride;
    public static string $cacheDirOverride;

    protected function configureContainer(
        \Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container,
        \Symfony\Component\Config\Loader\LoaderInterface $loader,
        \Symfony\Component\DependencyInjection\ContainerBuilder $builder,
    ): void {
        parent::configureContainer($container, $loader, $builder);
        $container->extension('framework', [
            'test' => true,
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);
    }

    // the subclass lives outside the project: without this, Symfony guesses the wrong project dir
    public function getProjectDir(): string { return self::$projectDirOverride; }

    public function getCacheDir(): string { return self::$cacheDirOverride . '/' . $this->environment; }
}
PHP, $smokeKernelClass, '\\' . ltrim($kernelClass, '\\')));
require $smokeKernelFile;

$smokeKernelClass::$projectDirOverride = $projectDir;
$smokeKernelClass::$cacheDirOverride = sys_get_temp_dir() . '/smoke-cache-' . md5($projectDir . $host . $firewall . (int) $debug);

$kernel = new $smokeKernelClass($env, $debug);
$kernel->boot();

$cookies = [];
if ($userId) {
    $tc = $kernel->getContainer()->get('test.service_container');
    $userClass = getenv('SMOKE_USER_CLASS') ?: 'App\\Entity\\User';
    $user = $tc->get('doctrine')->getManager()->getRepository($userClass)
        ->findOneBy([getenv('SMOKE_USER_FIELD') ?: 'email' => $userId]) ?? exit("User $userId not found\n");
    $token = new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($user, $firewall, $user->getRoles());
    $session = $tc->get('session.factory')->createSession();
    $session->set('_security_' . $firewall, serialize($token));
    $session->save();
    $cookies[$session->getName()] = $session->getId();
}

foreach (array_slice($argv, 1) as $path) {
    $request = Symfony\Component\HttpFoundation\Request::create("https://$host$path", server: ['HTTP_HOST' => $host, 'HTTPS' => 'on']);
    foreach ($cookies as $name => $value) {
        $request->cookies->set($name, $value);
    }
    $response = $kernel->handle($request);
    $status = $response->getStatusCode();

    $detail = '';
    if ($status >= 500 && preg_match('/<title>(.*?)<\/title>/s', (string) $response->getContent(), $m)) {
        $detail = preg_replace('/\s+/', ' ', html_entity_decode(trim($m[1])));
    }
    if ($dumpDir) {
        @mkdir($dumpDir, 0777, true);
        file_put_contents($dumpDir . '/page' . preg_replace('/\W+/', '_', $path) . '.html', $response->getContent());
    }
    printf("%d %s %s %s\n", $status, $path, $response->headers->get('Location') ?? '', substr($detail, 0, 300));
    if (getenv('SMOKE_COOKIES')) {
        foreach ($response->headers->getCookies() as $cookie) {
            printf("    set-cookie %s (domain %s, path %s)\n", $cookie->getName(), $cookie->getDomain() ?? '-', $cookie->getPath());
        }
    }
    $kernel->terminate($request, $response);
}
