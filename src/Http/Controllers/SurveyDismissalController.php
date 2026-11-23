<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers;

use DanielCristea\SimpleSurveys\Events\SurveyDismissed;
use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveySynchronizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SurveyDismissalController
{
    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveyEligibilityResolver $eligibility,
        private readonly SurveySynchronizer $synchronizer,
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

        $existingRecord = SurveyRecord::query()->byKey($survey->key())->first();

        if ($existingRecord !== null) {
            $alreadyDismissed = SurveyDismissal::query()
                ->forSurvey($existingRecord)
                ->forRespondent($user)
                ->exists();

            if ($alreadyDismissed) {
                return $request->expectsJson()
                    ? new JsonResponse(['status' => 'dismissed', 'survey_key' => $survey->key()])
                    : back();
            }

            $alreadyCompleted = SurveyResponse::query()
                ->forSurvey($existingRecord)
                ->forRespondent($user)
                ->exists();

            if ($alreadyCompleted) {
                abort(409, 'Survey has already been completed.');
            }
        }

        if (! $this->eligibility->isEligible($survey, $user)) {
            abort(403, 'You are not eligible for this survey.');
        }

        /** @var SurveyDismissal $dismissal */
        $dismissal = DB::transaction(function () use ($survey, $user): SurveyDismissal {
            $record = $this->synchronizer->sync($survey);

            return SurveyDismissal::query()->create([
                'survey_id' => $record->getKey(),
                'respondent_type' => $user->getMorphClass(),
                'respondent_id' => $user->getKey(),
                'dismissed_at' => now(),
            ]);
        });

        SurveyDismissed::dispatch($survey, $dismissal, $user);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'status' => 'dismissed',
                'survey_key' => $survey->key(),
            ]);
        }

        return back();
    }
}
