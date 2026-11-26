<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers\Admin;

use DanielCristea\SimpleSurveys\Services\SurveyLifecycleManager;
use Illuminate\Http\RedirectResponse;

final class SurveyResetController
{
    public function __construct(
        private readonly SurveyLifecycleManager $lifecycle,
    ) {}

    public function __invoke(string $surveyKey): RedirectResponse
    {
        $title = $this->lifecycle->titleFor($surveyKey);

        if ($title === null || ! $this->lifecycle->reset($surveyKey)) {
            abort(404, 'Survey not found.');
        }

        return back()->with(
            'simple_surveys_admin_status',
            sprintf('All responses, dismissals, opens, and impressions for "%s" have been reset.', $title),
        );
    }
}
