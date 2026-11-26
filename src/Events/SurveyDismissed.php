<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Events;

use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

final class SurveyDismissed
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $tracking
     */
    public function __construct(
        public readonly Survey $survey,
        public readonly SurveyDismissal $dismissal,
        public readonly Model $respondent,
        public readonly array $context = [],
        public readonly array $tracking = [],
    ) {}
}
