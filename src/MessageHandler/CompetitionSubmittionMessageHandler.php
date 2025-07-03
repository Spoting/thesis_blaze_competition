<?php

namespace App\MessageHandler;

use App\Message\CompetitionSubmittionMessage;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;

// https://github.com/symfony/symfony/discussions/46869#discussioncomment-6164399
// https://github.com/wazum/symfony-messenger-batch/blob/main/config/packages/messenger.yaml

// UnrecoverableMessageHandlingException

#[AsMessageHandler]
class CompetitionSubmittionMessageHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    private $output;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
        $this->output = new ConsoleOutput();
    }

    public function __invoke(CompetitionSubmittionMessage $message, ?Acknowledger $ack): mixed
    {
        // $this->output->writeln('Added to batch Submission: ' . $message->getEmail());
        return $this->handle($message, $ack);
    }

    private function process(array $jobs): void
    {
        $this->output->writeln(sprintf('Attempting to bulk insert %d submissions.', count($jobs)));
        $this->logger->info(sprintf('Attempting to bulk insert %d submissions.', count($jobs)));

        // Define Columns
        $columns = [
            'competition_id',
            'email',
            'submission_data',
            'created_at',
        ];

        // Prepare placeholders for a single row's values: (?, ?, ?, ?)
        $singleRowPlaceholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $allValuePlaceholders = [];
        $parameters = [];
        $paramTypes = [];

        // Keep preinsert emails for later checking.
        $toInsertEmails = [];
        $insertedEmails = [];

        // Build each Row's Data for Raw SQL BULK Insert Query
        foreach ($jobs as [$message, $ack]) {
            $toInsertEmails[] = $message->getEmail();

            /** @var CompetitionSubmittionMessage $message */
            $allValuePlaceholders[] = $singleRowPlaceholders;

            $parameters[] = $message->getCompetitionId();
            $parameters[] = $message->getEmail();
            $parameters[] = json_encode($message->getFormData());
            $parameters[] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            // Define parameter types for each value
            $paramTypes[] = ParameterType::INTEGER;
            $paramTypes[] = ParameterType::STRING;
            $paramTypes[] = ParameterType::STRING;
            $paramTypes[] = ParameterType::STRING;
        }

        // Construct the base INSERT SQL statement
        $sql = sprintf(
            'INSERT INTO submission (%s) VALUES %s',
            implode(', ', $columns),
            implode(', ', $allValuePlaceholders)
        );

        $sql .= ' ON CONFLICT DO NOTHING RETURNING email'; //(competition_id, email)

        // ----------------------------------------------------
        $errorOccured = false;
        try {
            /** @var Connection $connection */
            $connection = $this->entityManager->getConnection();
            $connection->beginTransaction();

            $statement = $connection->prepare($sql);

            foreach ($parameters as $index => $value) {
                $statement->bindValue($index + 1, $value, $paramTypes[$index]);
            }
            $query_result = $statement->executeQuery();

            $results = $query_result->fetchAllAssociative();
            if (!empty($results)) {
                $insertedEmails = array_column($results, 'email');
            }

            $connection->commit();
            $this->logger->info(sprintf('Successfully bulk inserted %d new submissions.', count($jobs)));
            $this->output->writeln(sprintf('Successfully bulk inserted %d new submissions.', count($jobs)));
        } catch (\Exception $e) {
            // Failing here, means a more Generic Error Happened. ex, database connection error
            $connection->rollback();

            $this->logger->error(sprintf('Failed to bulk insert submissions: %s', $e->getMessage()));
            $this->output->writeln(sprintf('Failed to bulk insert submissions: %s', $e->getMessage()));

            $errorOccured = true;
        } finally {
            $this->entityManager->clear();


            // ACK all messages since Batch was Succefull
            foreach ($jobs as $i => [$message, $ack]) {
                if ($errorOccured && !empty($e)) {
                    $ack->nack($e);
                } else {
                    $ack->ack($message);
                }
            }

            if ($errorOccured && !empty($e)) {
                dump('Error occured!');
                throw $e;
            }
        }


        // Sent Corresponding Emails.
        foreach ($insertedEmails as $successEmail) {
            // Sent Success Email
            // $this->output->writeln('Success: ' . $successEmail);
        }
        $failedEmails = array_diff($toInsertEmails, $insertedEmails);
        if (!empty($failedEmails)) {
            foreach ($failedEmails as $failedEmail) {
                // Sent Failed Email
                // $this->output->writeln('Failed: ' . $failedEmail);
                // TODO: Decrease Redis Counter here.
            }
        }

        $this->output->writeln('Processed new batch of Submissions');
        $this->output->writeln(PHP_EOL);
    }


    private function getBatchSize(): int
    {
        return 50; // TODO: Convert to Enviromental
    }

    // private function shouldFlush(): bool
    // {
    //     return 12 <= \count($this->jobs);
    // }
}
