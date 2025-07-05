<?php

namespace App\Service;

use App\Entity\Competition;

class CompetitionStatusManagerService
{
    // TODO:
    public function isStatusTransitionValid(Competition $competition, string $new_status): bool
    {
        // Check StartDate when running

        // Check EndDate when submission_ended

        // Check Winner Generation when winner_generated

        return true;
    }


    /** Returns Scheduling Delays in Milliseconds  */
    public function calculateStatusTransitionDelays(
        Competition $competition,
        int $winnerGracePeriod = 30,     // 30 seconds
        int $archiveAfter = 259200       // 3 days in seconds
    ): array {

        $now = new \DateTimeImmutable('now');
        $now = $now->getTimestamp();

        $start = $competition->getStartDate()->getTimestamp();
        $end = $competition->getEndDate()->getTimestamp();

        $runningDelay = $start - $now;
        $submissionsEndedDelay = $end - $now;
        $winnersGeneratedDelay = ($end + $winnerGracePeriod) - $now;
        $archivedDelay = ($end + $archiveAfter) - $now;

        return [
            'running' => (int) $runningDelay * 1000,
            'submissions_ended' => (int) $submissionsEndedDelay * 1000,
            'winners_announced' => (int) $winnersGeneratedDelay * 1000,
            'archived' => (int) $archivedDelay * 1000,
        ];
    }

}
