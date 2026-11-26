<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers\Admin;

use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final class SurveyResetController
{
    public function __construct(
        private readonly SurveyRegistry $registry,
    ) {}

    public function __invoke(string $surveyKey): RedirectResponse
    {
        $record = SurveyRecord::query()->byKey($surveyKey)->first();
        $registered = $this->registry->find($surveyKey);

        if ($record === null && $registered === null) {
            abort(404, 'Survey not found.');
        }

        $title = $registered !== null ? $registered->getTitle() : $record->title;

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

        return back()->with(
            'simple_surveys_admin_status',
            sprintf('All responses, dismissals, opens, and impressions for "%s" have been reset.', $title),
        );
    }
}
