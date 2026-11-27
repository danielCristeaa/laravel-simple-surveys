<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers\Admin;

use DanielCristea\SimpleSurveys\Enums\WidgetIconPreset;
use DanielCristea\SimpleSurveys\Enums\WidgetPosition;
use DanielCristea\SimpleSurveys\Enums\WidgetTheme;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Services\SurveyAppearanceResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveyResultsAggregator;
use DanielCristea\SimpleSurveys\Services\SurveyStatusResolver;
use DanielCristea\SimpleSurveys\Services\SurveySynchronizer;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\SurveyAppearance;
use DanielCristea\SimpleSurveys\WidgetIcon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SurveyAppearanceController
{
    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveySynchronizer $synchronizer,
        private readonly SurveyAppearanceResolver $appearanceResolver,
        private readonly SurveyResultsAggregator $aggregator,
        private readonly SurveyStatusResolver $statusResolver,
    ) {}

    public function edit(string $surveyKey): View
    {
        $this->ensureEditorEnabled();

        $survey = $this->findRegisteredSurvey($surveyKey);
        $record = SurveyRecord::query()->byKey($surveyKey)->withCount('responses')->first();
        $appearance = $this->appearanceResolver->fromRecord($record);
        $status = $this->statusResolver->resolve($survey, $record);
        $responsesCount = $record !== null ? (int) $record->getAttribute('responses_count') : 0;

        /** @var view-string $view */
        $view = 'simple-surveys::admin.appearance';

        return view($view, [
            'survey' => $survey,
            'status' => $status,
            'priority' => $survey->getPriority(),
            'questionsCount' => count($survey->getQuestions()),
            'responsesCount' => $responsesCount,
            'surveyOptions' => $this->aggregator->surveyOptions(),
            'appearance' => $appearance,
            'positions' => WidgetPosition::cases(),
            'themes' => WidgetTheme::cases(),
            'iconPresets' => WidgetIconPreset::cases(),
            'inheritedIcon' => $survey->getIcon() ?? WidgetIcon::fromConfig(),
            'assetBaseUrl' => rtrim(asset(''), '/'),
            'tokens' => SurveyAppearance::STYLE_TOKENS,
        ]);
    }

    public function update(Request $request, string $surveyKey): RedirectResponse
    {
        $this->ensureEditorEnabled();

        $survey = $this->findRegisteredSurvey($surveyKey);

        /** @var array<string, mixed> $validated */
        $validated = $request->validate(SurveyAppearance::validationRules(), [
            'icon.regex' => 'The banner icon must be a built-in preset, "none", or a relative public/ path ending in .png or .svg.',
        ]);
        $appearance = SurveyAppearance::fromArray($validated);

        $record = SurveyRecord::query()->byKey($surveyKey)->first()
            ?? $this->synchronizer->sync($survey);

        $record->update([
            'appearance' => $appearance->toStorageArray(),
        ]);

        return redirect()
            ->route('simple-surveys.admin.appearance.edit', $surveyKey)
            ->with('simple_surveys_admin_status', sprintf('Appearance settings saved for "%s".', $survey->getTitle()));
    }

    public function destroy(string $surveyKey): RedirectResponse
    {
        $this->ensureEditorEnabled();

        $survey = $this->findRegisteredSurvey($surveyKey);
        $record = SurveyRecord::query()->byKey($surveyKey)->first();

        if ($record !== null) {
            $record->update(['appearance' => null]);
        }

        return redirect()
            ->route('simple-surveys.admin.appearance.edit', $surveyKey)
            ->with('simple_surveys_admin_status', sprintf('Appearance reset to defaults for "%s".', $survey->getTitle()));
    }

    private function ensureEditorEnabled(): void
    {
        if (! config('simple-surveys.admin.appearance_editor', true)) {
            abort(404);
        }
    }

    private function findRegisteredSurvey(string $surveyKey): Survey
    {
        $survey = $this->registry->find($surveyKey);

        if ($survey === null) {
            abort(404, 'Survey not found.');
        }

        return $survey;
    }
}
