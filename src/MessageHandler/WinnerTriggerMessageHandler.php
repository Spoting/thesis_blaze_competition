<?php

namespace App\MessageHandler;

use App\Entity\Competition;
use App\Entity\Submission;
use App\Entity\Winner;
use App\Message\WinnerTriggerMessage;
use App\Repository\SubmissionRepository;
use App\Repository\WinnerRepository;
use App\Service\CompetitionStatusManagerService;
use App\Service\MessageProducerService;
use App\Service\RedisKeyBuilder;
use App\Service\RedisManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final class WinnerTriggerMessageHandler
{
    // Inject the EntityManagerInterface via the constructor
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private MessageProducerService $messageProducerService,
        private CompetitionStatusManagerService $competitionStatusManager,
        private RedisManager $redisManager,
        private RedisKeyBuilder $redisKeyBuilder,
        private SubmissionRepository $submissionRepository,
        private WinnerRepository $winnerRepository,
    ) {}

    public function __invoke(WinnerTriggerMessage $message)
    {
        $targetStatus = 'winners_announced';
        $competitionId = $message->getCompetitionId();
        $messageCreationDate = $message->getMessageCreationDate();
        $delayTime = $message->getDelayTime();

        dump(sprintf('Winner generation triggered for competition: %s', $competitionId));
        dump($message->getMessageCreationDate()
            . " | " . $message->getDelayTime());


        $this->logger->info(sprintf(
            'Attempting Winner Generation for competition ID: %s. Target Status: %s. Message created: %s. Delayed by: %d seconds.',
            $competitionId,
            $targetStatus,
            $messageCreationDate,
            $delayTime
        ));

        try {
            $this->entityManager->getConnection()->beginTransaction();

            /** @var Competition */
            $competition = $this->entityManager->getRepository(Competition::class)->find($competitionId);

            // Validate Status Change
            $isStatusValid = $this->competitionStatusManager->isStatusTransitionValid($competition, $targetStatus);
            if (!$isStatusValid) {
                // dont attempt retry

                throw new UnrecoverableMessageHandlingException(sprintf(
                    'Invalid status transition for competition %s. Current status: %s, Target: %s.',
                    $competitionId,
                    $competition->getStatus(),
                    $targetStatus
                ));
            }

            // Query Actuall Processed Submissions
            $processedSubmissionCount = $this->submissionRepository->countByCompetitionId($competitionId);

            if (!$this->shouldGenerateWinners($processedSubmissionCount, $competition)) {
                // Dont attempt to generate/announce winners Yet
                // NACK message and retry later.
                throw new \Exception('Not all Submittions has been Processed for : ' . $competition->getId());
            }

            // Generate Winners
            $winners = $this->generateWinners($competition, $competition->getNumberOfWinners());

            // Update Competition Status
            $competition->setStatus($targetStatus);

            // Save Winners and Competition Status
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
            $this->entityManager->clear();
        } catch (\Throwable $e) {
            // Ensure rollback on any error if the transaction is still active.
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->getConnection()->rollBack();
            }

            $this->logger->error(sprintf(
                'Error during winner generation for competition %s: %s',
                $competitionId,
                $e->getMessage()
            ), ['exception' => $e]);

            throw $e;
        }

        // Publish Notification Message to Winners
        $winnersText = [];
        foreach ($winners as $i => $winnerEmail) {
            $winnersText[] = $i . '. ' . $winnerEmail;

            $emailSubject = 'You are a Winner!';
            $emailText = 'Mother Luck has decided a gift for you from ' . $competition->getTitle()
                . " !! </br> You are the winner " . $i;
            if (!empty($winnerEmail)) {
                $this->messageProducerService->produceEmailNotificationMessage(
                    $competitionId,
                    $winnerEmail,
                    $emailSubject,
                    ['text' => $emailText]
                );
            }
        }


        // Publish Message Email Notification to Organizer
        $organizerEmail = $competition->getCreatedBy()?->getEmail();
        $emailSubject = 'Notification: Winners Announced for Competition';
        $emailText = $competition->getTitle() . ' has new Winners! <br>' . implode("<br>", $winnersText);
        if (!empty($organizerEmail)) {
            $this->messageProducerService->produceEmailNotificationMessage(
                $competitionId,
                $organizerEmail,
                $emailSubject,
                ['text' => $emailText]
            );
        }



        dump(sprintf('Winners Generated/Announced Competition: %s', $competitionId));
    }

    private function shouldGenerateWinners(int $processedSubmissionCount, Competition $competition): bool
    {
        // Validate if all Submissions are processed
        $submittedSubmissionCountKey = $this->redisKeyBuilder->getCompetitionCountKey($competition->getId());
        $submittedSubmissionCount = $this->redisManager->getValue($submittedSubmissionCountKey);
        if ($processedSubmissionCount < $submittedSubmissionCount) {
            return false;
        } elseif ($processedSubmissionCount > $submittedSubmissionCount) {
            // Heal Redis counter key
            $this->redisManager->incrementValue($submittedSubmissionCountKey, $processedSubmissionCount - $submittedSubmissionCount);
        }

        return true;
    }

    private function generateWinners(Competition $competition, int $numberOfWinners): array
    {
        // --- Reservoir Sampling Algorithm (Algorithm R) ---
        // This algorithm selects 'k' random items from a stream of 'N' items
        // where 'N' can be very large or unknown, in a single pass, using O(k) space.
        $reservoirSubmissionIds = []; // This will store the IDs of the 'k' potential winners.
        $i = 0; // Counter for the total number of submissions processed so far.

        // Use toIterable() to fetch submission IDs in batches.
        $submissionIdsIterator = $this->submissionRepository->getSubmissionIdsIterator($competition->getId());
        
        foreach ($submissionIdsIterator as $submissionIdRow) {
            $currentSubmissionId = (int) $submissionIdRow['id'];

            if ($i < $numberOfWinners) {
                // Phase 1: Fill the reservoir with the first 'k' items.
                $reservoirSubmissionIds[] = $currentSubmissionId;
            } else {
                // Phase 2: For the (i+1)-th item, decide whether to include it in the reservoir.
                // Generate a random number 'j' between 0 and 'i' (inclusive).
                // If 'j' is less than 'numberOfWinners', replace a random element in the reservoir.
                // Using mt_rand for better pseudo-randomness than rand().
                $j = mt_rand(0, $i);

                if ($j < $numberOfWinners) {
                    // Replace the element at index 'j' in the reservoir with the current submission ID.
                    $reservoirSubmissionIds[$j] = $currentSubmissionId;
                }
            }
            $i++; // Increment the count of processed submissions.
        }

        // After iterating through all submissions, $reservoirSubmissionIds contains
        // $numberOfWinners randomly selected Submission IDs.

        // Now, fetch the actual Submission entities for these selected IDs.
        // This query will be fast as it's fetching a small number (k) of records by primary key.
        /** @var Submission[] $winningSubmissions */
        $winningSubmissions = $this->submissionRepository->findBy(['id' => $reservoirSubmissionIds]);

        // dump($reservoirSubmissionIds);
        // throw new UnrecoverableMessageHandlingException('aaa');

        // We could also Sort based on Criteria
        // usort($winningSubmissions, fn($a, $b) => $a->getId() <=> $b->getId());

        // Create Winner entities and collect their emails for notification.
        $winnerEmails = [];
        $rank = 1; // Start ranking from 1.
        foreach ($winningSubmissions as $winningSubmission) {
            $winner = new Winner();
            $winner->setCompetition($competition);
            $winner->setEmail($winningSubmission->getEmail());
            $winner->setRank($rank++); // Assign rank and increment for next winner.
            $winner->setSubmission($winningSubmission);
            $this->entityManager->persist($winner); // Mark the new Winner entity for persistence.

            $winnerEmails[] = $winningSubmission->getEmail(); // Add to list for notifications.
        }

        return $winnerEmails;
    }
}
