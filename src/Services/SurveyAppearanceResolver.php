<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\SurveyAppearance;

final class SurveyAppearanceResolver
{
    public function forSurvey(Survey $survey): SurveyAppearance
    {
        return $this->forSurveyKey($survey->key());
    }

    public function forSurveyKey(string $surveyKey): SurveyAppearance
    {
        $record = SurveyRecord::query()
            ->byKey($surveyKey)
            ->first(['id', 'key', 'appearance']);

        return $this->fromRecord($record);
    }

    public function fromRecord(?SurveyRecord $record): SurveyAppearance
    {
        if ($record === null) {
            return new SurveyAppearance;
        }

        return SurveyAppearance::fromArray($record->appearance);
    }
}
