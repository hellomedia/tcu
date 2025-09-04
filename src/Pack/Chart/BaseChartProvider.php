<?php

namespace App\Pack\Chart;

use App\Pack\Chart\Enum\ChartColor;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Tick formatting with a callback takes place in stimulus
 * https://symfony.com/bundles/ux-chartjs/current/index.html#extend-the-default-behavior
 * 
 */
class BaseChartProvider
{
    protected string $siteColor;
    protected string $siteFillColor;

    protected array $colors;

    private const COLORS = [
        ChartColor::BLUE->value,
        ChartColor::GREEN->value,
        ChartColor::PURPLE->value,
        ChartColor::GREY->value,
        ChartColor::PINK->value,
        ChartColor::ORANGE->value,
        ChartColor::GOLD->value,
    ];

    public function __construct(
        protected ChartBuilderInterface $chartBuilder,
        private RequestStack $requestStack,
        protected TranslatorInterface $translator,
    )
    {
        // if single site
        $this->colors = self::COLORS;
        $this->siteColor = ChartColor::BLUE->value;
        $this->siteFillColor = ChartColor::BLUE_BG->value;

        // if multisite and siteColor
        // $this->colors = [$this->siteColor, ...array_map(fn(ChartColor $color): string => $color->value, self::COLORS)];
    }

    protected function createLineChart(array $datasets, string $timeUnit = 'day', bool $showLegend = true, bool $showAxes = true): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        $formattedDatasets = [];
        $i = 0;

        foreach ($datasets as $key => $dataset) {
            $formattedDatasets[] = [
                'label' => $this->translator->trans($key),
                'borderColor' => $dataset['color'] ?? $this->colors[$i],
                'backgroundColor' => $dataset['bg_color'] ?? $dataset['color'] ?? $this->colors[$i],
                'fill' => false,
                'data' => $dataset,
            ];
            $i++;
        }

        $chart->setData(['datasets' => $formattedDatasets]);

        $this->_setLineChartOptions(
            chart: $chart,
            timeUnit: $timeUnit,
            showLegend: $showLegend,
            showAxes: $showAxes
        );

        return $chart;
    }

    protected function createSingleDatasetLineChart(array $data, ?string $label = null, string $timeUnit = 'day', bool $fill = false, bool $showLegend = true, bool $showAxes = true): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        $chart->setData([
            'datasets' => [
                [
                    'label' => $label ? $this->translator->trans($label) : 'FIX ME',
                    'data' => $data,
                    'borderColor' => $this->siteColor,
                    'backgroundColor' => $this->siteFillColor,
                    'fill' => $fill,
                ],
            ],
        ]);

        $this->_setLineChartOptions(
            chart: $chart,
            timeUnit: $timeUnit,
            showLegend: $showLegend,
            showAxes: $showAxes
        );

        return $chart;
    }

    protected function createBarChart(array $datasets, bool $stacked = false, ?string $timeUnit = null, bool $showLegend = true, bool $showAxes = true, ?string $color = null, ?string $gridColor = null): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);

        $formattedDatasets = [];
        $i = 0;

        foreach ($datasets as $key => $dataset) {
            $formattedDatasets[] = [
                'label' => $this->translator->trans($key),
                'color' => $color ?? '',
                'borderColor' => $dataset['color'] ?? $this->colors[$i],
                'backgroundColor' => $dataset['bg_color'] ?? $dataset['color'] ?? $this->colors[$i],
                'data' => $dataset['data'] ?? $dataset,
                'borderWidth' => 1,
            ];
            $i++;
        }

        $chart->setData(['datasets' => $formattedDatasets]);

        $this->_setBarChartOptions(
            chart: $chart,
            stacked: $stacked,
            timeUnit: $timeUnit,
            showLegend: $showLegend,
            showAxes: $showAxes,
            color: $color,
            gridColor: $gridColor,
        );

        return $chart;
    }

    protected function createSingleDatasetBarChart(string $label, array $data, ?string $timeUnit = null, bool $showLegend = false, bool $showAxes = false, ?string $color = null, ?string $bgColor = null, ?string $gridColor = null): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);

        $chart->setData([
            'datasets' => [
                [
                    'label' => $this->translator->trans($label),
                    'data' => $data,
                    'color' => $color ?? '',
                    'borderColor' => $bgColor ?? $this->siteColor,
                    'backgroundColor' => $bgColor ?? $this->siteFillColor,
                    'borderWidth' => 0,
                ],
            ],
        ]);

        $this->_setBarChartOptions(
            chart: $chart,
            stacked: false, // stacked requires multiple datasets
            timeUnit: $timeUnit,
            showLegend: $showLegend,
            showAxes: $showAxes,
            color: $color,
            gridColor: $gridColor,
        );

        return $chart;
    }

    private function _setLineChartOptions(Chart $chart, string $timeUnit, bool $showLegend = true, $showAxes = true)
    {
        // line chart
        // https://www.chartjs.org/docs/latest/charts/line.html

        // time scale
        // https://www.chartjs.org/docs/latest/samples/scales/time-line.html

        // luxon date formats
        // https://moment.github.io/luxon/#/formatting?id=table-of-tokens

        //  Tick formatting with a callback takes place in stimulus
        // {{ render_chart(memory_chart, {'data-controller': 'format-bytes-axis'}) }}
        //  https://symfony.com/bundles/ux-chartjs/current/index.html#extend-the-default-behavior

        $tooltipFormat = match ($timeUnit) {
            'day' => 'DDD',
            'month' => 'LLLL y',
            'year' => 'y'
        };

        $chart->setOptions([
            'locale' => $this->requestStack->getCurrentRequest()->getLocale(),
            'pointRadius' => 3,
            'pointHitRadius' => 10,
            'maintainAspectRatio' => false, // https://www.chartjs.org/docs/latest/configuration/responsive.html#responsive-charts
            'scales' => [
                'x' => [
                    'type' => 'time',
                    'display' => $showAxes,
                    'time' => [
                        'unit' => $timeUnit,
                        'tooltipFormat' => $tooltipFormat,
                    ],
                    'adapters' => [
                        'date' => [
                            // Fixes timezone offset in display of x-axis data points
                            // Dates are assumed to be received as UTC by chartjs, and adjusted to local timezone.
                            // However, we constructed our dataset with timestamps corresponding to dates at midnight
                            // In ContentStatsProvider: EXTRACT(EPOCH FROM group_date) * 1000 AS date_bucket,
                            // And table creation : DATE_TRUNC('{$precision}', created_at)::DATE AS group_date,
                            // We do not use hours in charts output, we use them for daily aggregates
                            // So we need the time values to be displayed as is, *** NOT adjusted to local timezone ***
                            // which visually shifts the data points a few hours off the date chart lines
                            // So we need chartjs display config set to UTC
                            'zone' => 'UTC' 
                        ],
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'display' => $showAxes,
                    'ticks' => [
                        'precision' => 0 // only display ticks for numbers with 0 decimals
                    ],
                ],
            ],
            'plugins' => [
                'title' => [
                    'display' => false,
                ],
                'legend' => [
                    'display' => $showLegend,
                ]
            ]
        ]);
    }

    private function _setBarChartOptions(Chart $chart, bool $stacked, ?string $timeUnit, bool $showLegend, bool $showAxes, ?string $color, ?string $gridColor)
    {
        // bar chart
        // https://www.chartjs.org/docs/latest/charts/bar.html

        // stacked bar chart 
        // https://www.chartjs.org/docs/latest/charts/bar.html#stacked-bar-chart

        // time scale
        // https://www.chartjs.org/docs/latest/samples/scales/time-line.html

        // luxon date formats
        // https://moment.github.io/luxon/#/formatting?id=table-of-tokens

        //  Tick formatting with a callback takes place in stimulus
        // {{ render_chart(memory_chart, {'data-controller': 'format-bytes-axis'}) }}
        //  https://symfony.com/bundles/ux-chartjs/current/index.html#extend-the-default-behavior

        $options = [
            'locale' => $this->requestStack->getCurrentRequest()->getLocale(),
            'color' => $color ?? '',
            'maintainAspectRatio' => false, // https://www.chartjs.org/docs/latest/configuration/responsive.html#responsive-charts
            'scales' => [
                'x' => [
                    'bounds' => 'data', // https://www.chartjs.org/docs/latest/axes/cartesian/#scale-bounds
                    'round' => 'day',
                    'display' => $showAxes,
                    'stacked' => $stacked,
                    'color' => $color ?? '',
                    'ticks' => [
                        'color' => $color ?? '',
                    ],
                    'grid' => [
                        'display' => $gridColor ? true : false,
                        'color' => $gridColor ?? '',
                    ],
                    'border' => [
                        'color' => $color ?? '', // x-axis color
                    ],
                ],
                'y' => [
                    'display' => $showAxes,
                    'stacked' => $stacked,
                    'beginAtZero' => true,
                    'ticks' => [
                        'color' => $color ?? '',
                        'precision' => 0 // only display ticks for numbers with 0 decimals
                    ],
                    'grid' => [
                        'display' => $gridColor ? true : false,
                        'color' => $gridColor ?? '',
                    ],
                    'border' => [
                        'color' => $color ?? '', // y-axis color
                    ],
                ],
            ],
            'plugins' => [
                'title' => [
                    'display' => false,
                ],
                'legend' => [
                    'display' => $showLegend,
                    'labels' => [
                        'color' => $color ?? '',
                    ]
                ]
            ]
        ];

        if ($timeUnit) {

            $tooltipFormat = match ($timeUnit) {
                'day' => 'DDD',
                'month' => 'LLLL y',
                'year' => 'y'
            };

            $options['scales']['x']['type'] = 'time';

            $options['scales']['x']['time'] = [
                'unit' => $timeUnit,
                'displayFormats' => [
                    'month' => 'LLL',
                ],
                'tooltipFormat' => $tooltipFormat,
            ];

            // see comment in _setLineChartOptions
            $options['scales']['x']['adapters']['date']['zone'] = 'UTC';
        }

        $chart->setOptions($options);
    }


    protected function beginAtZero(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true
                ]
            ],
        ];
    }

    protected function getTimeScaleOptions(?string $unit = 'day'): array
    {
        $tooltipFormat = match ($unit) {
            'day' => 'DDD',
            'month' => 'LLLL y',
            'year' => 'y'
        };

        return [
            'scales' => [
                'x' => [
                    'type' => 'time',
                    'time' => [
                        'unit' => $unit,
                        'tooltipFormat' => $tooltipFormat,
                    ],
                    'adapters' => [
                        'date' => [
                            // Fixes timezone offset in display of x-axis data points
                            // Dates are assumed to be received as UTC by chartjs, and adjusted to local timezone.
                            // However, we constructed our dataset with timestamps corresponding to dates at midnight
                            // In ContentStatsProvider: EXTRACT(EPOCH FROM group_date) * 1000 AS date_bucket,
                            // And table creation : DATE_TRUNC('{$precision}', created_at)::DATE AS group_date,
                            // We do not use hours in charts output, we use them for daily aggregates
                            // So we need the time values to be displayed as is, *** NOT adjusted to local timezone ***
                            // which visually shifts the data points a few hours off the date chart lines
                            // So we need chartjs display config set to UTC
                            'zone' => 'UTC'
                        ],
                    ],
                ],
                'y' => [
                    'beginAtZero' => true
                ]
            ],
        ];
    }

    /**
     * Convert human-readable size to bytes
     */
    protected function _convertToBytes(string $size): int
    {
        if (\is_numeric($size)) {
            return $size;
        }

        $unit = strtoupper(substr($size, -1));
        $size = (int) $size;

        return match ($unit) {
            'K' => $size * 1024,
            'M' => $size * 1024 * 1024,
            'G' => $size * 1024 * 1024 * 1024,
        };
    }

    protected function _sizeForHumans(int $bytes): string
    {
        if ($bytes > 1048576) {
            return sprintf('%d M', $bytes / 1048576);
        } else {
            if ($bytes > 1024) {
                return sprintf('%d K', $bytes / 1024);
            } else {
                return sprintf('%d bytes', $bytes);
            }
        }
    }
}   