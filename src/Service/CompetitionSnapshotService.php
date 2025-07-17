<?php

namespace App\Service;

use App\Entity\Competition;
use App\Entity\CompetitionStatsSnapshot;
use App\Repository\SubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CompetitionSnapshotService
{
    public function __construct(
        private RedisManager $redisManager,
        private RedisKeyBuilder $redisKeyBuilder,
        private SubmissionRepository $submissionRepository,
        private RabbitMqManagementService $rabbitMqManagement,
        private LoggerInterface $logger,
        private MercurePublisherService $mercurePublisher,
    ) {}

    /**
     * Captures the current statistics (initiated, processed, DLQ) for a given competition,
     * persists them as a snapshot, and publishes the update to Mercure.
     *
     * @param Competition $competition The competition to capture stats for.
     * @return CompetitionStatsSnapshot The created snapshot entity.
     * @throws \Exception If any critical error occurs during capture/publish.
     */
    public function generateSnapshotEntity(Competition $competition): CompetitionStatsSnapshot
    {
        try {
            $competitionId = $competition->getId();
            $initiatedSubmissions = (int) $this->redisManager->getValue(
                $this->redisKeyBuilder->getCompetitionCountKey($competitionId)
            ) ?? 0;
            $processedSubmissions = $this->submissionRepository->countByCompetitionId($competitionId);

            $failedSubmissions = $this->rabbitMqManagement->getQueueMessageCount('dlq_competition_submission_' . $competitionId);

            $snapshot = new CompetitionStatsSnapshot();
            $snapshot->setCompetition($competition);
            $snapshot->setCapturedAt(new \DateTimeImmutable());
            $snapshot->setInitiatedSubmissions($initiatedSubmissions);
            $snapshot->setProcessedSubmissions($processedSubmissions);
            $snapshot->setFailedSubmissions($failedSubmissions);

            return $snapshot;

        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'Failed to capture and publish snapshot for competition ID %d: %s',
                $competitionId,
                $e->getMessage()
            ), ['exception' => $e]);
            throw new \Exception(sprintf('Snapshot capture failed for competition %d: %s', $competitionId, $e->getMessage()), 0, $e);
        }
    }
}
