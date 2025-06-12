<?php

namespace App\Constants;

class CompetitionConstants
{
    public const REDIS_PREFIX_COUNT_SUBMITTIONS = 'count_competition_submittions_';
    public const REDIS_PREFIX_SUBMISSION_KEY = 'submission_key_';


    public const AMPQ_ROUTING = [
        'normal_submission' => 'normal_submission',
        'premium_submission' => 'premium_submission',
        'winner_trigger' => 'winner_trigger',
    ];
    // TODO: Adds roles >.< they are already declared as Service Params
}