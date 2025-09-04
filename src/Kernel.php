<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * We load admin parameters and services conditionally to make the user facing app leaner.
     * This adds complexity because it forces us to use separate caches for the app and admin (see getCacheDir).
     * However, since we already deal with that for the multi-site setup in HK sites, why not.
     */
    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $configDir = $this->getConfigDir();
        $adminConfigDir = $this->getProjectDir() . '/admin/config';

        $container->import($configDir . '/packages/*.yaml');
        // SECOND -- for session name -- last value overrides previous ones
        if (($_SERVER['HTTP_HOST'] ?? 'undefined') == $_SERVER['HOST_ADMIN']) {
            $container->import($adminConfigDir . '/packages/*.yaml');
        }

        $container->import($configDir . '/parameters.yaml');
        if (($_SERVER['HTTP_HOST'] ?? 'undefined') == $_SERVER['HOST_ADMIN']) {
            $container->import($adminConfigDir . '/parameters.yaml');
        }

        $container->import($configDir . '/services.yaml');
        if (($_SERVER['HTTP_HOST'] ?? 'undefined') == $_SERVER['HOST_ADMIN']) {
            $container->import($adminConfigDir . '/services.yaml');
        }
    }

    /**
     * See comment in configureContainer
     */
    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $configDir = $this->getConfigDir();
        $adminConfigDir = $this->getProjectDir() . '/admin/config';

        // FIRST -- for admin routes - ie: /login or / -- first route wins
        // if ($_SERVER['HTTP_HOST'] ?? 'undefined' == $_SERVER['HOST_ADMIN']) {
            $routes->import($adminConfigDir . '/routes.yaml');
            $routes->import($adminConfigDir . '/routes/easyadmin.yaml');
        // }

        $routes->import($configDir . '/{routes}/' . $this->environment . '/*.{php,yaml}');
        $routes->import($configDir . '/{routes}/*.{php,yaml}');

        $routes->import($configDir . '/routes.yaml');

        if (false !== ($fileName = (new \ReflectionObject($this))->getFileName())) {
            $routes->import($fileName, 'attribute');
        }
    }

    /**
     * Since configureContainer and configureRoutes are dependent on SERVER var,
     * the application cache should be separate for tcu and admin.
     * Otherwise, amongst other things, the cached container is not consistent across tcu and admin.
     * https://symfony.com/doc/current/configuration/override_dir_structure.html#override-the-cache-directory.
     */
    public function getCacheDir(): string
    {
        $dir = match($_SERVER['HTTP_HOST'] ?? 'undefined') {
            $_SERVER['HOST_ADMIN'] => 'admin',
            $_SERVER['HOST'] => 'site',
            default => 'site',
        };

        if (isset($_SERVER['APP_CACHE_DIR'])) {
            return $_SERVER['APP_CACHE_DIR'] . '/' . $dir . '/' . $this->environment;
        }

        return $this->getProjectDir() . '/var/cache/' . $dir . '/' . $this->environment;
    }

}
