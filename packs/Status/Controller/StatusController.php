<?php

namespace Pack\Status\Controller;

use App\Controller\BaseController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Pack\Status\Chart\OpCacheChartProvider;
use Pack\Status\Chart\RealpathCacheChartProvider;
use Pack\Status\Model\OpCacheStatus;
use Symfony\Component\HttpFoundation\Response;

#[AdminRoute('/status', name: 'status')]
class StatusController extends BaseController
{
    #[AdminRoute('/opcache', name: 'opcache')]
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

    #[AdminRoute('/realpath_cache', name: 'realpath_cache')]
    public function realpathCache(RealpathCacheChartProvider $charts): Response
    {
        $realpathChart = $charts->getRealpathCacheChart();

        return $this->render('@status/realpath_cache.html.twig', [
            'realpath_chart' => $realpathChart,
        ]);
    }

    #[AdminRoute('/phpinfo', name: 'phpinfo')]
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
