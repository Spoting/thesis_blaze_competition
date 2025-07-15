<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;


class RabbitMqManagementService
{
    private string $baseUrl;
    private string $username;
    private string $password;

    public function __construct(
        private HttpClientInterface $httpClient,
        string $rabbitmqManagementBaseUrl,
        string $rabbitmqManagementUsername,
        string $rabbitmqManagementPassword
    ) {
        $this->baseUrl = rtrim($rabbitmqManagementBaseUrl, '/');
        $this->username = $rabbitmqManagementUsername;
        $this->password = $rabbitmqManagementPassword;
    }

    /**
     * Fetches the number of messages in a specific RabbitMQ queue using the Management API.
     * curl -v -u guest:guest http://rabbitmq:15672/api/queues/%2F/dlq_competition_submission_321
     * 
     * @param string $queueName The name of the queue (e.g., 'dlq_competition_submission_123').
     * @param string $vhost The virtual host (default is '/').
     * @return int The number of messages in the queue, or 0 if an error occurs or queue not found.
     */
    public function getQueueMessageCount(string $queueName, string $vhost = '/'): int
    {
        $encodedVhost = urlencode($vhost); // Vhost needs to be URL-encoded
        $url = sprintf('%s/api/queues/%s/%s', $this->baseUrl, $encodedVhost, $queueName);
        try {
            $response = $this->httpClient->request('GET', $url, [
                'auth_basic' => [$this->username, $this->password],
                'timeout' => 5, // Timeout for the request
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode === 200) {
                $content = $response->toArray();
                // 'messages_ready' are messages ready for delivery
                // 'messages_unacknowledged' are messages delivered but not yet acked
                // 'messages' is the sum of both
                return $content['messages'] ?? 0;
            } elseif ($statusCode === 404) {
                // Queue not found, which is expected if no failed submissions yet
                // $this->logger->info(sprintf('RabbitMQ queue "%s" not found (404). Assuming 0 messages.', $queueName));
                return 0;
            } else {
                // $this->logger->error(sprintf('Failed to fetch RabbitMQ queue "%s" count. Status: %d, Response: %s',
                //     $queueName, $statusCode, $response->getContent(false)));
                return 0;
            }
        } catch (\Throwable $e) {
            // $this->logger->error(sprintf('Exception while fetching RabbitMQ queue "%s" count: %s',
            //     $queueName, $e->getMessage()), ['exception' => $e]);
            return 0;
        }
    }
}
