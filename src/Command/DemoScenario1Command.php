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


// docker compose exec rabbitmq sh -c "rabbitmqadmin -u guest -p guest list queues name -f tsv | xargs -I {} rabbitmqadmin -u guest -p guest purge queue name={}"
// php bin/console app:demo-scenario-1 --clear-only; php bin/console app:demo-scenario-1 400 30
#[AsCommand(
    name: 'app:demo-scenario-1',
    description: 'Demonstrates competition lifecycle and real-time submission charts by producing real RabbitMQ messages.',
)]
class DemoScenario1Command extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private CompetitionStatsSnapshotRepository $competitionStatsSnapshotRepository,
        private SubmissionRepository $submissionRepository,
        private WinnerRepository $winnerRepository, // NEW: Inject WinnerRepository
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
            ->addOption('clear-only', null, InputOption::VALUE_NONE, 'If set, only clears existing demo competitions and exits.') // NEW: Add --clear-only option
            ->addArgument('send-rate', InputArgument::OPTIONAL, 'Messages per second to attempt to send (0 for no rate limit)', 300)
            ->addArgument('duration', InputArgument::OPTIONAL, 'Simulation Duration', 100)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting Demo Scenario 1: Competition Lifecycle & Real-time Charts with Real Messages');

        $sendRate = (int) $input->getArgument('send-rate');
        $duration = (int) $input->getArgument('duration');

        // --- 1. Get an Organizer User ---
        /** @var User|null $organizerUser */
        $organizerUser = $this->userRepository->findOneBy(['email' => 'admin@symfony.com']);

        if (!$organizerUser) {
            $io->error('No organizer user found. Please create a user with email "	admin@symfony.com" or change the email in the command.');
            return Command::FAILURE;
        }
        $io->text(sprintf('Using organizer user: %s', $organizerUser->getUserIdentifier()));


        $this->redisManager->deleteKey(RedisKeyBuilder::GLOBAL_ANNOUNCEMENT_KEY);

        // --- NEW: Clear existing competitions with the same demo titles ---
        $io->section('Clearing Existing Demo Competitions...');
        $this->clearExistingCompetitions([
            'Demo Comp A (10s Start)',
            'Demo Comp B (20s Start)',
            'Demo Comp C (30s Start)',
            'Demo Comp D (10s Start)', // Include all demo competition titles
        ]);
        $io->success('Existing demo competitions and their data cleared.');

        if ($input->getOption('clear-only')) {
            $io->info('"--clear-only" option detected. Exiting after clearing data.');
            return Command::SUCCESS;
        }

        // --- 2. Create 3 Competitions with Specific Timings ---
        $io->section('Creating Competitions...');
        $competitions = [];
        $now = new \DateTime();

        // $comp1 = $this->createCompetition(
        //     'Demo Comp A (10s Start)',
        //     new \DateTime()->modify('+10 seconds'),
        //     new \DateTime()->modify('+4 minutes'),
        // );
        // $competitions[] = $comp1;
        // $this->updateCompetition($comp1, $organizerUser);
        // $io->text(sprintf('Created Competition A (ID: %d): Starts in 10s, Ends in 4m', $comp1->getId()));

        // $comp2 = $this->createCompetition(
        //     'Demo Comp B (20s Start)',
        //     new \DateTime()->modify('+20 seconds'),
        //     new \DateTime()->modify('+2 minutes'),
        // );
        // $this->updateCompetition($comp2, $organizerUser);
        // $competitions[] = $comp2;
        // $io->text(sprintf('Created Competition B (ID: %d): Starts in 20s, Ends in 2m', $comp2->getId()));

        // $comp3 = $this->createCompetition(
        //     'Demo Comp C (30s Start)',
        //     new \DateTime()->modify('+30 seconds'),
        //     new \DateTime()->modify('+1 minutes'),
        // );
        // $this->updateCompetition($comp3, $organizerUser);
        // $competitions[] = $comp3;
        // $io->text(sprintf('Created Competition C (ID: %d): Starts in 30s, Ends in 1m', $comp3->getId()));

        $comp4 = $this->createCompetition(
            'Demo Comp D (10s Start)',
            new \DateTime()->modify('+8 seconds'),
            new \DateTime()->modify('+30 seconds'),
        );
        $this->updateCompetition($comp4, $organizerUser);
        $competitions[] = $comp4;
        $io->text(sprintf('Created Competition D (ID: %d): Starts in 8s, Ends in 30s', $comp4->getId()));

        $this->entityManager->flush(); // Persist all competitions

        $io->success('Competitions created and persisted.');

        // --- 3. Simulate Submission Activity by Producing Real Messages ---
        $io->section('Simulating Real Submission Activity...');
        $io->text('Keep your EasyAdmin dashboard open (e.g., /admin) and watch the charts.');
        $io->text('Ensure your Messenger consumers are running.');
        $io->text('Ensure Scheduler/Cronjob is running to capture snapshots.');

        $simulationDuration = $duration; // Total simulation time in seconds
        $interval = 1; // Simulate activity every 5 seconds (produce messages every X seconds)
        $submissionsPerInterval = $sendRate; // Number of submissions to produce per interval
        $failedSubmissionRate = 0.1; // 10% of submissions will be marked as "failed" for demo

        $totalSubmissionsProduced = 0;

        for ($time = 0; $time <= $simulationDuration; $time += $interval) {
            $io->text(sprintf("\n--- Simulating at T+%d seconds ---", $time));
            sleep($interval); // Pause for real-time observation
            foreach ($competitions as $comp) {
                try {
                    $this->entityManager->refresh($comp);
                } catch (\Throwable $e) {
                    $io->error('Database is down.');
                    $this->entityManager->getConnection()->close();
                }
                if ($comp->getStatus() === 'running') {
                    $io->text(sprintf('  Producing %d messages for Comp %s (ID: %d)...', $submissionsPerInterval, $comp->getTitle(), $comp->getId()));
                    
                    for ($i = 0; $i < $submissionsPerInterval; $i++) {
                        $dummyCompetitionEndTimestamp = $comp->getEndDate()->getTimestamp();
                        $priorityKey = $this->messageProducerService->identifyPriorityKey($dummyCompetitionEndTimestamp);

                        $email = sprintf('user_%d_%d_%s@example.com', $comp->getId(), $totalSubmissionsProduced, uniqid());
                        $formData = [
                            'email' => $email,
                            'phoneNumber' => '696969690' . $priorityKey,
                            'priorityKey' => $priorityKey
                        ];


                        $this->messageProducerService->produceSubmissionMessage(
                            (string) $dummyCompetitionEndTimestamp,
                            $formData,
                            $comp->getId(),
                            $email
                        );
                        $this->redisManager->incrementValue($this->redisKeyBuilder->getCompetitionCountKey($comp->getId()));

                        $totalSubmissionsProduced++;
                    }
                    $io->text(sprintf('  Produced %d messages for Comp %s (ID: %d). Total produced: %d', $submissionsPerInterval, $comp->getTitle(), $comp->getId(), $totalSubmissionsProduced));
                } else {
                    $io->text(sprintf('  Comp %s (ID: %d): Status "%s", not running.', $comp->getTitle(), $comp->getId(), $comp->getStatus()));
                }
            }
        }

        $io->section('Demo Scenario 1 Finished.');
        $io->success('Demonstration complete!');

        return Command::SUCCESS;
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
        // This is important because the initial submission controller increments it.
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


    // Adjusted thresholds for demonstration
    // /** Returns Scheduling Delays in Milliseconds  */
    // public function calculateStatusTransitionDelays(
    //     Competition $competition,
    //     int $winnerGracePeriod = 10,     // 10 seconds
    //     int $archiveAfter = 259200       // 3 days in seconds
    // ): array {

    //     $now = new \DateTimeImmutable('now');
    //     $now = $now->getTimestamp();

    //     $start = $competition->getStartDate()->getTimestamp();
    //     $end = $competition->getEndDate()->getTimestamp();

    //     $runningDelay = $start - $now;
    //     $submissionsEndedDelay = $end - $now;

    //     $winnersGeneratedDelay = ($end + $winnerGracePeriod) - $now;
    //     $archivedDelay = ($end + $archiveAfter) - $now;

    //     return [
    //         'running' => (int) $runningDelay * 1000,
    //         'submissions_ended' => (int) $submissionsEndedDelay * 1000,
    //         'winners_announced' => (int) $winnersGeneratedDelay * 1000,
    //         'archived' => (int) $archivedDelay * 1000,
    //     ];
    // }