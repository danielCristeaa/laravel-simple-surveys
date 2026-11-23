<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers;

use DanielCristea\SimpleSurveys\Enums\ResponseStatus;
use DanielCristea\SimpleSurveys\Events\SurveyDismissed;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SurveyDismissalController
{
    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveyEligibilityResolver $eligibility,
    ) {}

    public function __invoke(Request $request, string $surveyKey): JsonResponse|RedirectResponse
    {
        $guard = config('simple-surveys.guard');
        $user = $request->user(is_string($guard) ? $guard : null);

        if (! $user instanceof Model) {
            abort(401, 'Authentication required.');
        }

        $survey = $this->registry->find($surveyKey);

        if ($survey === null) {
            abort(404, 'Survey not found.');
        }

        if (! $survey->isDismissible()) {
            abort(403, 'This survey cannot be dismissed.');
        }

        $existing = SurveyResponse::query()
            ->forSurvey($survey->key())
            ->forRespondent($user)
            ->first();

        if ($existing !== null) {
            if ($existing->isDismissed()) {
                return $request->expectsJson()
                    ? new JsonResponse(['status' => 'dismissed', 'survey_key' => $survey->key()])
                    : back();
            }

            abort(409, 'Survey has already been completed.');
        }

        if (! $this->eligibility->isEligible($survey, $user)) {
            abort(403, 'You are not eligible for this survey.');
        }

        $response = SurveyResponse::query()->create([
            'survey_key' => $survey->key(),
            'respondent_type' => $user->getMorphClass(),
            'respondent_id' => $user->getKey(),
            'status' => ResponseStatus::Dismissed,
            'answers' => null,
            'metadata' => null,
            'completed_at' => null,
            'dismissed_at' => now(),
        ]);

        SurveyDismissed::dispatch($survey, $response, $user);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'status' => 'dismissed',
                'survey_key' => $survey->key(),
            ]);
        }

        return back();
    }
}
