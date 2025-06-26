<?php

namespace App\Service;

use App\Entity\Competition;

class CompetitionService
{

    // TODO: 
    public function shouldAllowSumbission(Competition $competition): bool
    {
        // if ($competition->getStatus(), in_array['statuses that allow submission']

        return true;
    }


    // TODO:
    public function isStatusTransitionValid(Competition $competition, string $new_status): bool
    {
        // Check StartDate when running

        // Check EndDate when submission_ended

        // Check Winner Generation when winner_generated

        return true;
    }


    public function calculateStatusTransitionTimestamps(Competition $competition) 
    {
        // Produce running delay message
        // Produce submissions_ended delay message
        // Produce winners_generated ? ( or winnertriggered )
        // Produce archived 
    }

    //TODO: Maybe move functionality to generate LockSubmissionKey here

    //TODO: Maybe move functionality to generate CountSubmissionKey here
}
