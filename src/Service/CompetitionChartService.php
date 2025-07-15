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

        // 1. Fetch historical snapshots
        $snapshots = $this->competitionStatsSnapshotRepository->findSnapshotsForCompetition(
            $competitionId,
        );

        $labels = [];
        $initiatedData = [];
        $processedData = [];

        foreach ($snapshots as $snapshot) {
            $labels[] = $snapshot->getCapturedAt()->format('H:i:s'); // Format time as HH:MM
            $initiatedData[] = $snapshot->getInitiatedSubmissions();
            $processedData[] = $snapshot->getProcessedSubmissions();
        }

        // 2. Get current live data from Redis and PostgreSQL
        $currentInitiated = (int) $this->redisManager->getValue($this->redisKeyBuilder->getCompetitionCountKey($competitionId)) ?? 0;
        $currentProcessed = $this->submissionRepository->countByCompetitionId($competitionId);

        // Add the current live data point as the very latest on the graph
        if (empty($labels) || end($initiatedData) !== $currentInitiated || end($processedData) !== $currentProcessed) {
            $labels[] = (new \DateTimeImmutable())->format('H:i:s') . ' (Now)';
            $initiatedData[] = $currentInitiated;
            $processedData[] = $currentProcessed;
        }

        // 3. Build the Chart.js object
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
                ]
            ],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'title' => [
                    'display' => true,
                    'text' => $chartTitlePrefix // Dynamic chart title
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'callbacks' => [
                        'label' => 'function(context) { return context.dataset.label + ": " + context.parsed.y.toLocaleString(); }',
                    ],
                ],
                'zoom' => [
                    'zoom' => [
                        'wheel' => [ 'enabled' => true ],
                        'pinch' => [ 'enabled' => true ],
                        'mode' => 'x',
                    ],
                    'pan' => [
                        'enabled' => true,
                        'mode' => 'x',
                    ],
                ],
            ],
            'scales' => [
                'x' => [ 'title' => [ 'display' => true, 'text' => 'Time' ] ],
                'y' => [
                    'beginAtZero' => true,
                    'title' => [ 'display' => true, 'text' => 'Number of Submissions' ],
                    'ticks' => [
                        'callback' => 'function(value) { return value.toLocaleString(); }',
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