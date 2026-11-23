<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Tests\Support;

use DanielCristea\SimpleSurveys\Contracts\SurveyDefinition;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;

final class SampleSurveyDefinition implements SurveyDefinition
{
    public function definition(): Survey
    {
        return Survey::make('class-defined-survey')
            ->title('Class Defined Survey')
            ->priority(10)
            ->questions([
                Question::text('name', 'What is your name?')->required(),
            ]);
    }
}
