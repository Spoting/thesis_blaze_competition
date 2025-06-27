<?php

namespace App\MessageHandler;

use App\Entity\Competition;
use App\Message\WinnerTriggerMessage;
use App\Service\CompetitionService;
use App\Service\MessageProducerService;
use App\Service\RedisKeyBuilder;
use App\Service\RedisManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class WinnerTriggerMessageHandler
{
    // Inject the EntityManagerInterface via the constructor
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private MessageProducerService $messageProducerService,
        private CompetitionService $competitionService,
        private RedisManager $redisManager,
        private RedisKeyBuilder $redisKeyBuilder,
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

        /** @var Competition */
        $competition = $this->entityManager->getRepository(Competition::class)->find($competitionId);


        // Validate Status Change
        $isStatusValid = $this->competitionService->isStatusTransitionValid($competition, $targetStatus);
        if (!$isStatusValid) {
            // throw exception. dont attempt retry
        }


        // Validate if all Submissions are processed
        $submittedSubmissionCount = (int) $this->redisKeyBuilder->getCompetitionCountKey($competitionId);
        $processedSubmissionCount = (int) $submittedSubmissionCount; // TODO: Actual Query
        if ($processedSubmissionCount < $submittedSubmissionCount) {
            // Do not awk message and retry later.
        }

        if (!$this->shouldGenerateWinners($competition)) {
            // Dont attempt to generate/announce winners
            return;
        }

        // Generate Winners
        $winners = $this->generateWinners($processedSubmissionCount, $competition->getNumberOfWinners());

        // Update Competition Status
        $competition->setStatus($targetStatus);

        // Save Winners and Competition Status
        $this->entityManager->flush();



        // Publish Notification Message to Winners
        $winnersText = [];
        foreach ($winners as $i => $winnerEmail) {
            $winnersText[] = $i . '. ' . $winnerEmail;

            $emailSubject = 'You are a Winner!';
            $emailText = 'Mother Luck has decided a gift for you from ' . $competition->getTitle() 
            . " !! </br> You are the winner ". $i;
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

    private function shouldGenerateWinners(Competition $competition): bool
    {

        // TODO: Query and check if Winners already exists for this Competition.

        return true;
    }

    private function generateWinners(int $processedSubmissionCount, int $numberOfWinners): array
    {
        // TODO: randomize N numbers from the $processedSubmissionCount.

        // TODO: Then query info | emails from Submission.

        // TODO: Create Winners Entity

        $winners = [];
        for ($i = 1; $i <= $numberOfWinners; $i++) {
            $winners[$i] = $i . '@winner.com';
        }

        return $winners;
    }

    // private function announceWinners() {}
}
