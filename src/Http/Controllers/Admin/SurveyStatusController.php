<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers\Admin;

use DanielCristea\SimpleSurveys\Services\SurveyLifecycleManager;
use Illuminate\Http\RedirectResponse;

final class SurveyStatusController
{
    public function __construct(
        private readonly SurveyLifecycleManager $lifecycle,
    ) {}

    public function close(string $surveyKey): RedirectResponse
    {
        $record = $this->lifecycle->close($surveyKey);

        if ($record === null) {
            abort(404, 'Survey not found.');
        }

        return back()->with('simple_surveys_admin_status', sprintf('Survey "%s" has been ended.', $record->title));
    }

    public function reopen(string $surveyKey): RedirectResponse
    {
        $record = $this->lifecycle->reopen($surveyKey);

        if ($record === null) {
            abort(404, 'Survey not found.');
        }

        return back()->with('simple_surveys_admin_status', sprintf('Survey "%s" has been re-opened.', $record->title));
    }
}
