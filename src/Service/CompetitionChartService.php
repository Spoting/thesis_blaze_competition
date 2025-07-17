<?php

namespace App\Service;

use App\Entity\Competition;
use App\Repository\CompetitionStatsSnapshotRepository;
use App\Repository\CompetitionStatusTransitionRepository;
use App\Repository\SubmissionRepository;
use Symfony\Component\Mercure\HubInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class CompetitionChartService
{
    public function __construct(
        private ChartBuilderInterface $chartBuilder,
        private CompetitionStatsSnapshotRepository $competitionStatsSnapshotRepository,
        private CompetitionStatusTransitionRepository $competitionStatusTransitionRepository,
        private RedisManager $redisManager,
        private RedisKeyBuilder $redisKeyBuilder,
        private SubmissionRepository $submissionRepository,
        private HubInterface $mercureHub
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

        // Fetch snapshots and build Chart Data
        $snapshots = $this->competitionStatsSnapshotRepository->findSnapshotsForCompetition(
            $competitionId,
        );

        $labels = [];
        $initiatedData = [];
        $processedData = [];
        $failedData = [];

        foreach ($snapshots as $snapshot) {
            $initiated = $snapshot->getInitiatedSubmissions();
            $processed = $snapshot->getProcessedSubmissions();
            $failed = $snapshot->getFailedSubmissions();

            $labels[] = $snapshot->getCapturedAt()->format('H:i:s'); // Format time as HH:MM
            $initiatedData[] = $initiated;
            $processedData[] = $processed;
            $failedData[] = $failed;
        }


        $annotations = $this->produceTransititonAnnotations($competitionId);


        // Build the Chart.js object
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Initiated Submissions (' . $initiated . ')',
                    'data' => $initiatedData,
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'fill' => true,
                    'tension' => 0.1
                ],
                [
                    'label' => 'Processed Submissions (' . $processed . ')',
                    'data' => $processedData,
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'fill' => true,
                    'tension' => 0.1
                ],
                [
                    'label' => 'Failed Submissions (DLQ) (' . $failed . ')',
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
                'annotation' => [
                    'annotations' => $annotations,
                ],
            ],
            'scales' => [
                'x' => [
                    'type' => 'time',
                    'time' => [
                        'unit' => 'second',
                        'tooltipFormat' => 'HH:mm:ss',
                        'displayFormats' => [
                            'minute' => 'HH:mm',
                            'second' => 'HH:mm:ss',
                        ],
                    ],
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
                    'min' => 0,
                ],
            ],
        ]);

        // Generate the Mercure subscribe URL 

        $mercureTopic = sprintf(MercurePublisherService::COMPETITION_STATS, $competitionId);
        $mercureUrl = $this->mercureHub->getPublicUrl() . '?topic=' . urlencode($mercureTopic);

        return [
            'competitionId' => $competitionId,
            'competitionTitle' => $competitionTitle,
            'chart' => $chart,
            'mercureUrl' => $mercureUrl,
        ];
    }

    public function produceTransititonAnnotations(int $competitionId): array
    {

        $annotations = [];

        // Fetch Transistions
        $transitions = $this->competitionStatusTransitionRepository->findTransitionsForCompetition(
            $competitionId
        );

        foreach ($transitions as $index => $transition) {
            $transitionTime = $transition->getTransitionedAt()->format('H:i:s');
            $newStatus = $transition->getNewStatus();

            // Assign unique ID for the annotation
            $annotationId = "status-{$newStatus}-{$index}-" . $transition->getTransitionedAt()->getTimestamp();


            $color = 'rgba(0,0,0,0.7)'; // Default black
            $labelContent = \App\Entity\Competition::STATUSES[$newStatus] ?? $newStatus;
            $yAdjust = 0; // Default vertical adjustment
            switch ($newStatus) {
                case 'draft':
                    $color = 'rgba(108, 117, 125, 0.7)';
                    $yAdjust = -100;
                    break; // Gray
                case 'scheduled':
                    $color = 'rgba(0, 123, 255, 0.7)';
                    $yAdjust = -70;
                    break; // Blue
                case 'running':
                    $color = 'rgba(40, 167, 69, 0.7)';
                    $yAdjust = -10;
                    break; // Green
                case 'submissions_ended':
                    $color = 'rgba(220, 53, 69, 0.7)';
                    $yAdjust = -30;
                    break; // Red
                case 'winners_announced':
                    $color = 'rgba(255, 193, 7, 0.7)';
                    $yAdjust = -50;
                    break; // Orange/Yellow
                case 'archived':
                    $color = 'rgba(0, 0, 0, 0.7)';
                    $yAdjust = -90;
                    break; // Black
                    // case 'cancelled': $color = 'rgba(255, 0, 0, 0.7)'; $yAdjust = -110; break; // Bright Red
            }

            $annotations[$annotationId] = [
                'type' => 'line',
                'mode' => 'vertical',
                'scaleID' => 'x',
                'value' => $transitionTime,
                'borderColor' => $color,
                'borderWidth' => 2,
                'label' => [
                    'content' => $labelContent,
                    'display' => true,
                    'enabled' => true,
                    'position' => 'end',
                    'backgroundColor' => $color,
                    'color' => 'white',
                    'font' => ['size' => 10],
                    'yAdjust' => $yAdjust, // Adjust label position to avoid overlap
                ]
            ];
        }

        return $annotations;
    }
}
