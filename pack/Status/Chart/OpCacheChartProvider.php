<?php

namespace Pack\Status\Chart;

use Pack\Chart\BaseChartProvider;
use Pack\Chart\Enum\ChartColor;
use Pack\Status\Model\OpCacheStatus;
use Symfony\UX\Chartjs\Model\Chart;

class OpCacheChartProvider extends BaseChartProvider
{
    public function getMemoryChart(OpCacheStatus $opCache): Chart
    {
        $stats = $opCache->getMemoryStats();

        $usedColor = match (true) {
            $opCache->getPercentFull() > 90 => ChartColor::RED,
            $opCache->getPercentFull() > 80 => ChartColor::ORANGE,
            default => ChartColor::BLUE,
        };

        $xAxisLabel = "Memory ({$opCache->getMemoryLimit()})";

        $datasets = [
            'used' => [
                'color' => $usedColor,
                'data' => [['x' => $xAxisLabel, 'y' => (int) $stats['used']]]
            ],
            'free' => [
                'color' => ChartColor::GREEN,
                'data' => [['x' => $xAxisLabel, 'y' => (int) $stats['free']]]
            ],
            'wasted' => [
                'color' => ChartColor::DARK_GREY,
                'data' => [['x' => $xAxisLabel, 'y' => (int) $stats['wasted']]]
            ],
        ];

        $chart = $this->createBarChart(
            datasets: $datasets,
            stacked: true,
        );

        return $chart;
    }

    public function getKeysChart(OpCacheStatus $opCache): Chart
    {
        $stats = $opCache->getKeysStats();
        $cachedKeys = (int) $stats['cached'];
        $remaining = (int) $stats['remaining'];

        $percentFull = 100 * $cachedKeys / ($cachedKeys + $remaining);

        $usedColor = match (true) {
            $percentFull > 90 => ChartColor::RED,
            $percentFull > 80 => ChartColor::ORANGE,
            default => ChartColor::BLUE,
        };

        $xAxisLabel = "Keys (# files cached)";

        $datasets = [
            'cached keys' => [
                'color' => $usedColor,
                'data' => [['x' => $xAxisLabel, 'y' => $cachedKeys]]
            ],
            'remaining' => [
                'color' => ChartColor::GREEN,
                'data' => [['x' => $xAxisLabel, 'y' => $remaining]]
            ],
        ];

        $chart = $this->createBarChart(
            datasets: $datasets,
            stacked: true,
        );

        return $chart;
    }

    public function getHitsChart(OpCacheStatus $opCache): Chart
    {
        $stats = $opCache->getHitsStats();

        $xAxisLabel = "Hits";

        $datasets = [
            'hits' => [
                'color' => ChartColor::BLUE,
                'data' => [['x' => $xAxisLabel, 'y' => (int) $stats['hits']]]
            ],
            'misses' => [
                'color' => ChartColor::RED,
                'data' => [['x' => $xAxisLabel, 'y' => (int) $stats['misses']]]
            ],
        ];

        $chart = $this->createBarChart(
            datasets: $datasets,
            stacked: true,
        );

        return $chart;
    }

    public function getRestartsChart(OpCacheStatus $opCache): Chart
    {
        $stats = $opCache->getRestartStats();

        $xAxisLabel = 'Restarts';

        $datasets = [
            'oom (out of memory) restarts' => [
                'color' => ChartColor::RED,
                'data' => [['x' => $xAxisLabel, 'y' => (int) $stats['oom']]]
            ],
            'manual restarts' => [
                'color' => ChartColor::BLUE,
                'data' => [['x' => $xAxisLabel, 'y' => (int) $stats['manual']]]
            ],
            'hash restarts' => [
                'color' => ChartColor::ORANGE,
                'data' => [['x' => $xAxisLabel, 'y' => (int) $stats['hash']]]
            ],
        ];
        
        $chart = $this->createBarChart(
            datasets: $datasets,
            stacked: true,
        );

        return $chart;
    }
}