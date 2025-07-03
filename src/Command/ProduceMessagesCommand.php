<?php

namespace App\Command;

use App\Message\CompetitionSubmittionMessage;
use App\Service\MessageProducerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:produce-messages',
    description: 'Dispatches a specified number of messages to RabbitMQ, based on the declared scenario.',
)]
class ProduceMessagesCommand extends Command
{
    private MessageBusInterface $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        parent::__construct();
        $this->messageBus = $messageBus;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('count', InputArgument::REQUIRED, 'Number of messages to dispatch')
            ->addArgument('target_scenario', InputArgument::OPTIONAL, 'Scenario that will be used to determine the produced messages. For example, we want to dispatch different priority messages', 'high')
            ->addArgument('send_rate', InputArgument::OPTIONAL, 'Messages per second to attempt to send (0 for no rate limit)', 0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $input->getArgument('count');
        $targetScenario = $input->getArgument('target_scenario');
        $sendRate = (int) $input->getArgument('send_rate');

        $io->title(sprintf('Starting message production: %d messages, strategy: %s', $count, $targetScenario));

        $messagesSent = 0;
        $startTime = microtime(true);
        $lastPrintTime = $startTime;
        $printInterval = 5; // Print progress every 5 seconds


        // Initialize $lastMessageSendTime *before* the loop
        $lastMessageSendTime = $startTime; // Set it to the start time initially

        // --- Initialize the ProgressBar ---
        $progressBar = new ProgressBar($output, $count);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% %message%');
        $progressBar->start();
        $progressBar->setMessage('Initializing...');
        // --- End ProgressBar Init ---


        for ($i = 0; $i < $count; $i++) {
            $currentLoopTime = microtime(true);
            $elapsedTime = $currentLoopTime - $startTime;

            if ($sendRate > 0) {
                $expectedMessagesSent = floor($elapsedTime * $sendRate);
                if ($messagesSent > $expectedMessagesSent) {
                    $sleepTime = ( ( $messagesSent + 1 ) / $sendRate ) - $elapsedTime;
                    if ($sleepTime > 0) {
                        usleep((int) ($sleepTime * 1_000_000));
                    }
                }
            }


            $message_attributes = [
                'content_type' => 'application/json',
                'content_encoding' => 'utf-8',
            ];
            if ($targetScenario === 'high') {
                $priorityKey = rand(1, 5);
                $queue = MessageProducerService::AMPQ_ROUTING['high_priority_submission'];
                $message_attributes['priority'] = $priorityKey;
            } elseif ($targetScenario === 'low') {
                $priorityKey = 0;
                $queue = MessageProducerService::AMPQ_ROUTING['low_priority_submission'];
            } else { // both
                $priorityKey = rand(0, 5);
                if ($priorityKey == 0) {
                    $queue = MessageProducerService::AMPQ_ROUTING['low_priority_submission'];
                } else {
                    $queue = MessageProducerService::AMPQ_ROUTING['high_priority_submission'];
                    $message_attributes['priority'] = $priorityKey;
                }
            }


            $message = new CompetitionSubmittionMessage(
                ['email' => 'kati@kati.com', 'priority' => $i . "|" . $priorityKey],
                321,
                'kati' . $i . '@kati.com' . $i
            );


            $this->messageBus->dispatch(
                $message,
                [new AmqpStamp(
                    $queue,
                    attributes: $message_attributes
                )]
            );

            $messagesSent++;
            $lastMessageSendTime = microtime(true);

            // --- Update the ProgressBar ---
            $progressBar->advance();
            if ($currentLoopTime - $lastPrintTime >= $printInterval || $i === $count - 1) {
                $currentRate = $messagesSent / (microtime(true) - $startTime);
                $progressBar->setMessage(sprintf('Rate: %.2f msg/s', $currentRate));
                $lastPrintTime = $currentLoopTime;
            }
            // --- End ProgressBar Update ---
        }
        
        // --- Finish the ProgressBar ---
        $progressBar->finish();
        // --- End ProgressBar Finish ---


        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        $messagesPerSecond = $messagesSent / $duration;

        $io->newLine(2); // Add some space after progress bar
        $io->success(sprintf(
            'Finished: Dispatched %d messages in %.2f seconds (%.2f msg/s).',
            $messagesSent,
            $duration,
            $messagesPerSecond
        ));

        return Command::SUCCESS;
    }
}
