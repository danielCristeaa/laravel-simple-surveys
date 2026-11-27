<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Survey;

final class SurveyStatusResolver
{
    public function resolve(Survey $survey, ?SurveyRecord $record, ?CarbonInterface $now = null): SurveyStatus
    {
        if ($survey->isDraft()) {
            return SurveyStatus::Draft;
        }

        if ($survey->isClosed()) {
            return SurveyStatus::Closed;
        }

        if ($record === null) {
            return $survey->status($now);
        }

        if ($record->status === SurveyStatus::Draft || $record->status === SurveyStatus::Closed) {
            return $record->status;
        }

        return $this->resolveForWindow($record->starts_at, $record->ends_at, $now);
    }

    public function isActive(Survey $survey, ?SurveyRecord $record, ?CarbonInterface $now = null): bool
    {
        return $this->resolve($survey, $record, $now) === SurveyStatus::Active;
    }

    public function resolveForWindow(
        ?CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        ?CarbonInterface $now = null,
    ): SurveyStatus {
        $reference = $now !== null ? CarbonImmutable::instance($now) : CarbonImmutable::now();

        if ($endsAt !== null && $reference->greaterThan($endsAt)) {
            return SurveyStatus::Closed;
        }

        if ($startsAt !== null && $reference->lessThan($startsAt)) {
            return SurveyStatus::Scheduled;
        }

        return SurveyStatus::Active;
    }

    public function effectiveStartsAt(Survey $survey, ?SurveyRecord $record): ?CarbonInterface
    {
        return $record !== null ? $record->starts_at : $survey->getStartsAt();
    }

    public function effectiveEndsAt(Survey $survey, ?SurveyRecord $record): ?CarbonInterface
    {
        return $record !== null ? $record->ends_at : $survey->getEndsAt();
    }
}
