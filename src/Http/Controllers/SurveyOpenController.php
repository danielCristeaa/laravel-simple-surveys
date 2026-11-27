<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers;

use DanielCristea\SimpleSurveys\Events\SurveyOpened;
use DanielCristea\SimpleSurveys\Models\SurveyOpen;
use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveySynchronizer;
use DanielCristea\SimpleSurveys\Services\SurveyTrackingContextResolver;
use DanielCristea\SimpleSurveys\Services\SurveyTranslator;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
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
        private readonly SurveyTranslator $translator,
    ) {}

    public function __invoke(Request $request, string $surveyKey): JsonResponse|RedirectResponse
    {
        $guard = config('simple-surveys.guard');
        $user = $request->user(is_string($guard) ? $guard : null);

        if (! $user instanceof Model) {
            abort(401, $this->translator->ui('errors.unauthenticated'));
        }

        $survey = $this->registry->find($surveyKey);

        if ($survey === null) {
            abort(404, $this->translator->ui('errors.not_found'));
        }

        if (! $this->eligibility->isEligible($survey, $user)) {
            abort(403, $this->translator->ui('errors.not_eligible'));
        }

        $open = $this->persistOpen($survey, $user);

        if ($open !== null) {
            $context = array_filter([
                'url' => $this->trackingContext->resolveUrl($request),
            ], static fn (mixed $value): bool => $value !== null);

            $tracking = $this->trackingContext->resolveTracking($request, $user, $survey);

            SurveyOpened::dispatch($survey, $open, $user, $context, $tracking);
        }

        if ($request->expectsJson()) {
            return new JsonResponse([
                'status' => 'opened',
                'survey_key' => $survey->key(),
            ]);
        }

        return back();
    }

    private function persistOpen(Survey $survey, Model $user): ?SurveyOpen
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($survey, $user): SurveyOpen {
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
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        return null;
    }
}
