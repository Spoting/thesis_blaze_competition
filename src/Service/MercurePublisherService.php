<?php

namespace App\Service;

use App\Entity\Competition;
use App\Entity\CompetitionStatsSnapshot;
use App\Entity\CompetitionStatusTransition;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

class MercurePublisherService
{

    public const ANNOUNCEMENT_TOPIC = '/global_announcements';
    public const COMPETITIONS_TOPIC = '/competitions';
    public const COMPETITION_STATS = '/competition/%d/stats';

    private HubInterface $hub;
    private Environment $twig;

    public function __construct(
        HubInterface $hub,
        Environment $twig
    ) {
        $this->hub = $hub;
        $this->twig = $twig;
    }


    public function publishAnnouncement(string $status, string $message): void
    {

        $topic = self::ANNOUNCEMENT_TOPIC;
        $update = new Update(
            $topic, // The topic(s) to publish to
            json_encode([
                'type' => 'new_announcement', // A custom identifier for the JS
                'status' => $status,
                'message' => $message,
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
            ]),
        );

        $this->hub->publish($update);
    }

    /**
     * Publishes a Mercure update for a specific competition.
     *
     * @param Competition $competition The competition entity that has been updated.
     */
    public function publishCompetitionUpdate(Competition $competition): void
    {
        $isPublic = in_array($competition->getStatus(), Competition::PUBLIC_STATUSES);

        $renderedHtml = null;

        if ($isPublic) {
            $renderedHtml = $this->twig->render('public/_competition_teaser.html.twig', [
                'competition' => $competition,
                'statusLabels' => Competition::STATUSES,
                'showSubmitButton' => $competition->canAcceptSubmissions()
            ]);
        }

        $topic = self::COMPETITIONS_TOPIC;

        $update = new Update(
            $topic,
            json_encode([
                'html' => $renderedHtml,
                'id' => $competition->getId(),
                'status' => $competition->getStatus(),
                'statusLabels' => Competition::STATUSES,
            ])
        );

        $this->hub->publish($update);
    }


    public function publishUpdateChart(int $competitionId, CompetitionStatsSnapshot $snapshot)
    {
        $newLabels = $snapshot->getCapturedAt()->format('H:i:s'); // Format time as HH:MM
        $newInitiatedData = (int) $snapshot->getInitiatedSubmissions();
        $newProcessedData = (int) $snapshot->getProcessedSubmissions();
        $newFailedData = (int) $snapshot->getFailedSubmissions();

        $data = [
            'type' => 'snapshot',
            'data' => [
                'labels' => [$newLabels],
                'datasets' => [
                    [
                        'label' => 'Initiated Submissions (' . $newInitiatedData . ')',
                        'data' => [$newInitiatedData],
                    ],
                    [
                        'label' => 'Processed Submissions (' . $newProcessedData . ')',
                        'data' => [$newProcessedData],
                    ],
                    [
                        'label' => 'Failed Submissions (DLQ) (' . $newFailedData . ')',
                        'data' => [$newFailedData],
                    ],
                ],
            ]

        ];

        $topic = sprintf(self::COMPETITION_STATS, $competitionId);

        $update = new Update(
            $topic,
            json_encode($data)
        );

        $this->hub->publish($update);
    }


    public function publishStatusTransitionAnnotation(Competition $competition, CompetitionStatusTransition $transition, int $index = 0): void
    {
        $competitionId = $competition->getId();
        $newStatus = $transition->getNewStatus();
        $transitionTime = $transition->getTransitionedAt()->format('H:i:s'); // Must match chart label format
        $timestamp = $transition->getTransitionedAt()->getTimestamp();

        // Unique annotation ID for Chart.js
        $annotationId = "status-{$newStatus}-{$index}-{$timestamp}";

        $color = 'rgba(0,0,0,0.7)';
        $yAdjust = 0;
        $labelContent = Competition::STATUSES[$newStatus] ?? $newStatus;

        switch ($newStatus) {
            case 'draft':
                $color = 'rgba(108, 117, 125, 0.7)';
                $yAdjust = -100;
                break;
            case 'scheduled':
                $color = 'rgba(0, 123, 255, 0.7)';
                $yAdjust = -70;
                break;
            case 'running':
                $color = 'rgba(40, 167, 69, 0.7)';
                $yAdjust = -10;
                break;
            case 'submissions_ended':
                $color = 'rgba(220, 53, 69, 0.7)';
                $yAdjust = -30;
                break;
            case 'winners_announced':
                $color = 'rgba(255, 193, 7, 0.7)';
                $yAdjust = -50;
                break;
            case 'archived':
                $color = 'rgba(0, 0, 0, 0.7)';
                $yAdjust = -90;
                break;
        }

        $annotationPayload = [
            'type' => 'status',
            'annotation' => [
                'id' => $annotationId,
                'value' => $transitionTime,
                'borderColor' => $color,
                'labelContent' => $labelContent,
                'yAdjust' => $yAdjust,
            ],
        ];

        $topic = sprintf(self::COMPETITION_STATS, $competitionId);

        $update = new Update(
            $topic,
            json_encode($annotationPayload)
        );

        $this->hub->publish($update);
    }
}
