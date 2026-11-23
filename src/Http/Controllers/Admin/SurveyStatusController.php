<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers\Admin;

use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveySynchronizer;
use Illuminate\Http\RedirectResponse;

final class SurveyStatusController
{
    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveySynchronizer $synchronizer,
    ) {}

    public function close(string $surveyKey): RedirectResponse
    {
        $record = $this->resolveRecord($surveyKey);

        $record->update([
            'status' => SurveyStatus::Closed,
            'ends_at' => now(),
        ]);

        return back()->with('simple_surveys_admin_status', sprintf('Survey "%s" has been ended.', $record->title));
    }

    public function reopen(string $surveyKey): RedirectResponse
    {
        $record = $this->resolveRecord($surveyKey);

        $endsAt = $record->ends_at;

        if ($endsAt !== null && $endsAt->isPast()) {
            $endsAt = null;
        }

        $record->update([
            'status' => SurveyStatus::Active,
            'ends_at' => $endsAt,
        ]);

        return back()->with('simple_surveys_admin_status', sprintf('Survey "%s" has been re-opened.', $record->title));
    }

    private function resolveRecord(string $surveyKey): SurveyRecord
    {
        $record = SurveyRecord::query()->byKey($surveyKey)->first();

        if ($record !== null) {
            return $record;
        }

        $registered = $this->registry->find($surveyKey);

        if ($registered === null) {
            abort(404, 'Survey not found.');
        }

        return $this->synchronizer->sync($registered);
    }
}
