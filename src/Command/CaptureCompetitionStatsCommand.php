<?php

namespace App\Command;

use App\Entity\CompetitionStatsSnapshot;
use App\Repository\CompetitionRepository;
use App\Repository\SubmissionRepository;
use App\Service\CompetitionSnapshotService;
use App\Service\MercurePublisherService;
use App\Service\RabbitMqManagementService;
use App\Service\RedisKeyBuilder;
use App\Service\RedisManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:capture-competition-stats',
    description: 'Captures a snapshot of initiated and processed submission counts for active competitions.',
)]
class CaptureCompetitionStatsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RedisManager $redisManager,
        private RedisKeyBuilder $redisKeyBuilder,
        private CompetitionRepository $competitionRepository,
        private SubmissionRepository $submissionRepository,
        private RabbitMqManagementService $rabbitMqManagement,
        private LoggerInterface $logger,
        private MercurePublisherService $mercurePublisher,
        private CompetitionSnapshotService $competitionSnapshotService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->logger->info('Starting competition stats snapshot capture...');
        $io->info('Starting competition stats snapshot capture...');

        // Fetch all competitions that are currently 'running' or 'submissions_ended'
        // You might want to adjust this to include 'scheduled' if you want to track from start
        $activeCompetitions = $this->competitionRepository->findBy(['status' => ['running', 'submissions_ended']]);

        if (empty($activeCompetitions)) {
            $io->warning('No active competitions found to capture stats for.');
            $this->logger->info('No active competitions found to capture stats for.');
            return Command::SUCCESS;
        }

        $capturedCount = 0;
        foreach ($activeCompetitions as $competition) {
            try {

                $snapshot = $this->competitionSnapshotService->generateSnapshotEntity($competition);

                $this->entityManager->persist($snapshot);
                $capturedCount++;
                
                $io->success(sprintf('Captured Snapshot for %d.', $competition->getId()));
                
                $this->mercurePublisher->publishUpdateChart($competition->getId(), $snapshot);

            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Failed to capture stats for competition ID %d: %s',
                    $competition->getId(),
                    $e->getMessage()
                ), ['exception' => $e]);
                $io->error(sprintf('Error for competition %d: %s', $competition->getId(), $e->getMessage()));
            }
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $io->success(sprintf('Captured %d stats snapshots for %d active competitions.', $capturedCount, count($activeCompetitions)));
        $this->logger->info(sprintf('Captured %d stats snapshots for %d active competitions.', $capturedCount, count($activeCompetitions)));

        return Command::SUCCESS;
    }
}
