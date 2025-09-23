<?php

namespace Pack\Status\Controller;

use App\Controller\BaseController;
use Pack\Status\Chart\OpCacheChartProvider;
use Pack\Status\Chart\RealpathCacheChartProvider;
use Pack\Status\Model\OpCacheStatus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/status', name: 'controlroom_status_')]
class StatusController extends BaseController
{
    #[Route(path: '/opcache', name: 'opcache')]
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

    #[Route(path: '/realpath_cache', name: 'realpath_cache')]
    public function realpathCache(RealpathCacheChartProvider $charts): Response
    {
        $realpathChart = $charts->getRealpathCacheChart();

        return $this->render('@status/realpath_cache.html.twig', [
            'realpath_chart' => $realpathChart,
        ]);
    }

    #[Route(path: '/phpinfo', name: 'phpinfo')]
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