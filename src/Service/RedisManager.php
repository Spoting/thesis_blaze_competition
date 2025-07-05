<?php

namespace App\Service;

use Redis; // For phpredis

class RedisManager
{
    /** @disregard P1009 */
    private Redis $redisClient;

    /** @disregard P1009 */
    public function __construct(
        \Redis $redisClient
    ) {
        $this->redisClient = $redisClient;
    }

    public function incrementValue(string $key, int $amount = 1): int
    {
        return $this->redisClient->incrBy($key, $amount);
    }

    public function decrementValue(string $key, int $amount = 1): int
    {
        return $this->redisClient->decrBy($key, $amount);
    }

    public function getValue(string $key): ?string
    {
        return $this->redisClient->get($key);
    }

    public function setValue(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl > 0) {
            return $this->redisClient->setex($key, $ttl, $value);
        }
        return $this->redisClient->set($key, $value);
    }

    public function deleteKey(string $key): int
    {
        return $this->redisClient->del($key);
    }

    public function addToList(string $listKey, string $value): int
    {
        return $this->redisClient->rpush($listKey, $value);
    }

    public function getList(string $listKey): array
    {
        return $this->redisClient->lrange($listKey, 0, -1);
    }

    public function trimList(string $listKey, int $start, int $end): bool
    {
        return $this->redisClient->ltrim($listKey, $start, $end);
    }
}
