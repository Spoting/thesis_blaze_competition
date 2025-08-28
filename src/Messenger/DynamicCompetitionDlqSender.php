<?php

namespace App\Messenger;

use App\Message\CompetitionSubmittionMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Serializer\SerializerInterface;

class DynamicCompetitionDlqSender
{
    private AMQPStreamConnection $connection;
    private \PhpAmqpLib\Channel\AMQPChannel $channel;
    private SerializerInterface $serializer;

    private array $declaredQueues = [];

    public function __construct(
        string $dsn, // Injected by services.yaml
        SerializerInterface $serializer
    ) {
        $this->serializer = $serializer;

        $this->connection = $this->createConnectionFromDsn($dsn);
        $this->channel = $this->connection->channel();
    }

    public function send(string $queueName, object $message, string $errorMessage): void
    {
        if (!($message instanceof CompetitionSubmittionMessage)) {
            return;
        }

        // Attempt for each Worker to make less Declare Queue Calls.
        if (!isset($this->declaredQueues[$queueName])) {
            $this->channel->queue_declare(
                $queueName,
                false,  // passive
                true,   // durable
                false,  // exclusive
                false   // auto-delete
            );
            $this->declaredQueues[$queueName] = true;
        }

        $payload = $this->serializer->serialize($message, 'json');
        // $payload = json_decode($payload);
        // $payload['error_message'] = $errorMessage;
        // $payload = json_encode($payload);
        $amqpMessage = new AMQPMessage($payload . "_$errorMessage", ['delivery_mode' => 2]);

        $this->channel->basic_publish($amqpMessage, '', $queueName);
    }

    public function __destruct()
    {
        $this->channel?->close();
        $this->connection?->close();
    }

    private function createConnectionFromDsn(string $dsn): AMQPStreamConnection
    {
        $parts = parse_url($dsn);

        $user = $parts['user'] ?? 'guest';
        $pass = $parts['pass'] ?? 'guest';
        $host = $parts['host'] ?? 'rabbitmq';
        $port = $parts['port'] ?? 5672;

        return new AMQPStreamConnection($host, $port, $user, $pass);
    }
}
