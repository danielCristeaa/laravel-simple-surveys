<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Contracts;

use DanielCristea\SimpleSurveys\Survey;

interface SurveyDefinition
{
    public function definition(): Survey;
}
