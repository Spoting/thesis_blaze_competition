<?php

namespace App\MessageHandler;

use App\Message\CompetitionSubmittionMessage;
use App\Service\MessageProducerService;
use App\Service\RedisKeyBuilder;
use App\Service\RedisManager;
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


// Bulk Insert...

// Type of Submissions:
// 1. Success
// 2. Failed Constraint.
// - ConstraintViolation
// 3. Retryable Error Errored. Allow Retries.
// - ConnectionException
// 4. Some other Exceptions that are not Expected but will only Affect a row.
// - ForeignKeyConstraintViolationException


// Scenario 1:  
// All inserted Succefully.

// Scenario 2:
// Bulk Insert some Successfully. Silent catch Constraints 



// Remember, that Connections are still Connections. We need to have less Workers as possible, meaning we need to optimize our Operation so we can have fewer Workers

#[AsMessageHandler]
class CompetitionSubmittionMessageHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    private $output;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private MessageProducerService $messageProducer,
        private RedisManager $redisManager,
        private RedisKeyBuilder $redisKeyBuilder,
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
        // $this->logger->info(sprintf('Attempting to bulk insert %d submissions.', count($jobs)));
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

        $emailCompetitionIdMapping = [];
        foreach ($jobs as [$message, $ack]) {
            // Build email-competitionId mapping for later use.
            $emailCompetitionIdMapping[$message->getEmail()] = $message->getCompetitionId();
            
            // Build each Row's Data for Raw SQL BULK Insert Query
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

        // Construct the base bulk INSERT SQL statement
        $sql = sprintf(
            'INSERT INTO submission (%s) VALUES %s',
            implode(', ', $columns),
            implode(', ', $allValuePlaceholders)
        );


        // Silent Error for unique(competition_id, email) and null Constraints
        $sql .= ' ON CONFLICT DO NOTHING RETURNING email'; 
        // The whole batch could fail because -> Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException
        // Which should not happen. Nevertheless, we still need to handle it accordingly.


        // ----------------------------------------------------
        $errorOccured = false;
        $insertedEmails = [];
        try {
            /** @var Connection $connection */
            $connection = $this->entityManager->getConnection();
            $connection->beginTransaction();
            // throw new Exception('aaa');

            $statement = $connection->prepare($sql);
            // throw new Exception('aaa');
            foreach ($parameters as $index => $value) {
                $statement->bindValue($index + 1, $value, $paramTypes[$index]);
            }
            $query_result = $statement->executeQuery();

            $results = $query_result->fetchAllAssociative();
            if (!empty($results)) {
                $insertedEmails = array_column($results, 'email');
            }
            $connection->commit();
            // $this->logger->info(sprintf('Successfully bulk inserted %d new submissions.', count($jobs)));
            $this->output->writeln(sprintf('Successfully bulk inserted %d new submissions.', count($jobs)));
        } catch (\Doctrine\DBAL\Exception\ConnectionException $ce) {
            // $this->output->writeln(sprintf('Connection Failed %s . %s', $ce->getMessage(), get_class($ce)));
            // Do not attempt to Rollback on Connection Error.
            $errorOccured = true;
            $e = $ce;
        } catch (\Throwable $e) {
            // $this->output->writeln(sprintf('Failed %s . %s', $e->getMessage(), get_class($e)));
            // Rollback Changes
            $connection->rollback();

            $errorOccured = true;
        } finally {
            // ACK all messages since Batch was Succefull
            foreach ($jobs as $i => [$message, $ack]) {
                if ($errorOccured) {
                    $ack->nack($e);
                } else {
                    $ack->ack($message);
                }
            }

            if ($errorOccured && !empty($e)) {
                // dump('Error occured!');
                $this->output->getErrorOutput()->writeln('An Error Occured in batch : ' . $e->getMessage() . " | " . get_class($e));
                $this->logger->error('An Error Occured in batch : ' . $e->getMessage() . " | " . get_class($e));
                // throw $e;
                return;
            }
        }


        // Sent Corresponding Emails.
        foreach ($insertedEmails as $successEmail) {
            // Sent Success Email
            $emailSubject = 'Submissions Accepted';
            $emailText = 'Your Submission is Accepted for Competition: ' . $emailCompetitionIdMapping[$successEmail];
            $this->messageProducer->produceEmailNotificationMessage(
                $emailCompetitionIdMapping[$successEmail],
                $successEmail,
                $emailSubject,
                ['text' => $emailText]
            );
        }

        $attemptedEmails = array_keys($emailCompetitionIdMapping);
        $failedEmails = array_diff($attemptedEmails, $insertedEmails);
        if (!empty($failedEmails)) {
            foreach ($failedEmails as $failedEmail) {
                // Sent Failed Email
                $emailSubject = 'Problem with Submission';
                $emailText = 'Seems you have already Submitted. Your Submission Failed for Competition: ' . $emailCompetitionIdMapping[$failedEmail];
                $this->messageProducer->produceEmailNotificationMessage(
                    $emailCompetitionIdMapping[$failedEmail],
                    $failedEmail,
                    $emailSubject,
                    ['text' => $emailText]
                );
                // Decrement the Total Count for this Competition
                $count_key = $this->redisKeyBuilder->getCompetitionCountKey($emailCompetitionIdMapping[$failedEmail]);
                $this->redisManager->decrementValue($count_key);
            }
        }

        $this->output->writeln('Processed new batch of Submissions 50');
        $this->output->writeln(PHP_EOL);
    }


    private function getBatchSize(): int
    {
        return 50; // TODO: Convert to Enviromental
    }
}
