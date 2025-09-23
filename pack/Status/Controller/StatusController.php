<?php

namespace Pack\Status\Controller;

use Admin\Controller\DashboardController;
use App\Controller\BaseController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Pack\Status\Chart\OpCacheChartProvider;
use Pack\Status\Chart\RealpathCacheChartProvider;
use Pack\Status\Model\OpCacheStatus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/status')]
class StatusController extends BaseController
{
    #[Route(path: '/opcache', name: 'admin_status_opcache', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function opcache(OpCacheChartProvider $charts): Response
    {
        $opCache = new OpCacheStatus();

        $memoryChart = $charts->getMemoryChart($opCache);
        $keysChart = $charts->getKeysChart($opCache);
        $hitsChart = $charts->getHitsChart($opCache);
        $restartsChart = $charts->getRestartsChart($opCache);

        return $this->render('@status/opcache.html.twig', [
            'opcache' => $opCache,
            'memory_chart' => $memoryChart,
            'keys_chart' => $keysChart,
            'hits_chart' => $hitsChart,
            'restarts_chart' => $restartsChart,
        ]);
    }

    #[Route(path: '/realpath_cache', name: 'admin_status_realpath_cache', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function realpathCache(RealpathCacheChartProvider $charts): Response
    {
        $realpathChart = $charts->getRealpathCacheChart();

        return $this->render('@status/realpath_cache.html.twig', [
            'realpath_chart' => $realpathChart,
        ]);
    }

    #[Route(path: '/phpinfo', name: 'admin_status_phpinfo', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function phpinfo(): Response
    {
        ob_start();
        phpinfo();
        $phpinfo = ob_get_contents();
        ob_end_clean();

        return $this->render('@status/phpinfo.html.twig', [
            'phpinfo' => $phpinfo,
        ]);
    }
}