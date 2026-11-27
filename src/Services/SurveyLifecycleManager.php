<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Support\Facades\DB;

final class SurveyLifecycleManager
{
    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveySynchronizer $synchronizer,
    ) {}

    public function close(Survey|string $survey): ?SurveyRecord
    {
        $record = $this->resolveOrSyncRecord($this->resolveKey($survey));

        if ($record === null) {
            return null;
        }

        $record->update([
            'status' => SurveyStatus::Closed,
            'ends_at' => now(),
        ]);

        return $record;
    }

    public function reopen(Survey|string $survey): ?SurveyRecord
    {
        $record = $this->resolveOrSyncRecord($this->resolveKey($survey));

        if ($record === null) {
            return null;
        }

        $endsAt = $record->ends_at;

        if ($endsAt !== null && $endsAt->lessThanOrEqualTo(now())) {
            $endsAt = null;
        }

        $record->update([
            'status' => SurveyStatus::Active,
            'ends_at' => $endsAt,
        ]);

        return $record;
    }

    public function reset(Survey|string $survey): bool
    {
        $surveyKey = $this->resolveKey($survey);
        $record = SurveyRecord::query()->byKey($surveyKey)->first();
        $registered = $this->registry->find($surveyKey);

        if ($record === null && $registered === null) {
            return false;
        }

        if ($record !== null) {
            DB::transaction(static function () use ($record): void {
                $responseIds = $record->responses()->pluck('id');

                if ($responseIds->isNotEmpty()) {
                    SurveyAnswer::query()->whereIn('response_id', $responseIds)->delete();
                }

                $record->responses()->delete();
                $record->dismissals()->delete();
                $record->opens()->delete();
                $record->impressions()->delete();
            });
        }

        return true;
    }

    public function titleFor(Survey|string $survey): ?string
    {
        $surveyKey = $this->resolveKey($survey);
        $registered = $this->registry->find($surveyKey);

        if ($registered !== null) {
            return $registered->getTitle();
        }

        return SurveyRecord::query()->byKey($surveyKey)->first()?->title;
    }

    private function resolveOrSyncRecord(string $surveyKey): ?SurveyRecord
    {
        $record = SurveyRecord::query()->byKey($surveyKey)->first();

        if ($record !== null) {
            return $record;
        }

        $registered = $this->registry->find($surveyKey);

        if ($registered === null) {
            return null;
        }

        return $this->synchronizer->sync($registered);
    }

    private function resolveKey(Survey|string $survey): string
    {
        return $survey instanceof Survey ? $survey->key() : $survey;
    }
}
