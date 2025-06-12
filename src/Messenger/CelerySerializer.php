<?php

namespace App\Messenger;

use App\Message\AbstractMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Uid\Uuid;

class CelerySerializer implements SerializerInterface
{
    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        $messageClass = get_class($message);

        if (!method_exists($message, 'toArray')) {
            throw new \RuntimeException("Message class $messageClass must implement toArray()");
        }

        // Determine task name based on message type
        // Be sure to be in accordance to worker/app/tasks.py
        $task = match ($messageClass) {
            \App\Message\CompetitionSubmittionMessage::class =>
            $message->isPremium()
                ? 'app.tasks.process_premium_submission'
                : 'app.tasks.process_normal_submission',

            \App\Message\WinnerTriggerMessage::class =>
            'app.tasks.trigger_winner_generation',

            default =>
            throw new \RuntimeException("No Celery task mapped for message: $messageClass"),
        };

        $body = [
            'id' => Uuid::v4()->toRfc4122(),
            'task' => $task,
            'args' => [json_encode($message->toArray())],
            'kwargs' => new \stdClass(),
            // 'retries' => 0,
            // 'eta' => null,
        ];

        return [
            'body' => json_encode($body),
            'headers' => [], // You can omit or keep empty
            'content_type' => 'application/json',
            'content_encoding' => 'utf-8',
        ];
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        throw new \LogicException('CelerySerializer only supports sending.');
    }
}
