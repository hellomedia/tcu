<?php

namespace App\Pack\Status\Chart;

use App\Pack\Chart\BaseChartProvider;
use App\Pack\Chart\Enum\ChartColor;
use Symfony\UX\Chartjs\Model\Chart;

class RealpathCacheChartProvider extends BaseChartProvider
{
    public function getRealpathCacheChart(): Chart
    {
        $sizeForHumans = \ini_get('realpath_cache_size');
        $size = $this->_convertToBytes($sizeForHumans);
        $used = realpath_cache_size();

        $free = $size - $used;
        $percentFull = 100 * $used / $size;

        $usedColor = match (true) {
            $percentFull > 90 => ChartColor::RED,
            $percentFull > 80 => ChartColor::ORANGE,
            default => ChartColor::BLUE,
        };

        $xAxisLabel = "realpath cache ({$sizeForHumans})";

        $datasets = [
            'used' => [
                'color' => $usedColor,
                'data' => [['x' => $xAxisLabel, 'y' => (int) $used]]
            ],
            'free' => [
                'color' => ChartColor::GREEN,
                'data' => [['x' => $xAxisLabel, 'y' => (int) $free]]
            ],
        ];

        $chart = $this->createBarChart(
            datasets: $datasets,
            stacked: true,
        );

        return $chart;
    }
}