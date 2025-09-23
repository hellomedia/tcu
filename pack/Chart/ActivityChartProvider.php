<?php

namespace Pack\Chart;

use Pack\Chart\Enum\ChartColor;
use App\Stats\Provider\ConnectionStatsProvider;
use App\Stats\Provider\MessageStatsProvider;
use App\Stats\Provider\PageViewStatsProvider;
use App\Stats\Provider\RegistrationStatsProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class ActivityChartProvider extends BaseChartProvider
{
    public function __construct(
        ChartBuilderInterface $chartBuilder,
        RequestStack $requestStack,
        TranslatorInterface $translator,
        private RegistrationStatsProvider $registrationStats,
        private ConnectionStatsProvider $connectionStats,
        private PageViewStatsProvider $viewsStats,
        private MessageStatsProvider $messageStats,
    )
    {
        parent::__construct($chartBuilder, $requestStack, $translator);
    }

    public function getRegistrationActivityChart(): Chart
    {
        $stats = $this->registrationStats;

        $registrations = $stats->getRegistrationActivity();
        $registrationsLastYear = $stats->getRegistrationActivity(lastYear: true);
        $unconfirmedRegistrations = $stats->getRegistrationActivity(confirmed: false);

        $chart = $this->chartBuilder->createChart('mixed');

        $chart->setData([
            'datasets' => [
                [
                    'type' => Chart::TYPE_LINE,
                    'label' => 'inscriptions',
                    'fill' => true,
                    'borderColor' => ChartColor::BLUE,
                    'backgroundColor' => ChartColor::BLUE_BG,
                    'data' => $registrations,
                ], [
                    'type' => Chart::TYPE_LINE,
                    'label' => 'inscriptions last year',
                    'fill' => false,
                    'borderColor' => ChartColor::PURPLE,
                    'data' => $registrationsLastYear,
                ], [
                    'type' => Chart::TYPE_BAR,
                    'label' => 'non confirmées',
                    'backgroundColor' => ChartColor::RED,
                    'data' => $unconfirmedRegistrations,
                ],
            ],
        ]);

        $chart->setOptions($this->getTimeScaleOptions());

        return $chart;
    }

    public function getConnectionActivityChart(): Chart
    {
        $stats = $this->connectionStats;

        $connections = $stats->getConnectionActivity();
        $connectionsLastYear = $stats->getConnectionActivity(lastYear: true);

        $chart = $this->chartBuilder->createChart('mixed');

        $chart->setData([
            'datasets' => [
                [
                    'type' => Chart::TYPE_LINE,
                    'label' => 'connections',
                    'fill' => true,
                    'borderColor' => ChartColor::BLUE,
                    'backgroundColor' => ChartColor::BLUE_BG,
                    'data' => $connections,
                ], [
                    'type' => Chart::TYPE_LINE,
                    'label' => 'connections last year',
                    'fill' => false,
                    'borderColor' => ChartColor::PURPLE,
                    'data' => $connectionsLastYear,
                ],
            ],
        ]);

        $chart->setOptions($this->getTimeScaleOptions());

        return $chart;
    }

    public function getViewsActivityChart(): Chart
    {
        $stats = $this->viewsStats;

        $views = $stats->getPageviewActivity();
        $viewsLastYear = $stats->getPageviewActivity(lastYear: true);

        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        $chart->setData([
            'datasets' => [
                [
                    'label' => 'pageviews',
                    'fill' => true,
                    'borderColor' => ChartColor::BLUE,
                    'backgroundColor' => ChartColor::BLUE_BG,
                    'data' => $views,
                ], [
                    'label' => 'pageviews last year',
                    'fill' => false,
                    'borderColor' => ChartColor::PURPLE,
                    'data' => $viewsLastYear,
                ]
            ],
        ]);

        $chart->setOptions($this->getTimeScaleOptions());

        return $chart;
    }

    public function getMessageActivityChart(): Chart
    {
        $stats = $this->messageStats;

        $messages = $stats->getMessageActivity();
        $unreadMessages = $stats->getMessageActivity(unread: true);
        $messagesLastYear = $stats->getMessageActivity(lastYear: true);
        $unreadMessagesLastYear = $stats->getMessageActivity(lastYear: true, unread: true);

        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        $chart->setData([
            'datasets' => [
                [
                    'label' => 'messages',
                    'fill' => true,
                    'borderColor' => ChartColor::BLUE,
                    'backgroundColor' => ChartColor::BLUE_BG,
                    'data' => $messages,
                ],
                [
                    'label' => 'messages last year',
                    'fill' => false,
                    'borderColor' => ChartColor::PURPLE,
                    'data' => $messagesLastYear,
                ],
                [
                    'label' => 'unread',
                    'fill' => true,
                    'borderColor' => ChartColor::RED,
                    'backgroundColor' => ChartColor::RED_BG,
                    'data' => $unreadMessages,
                ],
                [
                    'label' => 'unread last year',
                    'fill' => false,
                    'borderColor' => ChartColor::PURPLE,
                    'data' => $unreadMessagesLastYear,
                ],
            ],
        ]);

        $chart->setOptions($this->getTimeScaleOptions());

        return $chart;
    }
}