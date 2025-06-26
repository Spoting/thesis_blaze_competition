<?php

namespace App\Service;

class AnnouncementService
{
    private RedisManager $redisManager;
    private string $redisKey = RedisKeyBuilder::GLOBAL_ANNOUNCEMENT_KEY;
    private int $maxAnnouncements = 12; 

    public function __construct(RedisManager $redisManager)
    {
        $this->redisManager = $redisManager;
    }

    public function addAnnouncement(string $status, string $message): void
    {
        $this->redisManager->addToList(
            $this->redisKey,
            json_encode([
                'status' => $status,
                'message' => $message,
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
            ])
        );

        $this->redisManager->trimList($this->redisKey, -$this->maxAnnouncements, -1);
    }

    public function getAnnouncements(): array
    {
        $rawAnnouncements = $this->redisManager->getList($this->redisKey);
        $announcements = [];
        foreach ($rawAnnouncements as $rawAnnouncement) {
            $announcements[] = json_decode($rawAnnouncement, true);
        }

        return array_reverse($announcements);
    }

    public function clearAnnouncements(): void
    {
        $this->redisManager->deleteKey($this->redisKey);
    }
}