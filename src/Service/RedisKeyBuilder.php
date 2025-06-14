<?php
namespace App\Service;

class RedisKeyBuilder
{
    public const REDIS_PREFIX_COMPETITION_KEY = 'competition:%s:';

    public const COMPETITION_COUNT_KEY = self::REDIS_PREFIX_COMPETITION_KEY . 'submissions:count';
    public const COMPETITION_SUBMISSION_KEY = self::REDIS_PREFIX_COMPETITION_KEY . 'submission:%s';


    public function __construct()
    {
        // TODO: Convert Consts to Enviromentals so Celery can actually use them and to convert .env variables as common topology
    }

    public function getCompetitionCountKey(int $competitionId): string
    {
        return sprintf(self::COMPETITION_COUNT_KEY, $competitionId);
    }

    public function getCompetitionSubmissionKey(int $competitionId, string $email, string $phoneNumber): string
    {
        $hash = md5($competitionId . '-' . $email . '-' . $phoneNumber);

        return sprintf(self::COMPETITION_SUBMISSION_KEY, $competitionId, $hash);
    }
}