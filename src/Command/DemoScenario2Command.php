<?php

namespace App\Command;


use App\Entity\Competition;
use App\Entity\User;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionStatsSnapshotRepository;
use App\Repository\SubmissionRepository;
use App\Repository\UserRepository;
use App\Repository\WinnerRepository;
use App\Service\MercurePublisherService; // Still needed for initial competition status updates
use App\Service\MessageProducerService; // To produce real submission messages
use App\Service\RedisKeyBuilder;
use App\Service\RedisManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;


#[AsCommand(
    name: 'app:demo-scenario-2',
    description: 'Demonstrates competition lifecycle and real-time submission charts by producing real RabbitMQ messages.',
)]
class DemoScenario2Command extends Command
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private CompetitionStatsSnapshotRepository $competitionStatsSnapshotRepository,
        private SubmissionRepository $submissionRepository,
        private WinnerRepository $winnerRepository,
        private RedisManager $redisManager,
        private RedisKeyBuilder $redisKeyBuilder,
        private MercurePublisherService $mercurePublisherService,
        private MessageProducerService $messageProducerService,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }


    protected function configure(): void
    {
        $this
            ->addOption('clear-only', null, InputOption::VALUE_NONE, 'If set, only clears existing demo competitions and exits.')
            ->addArgument('send-rate', InputArgument::OPTIONAL, 'Messages per second to attempt to send (0 for no rate limit)', 300)
            ->addArgument('duration', InputArgument::OPTIONAL, 'Simulation Duration', 100)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $competitionConfigs = [
            ['title' => 'Demo Comp A (5s Start, 100s End)', 'start' => '+5 seconds', 'end' => '+100 seconds'],
            ['title' => 'Demo Comp B (10s Start, 75s End)', 'start' => '+10 seconds', 'end' => '+75 seconds'],
            ['title' => 'Demo Comp C (20s Start, 60s End)', 'start' => '+20 seconds', 'end' => '+60 seconds'],
            ['title' => 'Demo Comp D (40s Start, 50s End)', 'start' => '+40 seconds', 'end' => '+50 seconds'],
        ];


        $io = new SymfonyStyle($input, $output);
        $io->title('Starting Demo Scenario 1: Competition Lifecycle & Real-time Charts with Real Messages');

        $baseSendRate = (int) $input->getArgument('send-rate');
        $duration = (int) $input->getArgument('duration');

        // --- 1. Get an Organizer User ---
        /** @var User|null $organizerUser */
        $organizerUser = $this->userRepository->findOneBy(['email' => 'admin@symfony.com']);

        if (!$organizerUser) {
            $io->error('No organizer user found. Please create a user with email "admin@symfony.com" or change the email in the command.');
            return Command::FAILURE;
        }
        $io->text(sprintf('Using organizer user: %s', $organizerUser->getUserIdentifier()));


        $this->redisManager->deleteKey(RedisKeyBuilder::GLOBAL_ANNOUNCEMENT_KEY);

     // --- Clear existing competitions with the new demo titles ---
        $io->section('Clearing Existing Demo Competitions...');
        $this->clearExistingCompetitions(array_column($competitionConfigs, 'title'));
        $io->success('Existing demo competitions and their data cleared.');

        if ($input->getOption('clear-only')) {
            $io->info('"--clear-only" option detected. Exiting after clearing data.');
            return Command::SUCCESS;
        }

              // --- 2. Create Competitions ---
        $io->section('Creating Competitions for Scenario 2...');
        $competitions = [];
        $now = new \DateTime();


        foreach ($competitionConfigs as $config) {
            $comp = $this->createCompetition(
                $config['title'],
                (clone $now)->modify($config['start']),
                (clone $now)->modify($config['end'])
            );
            $this->updateCompetition($comp, $organizerUser);
            $competitions[] = $comp;
            if (str_contains($config['title'], 'Demo Comp D')) {
                $compD = $comp; // Assign reference
            }
            $io->text(sprintf('Created %s (ID: %d)', $config['title'], $comp->getId()));
        }

        $this->entityManager->flush();
        $io->success('Competitions created and persisted.');


        // --- 3. Simulate Submission Activity by Producing Real Messages ---
        $io->section('Simulating Real Submission Activity...');
        $io->text('Keep your EasyAdmin dashboard open (e.g., /admin) and watch the charts.');
        $io->text('Ensure your Messenger consumers are running.');
        $io->text('Ensure Scheduler/Cronjob is running to capture snapshots.');

        $totalSubmissionsProduced = 0;

    for ($time = 0; $time <= $duration; ++$time) {
            $io->text(sprintf("\n--- Simulating at T+%d seconds ---", $time));
            sleep(1);
            $loopTime = new \DateTime();

            // First, determine if Competition D is currently running
            $this->entityManager->refresh($compD);
            $isCompDRunning = $compD->getStatus() === 'running';

            foreach ($competitions as $comp) {
                $this->entityManager->refresh($comp);

                $title = $comp->getTitle();

                // Suppression Logic: Pause A and B if D is running
                if ((str_contains($title, 'Demo Comp A') || str_contains($title, 'Demo Comp B')) && $isCompDRunning) {
                    $io->text(sprintf('  Pausing production for %s because Comp D is running.', $title));
                    continue; // Skip to the next competition
                }

                if ($comp->getStatus() === 'running') {
                    $currentIntervalRate = $baseSendRate;
                    $burstMessage = '';

                    // Burst logic for Competition D (entire duration)
                    if (str_contains($title, 'Demo Comp D')) {
                        $currentIntervalRate *= 5;
                        $burstMessage = ' (BURST x5)';
                    }

                    // Burst logic for Competition B (last 10 seconds)
                    // if (str_contains($title, 'Demo Comp B')) {
                    //     $secondsUntilEnd = $comp->getEndDate()->getTimestamp() - $loopTime->getTimestamp();
                    //     if ($secondsUntilEnd > 0 && $secondsUntilEnd <= 10) {
                    //         $currentIntervalRate *= 5;
                    //         $burstMessage = ' (BURST x5 - Final 10s)';
                    //     }
                    // }

                    // Burst logic for Competition A (first 10 seconds)
                    if (str_contains($title, 'Demo Comp A')) {
                        $secondsSinceStart = $loopTime->getTimestamp() - $comp->getStartDate()->getTimestamp();
                        if ($secondsSinceStart >= 0 && $secondsSinceStart <= 25) {
                            $currentIntervalRate *= 5; // 
                            $burstMessage = ' (BURST x5 - First 10s)';
                        }
                        $secondsUntilEnd = $comp->getEndDate()->getTimestamp() - $loopTime->getTimestamp();
                        if ($secondsUntilEnd > 0 && $secondsUntilEnd <= 10) {
                            // continue;
                            $currentIntervalRate *= 3;
                            $burstMessage = ' (BURST x3 - Final 10s)';
                        }
                    }

                    $io->text(sprintf('  Producing %d messages for RUNNING Comp %s%s...', $currentIntervalRate, $title, $burstMessage));

                    for ($i = 0; $i < $currentIntervalRate; ++$i) {
                        $this->produceMessageForCompetition($comp, $totalSubmissionsProduced);
                        $totalSubmissionsProduced++;
                    }
                } else {
                    $io->text(sprintf('  Comp %s: Status is "%s".', $title, $comp->getStatus()));
                }
            }
        }
        $io->section('Demo Scenario 2 Finished.');
        $io->success('Demonstration complete!');

        return Command::SUCCESS;
    }
    
    private function produceMessageForCompetition(Competition $comp, int $submissionIndex): void
    {
        $dummyCompetitionEndTimestamp = $comp->getEndDate()->getTimestamp();
        $priorityKey = $this->messageProducerService->identifyPriorityKey($dummyCompetitionEndTimestamp);
        $email = sprintf('user_%d_%d_%s@example.com', $comp->getId(), $submissionIndex, uniqid());
        $formData = [
            'email' => $email,
            'phoneNumber' => '696969690' . $priorityKey,
            'priorityKey' => $priorityKey,
        ];

        $this->messageProducerService->produceSubmissionMessage(
            (string) $dummyCompetitionEndTimestamp,
            $formData,
            $comp->getId(),
            $email
        );
        $this->redisManager->incrementValue($this->redisKeyBuilder->getCompetitionCountKey($comp->getId()));
    }
    /**
     * Helper to create and persist a Competition entity.
     */
    private function createCompetition(string $title, \DateTime $startDate, \DateTime $endDate): Competition
    {
        $competition = new Competition();
        $competition->setTitle($title);
        $competition->setDescription('Demo competition for scenario 1.');
        $competition->setPrizes('Demo Prizes');
        $competition->setStartDate($startDate);
        $competition->setEndDate($endDate);
        $competition->setMaxParticipants(100000);
        $competition->setNumberOfWinners(rand(2, 5));
        $competition->setStatus('draft'); // Start as draft

        $this->entityManager->persist($competition);
        $this->entityManager->flush();

        // Reset Redis counter for this competition for a clean demo
        $this->redisManager->setValue($this->redisKeyBuilder->getCompetitionCountKey($competition->getId()), 0);

        return $competition;
    }

    private function updateCompetition(Competition $competition, User $createdBy)
    {
        $competition->setStatus('scheduled');
        $competition->setCreatedBy($createdBy);
        $this->entityManager->flush();
    }


    /**
     * Clears existing demo competitions and their associated data using raw SQL for children, and ORM for parent.
     *
     * @param array $titlesToDelete An array of competition titles to delete.
     */
    private function clearExistingCompetitions(array $titlesToDelete): void
    {
        /** @var Connection $connection */
        $connection = $this->entityManager->getConnection();

        // Find existing competitions by title to get their IDs
        $existingCompetitionIds = $this->competitionRepository->createQueryBuilder('c')
            ->select('c.id')
            ->where('c.title IN (:titles)')
            ->setParameter('titles', $titlesToDelete)
            ->getQuery()
            ->getSingleColumnResult(); // Get a flat array of IDs

        if (empty($existingCompetitionIds)) {
            $this->logger->info('No existing demo competitions found to clear.');
            return;
        }

        $this->logger->info(sprintf('Clearing data for %d existing demo competitions.', count($existingCompetitionIds)));

        // Start a single transaction for all deletions to ensure atomicity
        $connection->beginTransaction();
        try {
            // Use placeholders and PARAM_INT_ARRAY for safe and correct IN clause with executeStatement
            // This ensures the array of IDs is handled properly by the database driver.

            // 1. Delete CompetitionStatsSnapshot records
            $this->logger->info(sprintf('  Deleting snapshots for competitions: %s', implode(',', $existingCompetitionIds)));
            $connection->executeStatement(
                "DELETE FROM competition_stats_snapshot WHERE competition_id IN (?)",
                [$existingCompetitionIds],
                [Connection::PARAM_INT_ARRAY] // Correct type for array of integers
            );

            // 2. Delete Winner records
            $this->logger->info(sprintf('  Deleting winners for competitions: %s', implode(',', $existingCompetitionIds)));
            $connection->executeStatement(
                "DELETE FROM winner WHERE competition_id IN (?)",
                [$existingCompetitionIds],
                [Connection::PARAM_INT_ARRAY]
            );

            // 3. Delete Submission records
            $this->logger->info(sprintf('  Deleting submissions for competitions: %s', implode(',', $existingCompetitionIds)));
            $connection->executeStatement(
                "DELETE FROM submission WHERE competition_id IN (?)",
                [$existingCompetitionIds],
                [Connection::PARAM_INT_ARRAY]
            );

            // 4. Delete the Competition entities themselves using ORM
            foreach ($existingCompetitionIds as $competitionId) {
                // Re-fetch the competition entity to ensure it's managed by the current EntityManager instance
                // This is crucial after raw SQL operations which invalidate the EM's UnitOfWork for these entities.
                $competition = $this->competitionRepository->find($competitionId);

                if ($competition) { // Check if it still exists (might have been deleted by another process, though unlikely here)
                    $this->entityManager->remove($competition);
                    $this->logger->info(sprintf('    Marked Competition entity ID %d for removal.', $competitionId));
                } else {
                    $this->logger->warning(sprintf('Competition entity with ID %d not found for ORM deletion, skipping.', $competitionId));
                }
            }
            $this->entityManager->flush(); // Flush all marked Competition deletions
            $this->logger->info('    Removed Competition entities via ORM.');


            // 5. Reset Redis counters for these competitions
            foreach ($existingCompetitionIds as $competitionId) {
                $this->redisManager->deleteKey($this->redisKeyBuilder->getCompetitionCountKey($competitionId));
                $this->logger->info(sprintf('    Cleared Redis counter for competition ID: %d.', $competitionId));
            }

            $connection->commit(); // Commit the entire transaction
            $this->logger->info('All demo competition data cleared successfully via mixed SQL/ORM.');
        } catch (\Throwable $e) {
            // Rollback if any error occurs
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->logger->error(sprintf('Error clearing demo data with mixed SQL/ORM: %s', $e->getMessage()), ['exception' => $e]);
            $this->logger->error('Transaction rolled back due to error.');
            throw $e; // Re-throw to indicate command failure
        } finally {
            // Ensure EntityManager is clear after potential raw SQL operations
            // This prevents it from holding stale data or detached entities for subsequent operations in the command.
            $this->entityManager->clear();
        }
    }
}
