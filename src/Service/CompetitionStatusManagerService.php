<?php

namespace App\Service;

use App\Entity\Competition;

class CompetitionStatusManagerService
{
    /**
     * Checks if the current status is a "lower level" (earlier in progression) than the new status.
     * 'Cancelled' status is handled as a special case and is not considered in the linear progression for this check.
     */
    public function isCurrentStatusLowerThanNew(string $currentStatus, string $newStatus): bool
    {
        // Cancelled status is a terminal state and doesn't fit into the linear "lower/higher" progression easily.
        // If either status is 'cancelled', we treat it as not strictly "lower" in the progression sense.
        if ($currentStatus === 'cancelled' || $newStatus === 'cancelled') {
            return false;
        }

        $currentLevel = array_flip(array_keys(Competition::STATUSES))[$currentStatus] ?? null;
        $newLevel = array_flip(array_keys(Competition::STATUSES))[$newStatus] ?? null;

        if ($currentLevel === null || $newLevel === null) {
            // One of the statuses is not in the defined order (e.g., 'cancelled' or an unknown status)
            // or there's an issue with the definition.
            // You might want to log this as a warning or throw an exception.
            return false;
        }

        return $currentLevel < $newLevel;
    }

    /**
     * Checks if the current status is "equal or higher level" (later in progression or same level) than the new status.
     * 'Cancelled' status is handled as a special case and is not considered in the linear progression for this check.
     */
    public function isCurrentStatusEqualOrHigherThanNew(string $currentStatus, string $newStatus): bool
    {
        // Cancelled status is a terminal state and doesn't fit into the linear "lower/higher" progression easily.
        // If either status is 'cancelled', we treat it as not strictly "higher" or "equal" in the progression sense,
        // unless you explicitly define its position relative to others. For this method, we'll assume it breaks the linear comparison.
        if ($currentStatus === 'cancelled' || $newStatus === 'cancelled') {
            return false;
        }

        $currentLevel = array_flip(array_keys(Competition::STATUSES))[$currentStatus] ?? null;
        $newLevel = array_flip(array_keys(Competition::STATUSES))[$newStatus] ?? null;

        if ($currentLevel === null || $newLevel === null) {
            // One of the statuses is not in the defined order (e.g., 'cancelled' or an unknown status)
            // or there's an issue with the definition.
            // You might want to log this as a warning or throw an exception.
            return false;
        }

        return $currentLevel >= $newLevel;
    }


    public function isStatusTransitionValid(string $currentStatus, string $newStatus): bool
    {
        if (!array_key_exists($newStatus, Competition::STATUSES)) {
            // $this->logger->warning(sprintf('Invalid new status "%s" requested for competition %d.', $new_status, $competition->getId()));
            return false;
        }

        // Define valid transitions from each status (strict linear progression)
        $validTransitions = [
            'draft' => ['scheduled', 'cancelled'],
            'scheduled' => ['running', 'cancelled'],
            'running' => ['submissions_ended', 'cancelled'],
            'submissions_ended' => ['winners_announced'],
            'winners_announced' => ['archived'],
            'archived' => [],
            'cancelled' => [],
        ];

        // Check if the requested transition is allowed from the current status
        if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus])) {
            // $this->logger->warning(sprintf(
            //     'Invalid status transition for competition %d: from "%s" to "%s".',
            //     $competition->getId(), $currentStatus, $new_status
            // ));
            return false;
        }

        return true; // If all checks pass, the transition is valid
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
