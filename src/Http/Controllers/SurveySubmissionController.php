<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers;

use DanielCristea\SimpleSurveys\Enums\ResponseStatus;
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveyValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SurveySubmissionController
{
    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveyEligibilityResolver $eligibility,
        private readonly SurveyValidator $validator,
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

        if (! $survey->isActive()) {
            abort(403, 'Survey is not active.');
        }

        $alreadyResponded = SurveyResponse::query()
            ->forSurvey($survey->key())
            ->forRespondent($user)
            ->exists();

        if ($alreadyResponded) {
            abort(409, 'Survey has already been submitted or dismissed.');
        }

        if (! $this->eligibility->isEligible($survey, $user)) {
            abort(403, 'You are not eligible for this survey.');
        }

        $answers = $this->validator->validate($survey, $request->all());

        $url = $request->input('url');
        $referer = $request->headers->get('referer');

        $metadata = array_filter([
            'url' => is_string($url) && $url !== '' ? $url : (is_string($referer) ? $referer : null),
        ], static fn (mixed $value): bool => $value !== null);

        $response = SurveyResponse::query()->create([
            'survey_key' => $survey->key(),
            'respondent_type' => $user->getMorphClass(),
            'respondent_id' => $user->getKey(),
            'status' => ResponseStatus::Completed,
            'answers' => $answers,
            'metadata' => $metadata !== [] ? $metadata : null,
            'completed_at' => now(),
            'dismissed_at' => null,
        ]);

        SurveySubmitted::dispatch($survey, $response, $user);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => $survey->getThankYouMessage(),
                'survey_key' => $survey->key(),
            ], 201);
        }

        return back()->with('simple_surveys_status', $survey->getThankYouMessage());
    }
}
