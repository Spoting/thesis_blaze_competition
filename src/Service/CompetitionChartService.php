<?php

namespace App\Service;

use App\Entity\Competition;
use App\Repository\CompetitionStatsSnapshotRepository;
use App\Repository\SubmissionRepository;
use Symfony\Component\Mercure\HubInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class CompetitionChartService
{
    public function __construct(
        private ChartBuilderInterface $chartBuilder,
        private CompetitionStatsSnapshotRepository $competitionStatsSnapshotRepository,
        private RedisManager $redisManager,
        private RedisKeyBuilder $redisKeyBuilder,
        private SubmissionRepository $submissionRepository,
        // private HubInterface $mercureHub
    ) {}

    /**
     * Builds a Chart.js object for a given competition, including historical and live data,
     * and prepares associated Mercure URL for real-time updates.
     *
     * @param Competition $competition The competition entity for which to build the chart.
     * @param string $chartTitlePrefix A prefix for the chart's title (e.g., 'Submission Trends' for dashboard, 'Submission Statistics for [Comp Title]' for individual page)
     * @param ?\DateTimeImmutable $sinceDateTime The DateTimeImmutable object to fetch snapshots from (e.g., -24 hours, or null for all time).
     * @return array An array containing the Chart object and Mercure URL.
     * ['chart' => Chart, 'mercureUrl' => string, 'competitionTitle' => string]
     */
    public function buildCompetitionChartData(Competition $competition, string $chartTitlePrefix): array
    {
        $competitionId = $competition->getId();
        $competitionTitle = $competition->getTitle();

        // Fetch snapshots
        $snapshots = $this->competitionStatsSnapshotRepository->findSnapshotsForCompetition(
            $competitionId,
        );

        $labels = [];
        $initiatedData = [];
        $processedData = [];
        $failedData = [];

        foreach ($snapshots as $snapshot) {
            $labels[] = $snapshot->getCapturedAt()->format('H:i:s'); // Format time as HH:MM
            $initiatedData[] = (int) $snapshot->getInitiatedSubmissions();
            $processedData[] = (int) $snapshot->getProcessedSubmissions();
            $failedData[] = (int) $snapshot->getFailedSubmissions();
        }

        // Build the Chart.js object
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Initiated Submissions',
                    'data' => $initiatedData,
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'fill' => true,
                    'tension' => 0.1
                ],
                [
                    'label' => 'Processed Submissions',
                    'data' => $processedData,
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'fill' => true,
                    'tension' => 0.1
                ],
                [
                    'label' => 'Failed Submissions (DLQ)',
                    'data' => $failedData,
                    'borderColor' => 'rgba(255, 99, 132, 1)', // Red color
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)', // Light red fill
                    'fill' => true,
                    'tension' => 0.1
                ]
            ],
        ]);
        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'title' => [
                    'display' => true,
                    'text' => $chartTitlePrefix,
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'callbacks' => [
                        'label' => 'function(context) {
                    return context.dataset.label + ": " + context.parsed.y.toLocaleString();
                }',
                    ],
                ],
                'zoom' => [
                    'zoom' => [
                        'wheel' => ['enabled' => true],
                        'pinch' => ['enabled' => true],
                        'mode' => 'x',
                    ],
                    'pan' => [
                        'enabled' => true,
                        'mode' => 'x',
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Time',
                    ],
                ],
                'y' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Submissions',
                    ],
                ],
            ],
        ]);

        // TODO: 4. Generate the Mercure subscribe URL 
        $mercureUrl = sprintf('https://example.com/competition/%d/stats', $competitionId);
        // $mercureUrl = $this->mercureHub->getPublicUrl() . '?topic=' . urlencode($mercureTopic);

        return [
            'competitionId' => $competitionId,
            'competitionTitle' => $competitionTitle,
            'chart' => $chart,
            'mercureUrl' => $mercureUrl,
        ];
    }
}
