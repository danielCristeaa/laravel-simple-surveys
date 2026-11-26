<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers;

use DanielCristea\SimpleSurveys\Events\SurveySeen;
use DanielCristea\SimpleSurveys\Models\SurveyImpression;
use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveySynchronizer;
use DanielCristea\SimpleSurveys\Services\SurveyTrackingContextResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SurveyImpressionController
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

        if (! config('simple-surveys.tracking.impressions', true)) {
            if ($request->expectsJson()) {
                return new JsonResponse([
                    'status' => 'disabled',
                    'survey_key' => $survey->key(),
                ]);
            }

            return back();
        }

        /** @var SurveyImpression|null $createdImpression */
        $createdImpression = DB::transaction(function () use ($survey, $user): ?SurveyImpression {
            $record = $this->synchronizer->sync($survey);
            $now = now();

            $inserted = SurveyImpression::query()->insertOrIgnore([
                'survey_id' => $record->getKey(),
                'respondent_type' => $user->getMorphClass(),
                'respondent_id' => $user->getKey(),
                'first_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === 0) {
                return null;
            }

            return SurveyImpression::query()
                ->forSurvey($record)
                ->forRespondent($user)
                ->first();
        });

        if ($createdImpression !== null) {
            $context = array_filter([
                'url' => $this->trackingContext->resolveUrl($request),
            ], static fn (mixed $value): bool => $value !== null);

            $tracking = $this->trackingContext->resolveTracking($request, $user, $survey);

            SurveySeen::dispatch($survey, $createdImpression, $user, $context, $tracking);
        }

        if ($request->expectsJson()) {
            return new JsonResponse([
                'status' => 'seen',
                'survey_key' => $survey->key(),
            ]);
        }

        return back();
    }
}
