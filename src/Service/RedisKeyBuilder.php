<?php
namespace App\Service;

class RedisKeyBuilder
{
    public const REDIS_PREFIX_COMPETITION_KEY = 'competition:%s:';

    public const COMPETITION_COUNT_KEY = self::REDIS_PREFIX_COMPETITION_KEY . 'submissions:count';
    public const COMPETITION_SUBMISSION_KEY = self::REDIS_PREFIX_COMPETITION_KEY . 'submission:%s';

    public const VERIFICATION_TOKEN_KEY = 'verification_token:%s';
    public const VERIFICATION_PENDING_VALUE = 'pending_verification';
    public const VERIFICATION_TOKEN_TTL_SECONDS = 120;

    public function getCompetitionCountKey(int $competitionId): string
    {
        return sprintf(self::COMPETITION_COUNT_KEY, $competitionId);
    }

    public function getCompetitionSubmissionKey(int $competitionId, string $email): string
    {
        $hash = md5($competitionId . '-' . $email);

        return sprintf(self::COMPETITION_SUBMISSION_KEY, $competitionId, $hash);
    }

    public function getVerificationTokenKey(string $verificationToken): string
    {
        return sprintf(self::VERIFICATION_TOKEN_KEY, $verificationToken);
    }
}