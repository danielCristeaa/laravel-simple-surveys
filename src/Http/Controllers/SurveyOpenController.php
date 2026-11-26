<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers;

use DanielCristea\SimpleSurveys\Events\SurveyOpened;
use DanielCristea\SimpleSurveys\Models\SurveyOpen;
use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveySynchronizer;
use DanielCristea\SimpleSurveys\Services\SurveyTrackingContextResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SurveyOpenController
{
    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveyEligibilityResolver $eligibility,
        private readonly SurveySynchronizer $synchronizer,
        private readonly SurveyTrackingContextResolver $trackingContext,
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

        if (! $this->eligibility->isEligible($survey, $user)) {
            abort(403, 'You are not eligible for this survey.');
        }

        /** @var SurveyOpen $open */
        $open = DB::transaction(function () use ($survey, $user): SurveyOpen {
            $record = $this->synchronizer->sync($survey);
            $now = now();

            $existing = SurveyOpen::query()
                ->forSurvey($record)
                ->forRespondent($user)
                ->first();

            if ($existing !== null) {
                $existing->update([
                    'open_count' => $existing->open_count + 1,
                    'last_opened_at' => $now,
                ]);

                return $existing;
            }

            return SurveyOpen::query()->create([
                'survey_id' => $record->getKey(),
                'respondent_type' => $user->getMorphClass(),
                'respondent_id' => $user->getKey(),
                'open_count' => 1,
                'first_opened_at' => $now,
                'last_opened_at' => $now,
            ]);
        });

        $context = array_filter([
            'url' => $this->trackingContext->resolveUrl($request),
        ], static fn (mixed $value): bool => $value !== null);

        $tracking = $this->trackingContext->resolveTracking($request, $user, $survey);

        SurveyOpened::dispatch($survey, $open, $user, $context, $tracking);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'status' => 'opened',
                'survey_key' => $survey->key(),
            ]);
        }

        return back();
    }
}
