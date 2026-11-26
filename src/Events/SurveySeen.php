<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Events;

use DanielCristea\SimpleSurveys\Models\SurveyImpression;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

final class SurveySeen
{
    use Dispatchable;

    public function __construct(
        public readonly Survey $survey,
        public readonly SurveyImpression $impression,
        public readonly Model $respondent,
    ) {}
}
