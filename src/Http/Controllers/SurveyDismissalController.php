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
use DanielCristea\SimpleSurveys\Services\SurveyTrackingContextResolver;
use DanielCristea\SimpleSurveys\Services\SurveyTranslator;
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

        if (! $survey->isDismissible()) {
            abort(403, $this->translator->ui('errors.not_dismissible'));
        }

        $existingRecord = SurveyRecord::query()->byKey($survey->key())->first();
        $existingDismissal = null;

        if ($existingRecord !== null) {
            $existingDismissal = SurveyDismissal::query()
                ->forSurvey($existingRecord)
                ->forRespondent($user)
                ->first();

            if ($existingDismissal !== null && ! $survey->isDismissalExpired($existingDismissal->dismissed_at)) {
                return $request->expectsJson()
                    ? new JsonResponse(['status' => 'dismissed', 'survey_key' => $survey->key()])
                    : back();
            }

            $alreadyCompleted = SurveyResponse::query()
                ->forSurvey($existingRecord)
                ->forRespondent($user)
                ->exists();

            if ($alreadyCompleted) {
                abort(409, $this->translator->ui('errors.already_completed'));
            }
        }

        if (! $this->eligibility->isEligible($survey, $user)) {
            abort(403, $this->translator->ui('errors.not_eligible'));
        }

        $source = $this->trackingContext->resolveDismissalSource($request);

        /** @var SurveyDismissal $dismissal */
        $dismissal = DB::transaction(function () use ($survey, $user, $existingDismissal, $source): SurveyDismissal {
            $record = $this->synchronizer->sync($survey);

            if ($existingDismissal !== null) {
                $existingDismissal->update([
                    'dismissed_at' => now(),
                    'source' => $source,
                    'dismiss_count' => $existingDismissal->dismiss_count + 1,
                ]);

                return $existingDismissal;
            }

            return SurveyDismissal::query()->create([
                'survey_id' => $record->getKey(),
                'respondent_type' => $user->getMorphClass(),
                'respondent_id' => $user->getKey(),
                'dismissed_at' => now(),
                'source' => $source,
                'dismiss_count' => 1,
            ]);
        });

        $context = array_filter([
            'url' => $this->trackingContext->resolveUrl($request),
            'source' => $source,
        ], static fn (mixed $value): bool => $value !== null);

        $tracking = $this->trackingContext->resolveTracking($request, $user, $survey);

        SurveyDismissed::dispatch($survey, $dismissal, $user, $context, $tracking);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'status' => 'dismissed',
                'survey_key' => $survey->key(),
            ]);
        }

        return back();
    }
}
