<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers;

use DanielCristea\SimpleSurveys\Enums\QuestionType;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Models\SurveyOpen;
use DanielCristea\SimpleSurveys\Models\SurveyQuestion;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveySynchronizer;
use DanielCristea\SimpleSurveys\Services\SurveyTrackingContextResolver;
use DanielCristea\SimpleSurveys\Services\SurveyTranslator;
use DanielCristea\SimpleSurveys\Services\SurveyValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SurveySubmissionController
{
    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveyEligibilityResolver $eligibility,
        private readonly SurveyValidator $validator,
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

        $existingRecord = SurveyRecord::query()->byKey($survey->key())->first();

        $isActive = $existingRecord !== null
            ? $existingRecord->status === SurveyStatus::Active
            : $survey->isActive();

        if (! $isActive) {
            abort(403, $this->translator->ui('errors.not_active'));
        }

        $existingDismissal = null;

        if ($existingRecord !== null) {
            $alreadyCompleted = SurveyResponse::query()
                ->forSurvey($existingRecord)
                ->forRespondent($user)
                ->exists();

            if ($alreadyCompleted) {
                abort(409, $this->translator->ui('errors.already_submitted'));
            }

            $existingDismissal = SurveyDismissal::query()
                ->forSurvey($existingRecord)
                ->forRespondent($user)
                ->first();

            if ($existingDismissal !== null && ! $survey->isDismissalExpired($existingDismissal->dismissed_at)) {
                abort(409, $this->translator->ui('errors.already_dismissed'));
            }
        }

        if (! $this->eligibility->isEligible($survey, $user)) {
            abort(403, $this->translator->ui('errors.not_eligible'));
        }

        $answers = $this->validator->validate($survey, $request->all());

        $fallbackOpenedAt = null;

        if ($request->input('duration_ms') === null && $existingRecord !== null) {
            $fallbackOpenedAt = SurveyOpen::query()
                ->forSurvey($existingRecord)
                ->forRespondent($user)
                ->first(['first_opened_at'])
                ?->first_opened_at;
        }

        $rawLocale = app()->getLocale();
        $locale = trim($rawLocale) !== '' ? trim($rawLocale) : null;

        $context = array_filter([
            'url' => $this->trackingContext->resolveUrl($request),
            'duration_ms' => $this->trackingContext->resolveDurationMs($request, $fallbackOpenedAt),
            'locale' => $locale,
        ], static fn (mixed $value): bool => $value !== null);

        $tracking = $this->trackingContext->resolveTracking($request, $user, $survey);

        $metadata = $context;
        if ($tracking !== []) {
            $metadata['tracking'] = $tracking;
        }

        /** @var SurveyResponse $response */
        $response = DB::transaction(function () use ($survey, $user, $answers, $metadata, $existingDismissal): SurveyResponse {
            $record = $this->synchronizer->sync($survey);

            if ($existingDismissal !== null) {
                $existingDismissal->delete();
            }

            $response = SurveyResponse::query()->create([
                'survey_id' => $record->getKey(),
                'respondent_type' => $user->getMorphClass(),
                'respondent_id' => $user->getKey(),
                'metadata' => $metadata !== [] ? $metadata : null,
            ]);

            /** @var array<string, SurveyQuestion> $questionsByKey */
            $questionsByKey = [];
            foreach ($record->questions as $questionModel) {
                $questionsByKey[$questionModel->key] = $questionModel;
            }

            foreach ($answers as $questionKey => $value) {
                $questionModel = $questionsByKey[$questionKey] ?? null;

                if ($questionModel === null) {
                    continue;
                }

                SurveyAnswer::query()->create([
                    'response_id' => $response->getKey(),
                    'question_id' => $questionModel->getKey(),
                    ...$this->encodeAnswerValue($questionModel->type, $value),
                ]);
            }

            return $response->load(['survey', 'answers.question']);
        });

        SurveySubmitted::dispatch($survey, $response, $user, $context, $tracking);

        $thankYouMessage = $this->translator->thankYouMessage($survey);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => $thankYouMessage,
                'survey_key' => $survey->key(),
            ], 201);
        }

        return back()->with('simple_surveys_status', $thankYouMessage);
    }

    /**
     * @return array{value: string|null, value_numeric: float|null, value_json: array<int, string>|null}
     */
    private function encodeAnswerValue(QuestionType $type, mixed $value): array
    {
        if ($value === null) {
            return [
                'value' => null,
                'value_numeric' => null,
                'value_json' => null,
            ];
        }

        if ($type === QuestionType::Checkbox && is_array($value)) {
            /** @var array<int, string> $items */
            $items = array_values(array_map(
                static fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
                $value,
            ));

            return [
                'value' => null,
                'value_numeric' => null,
                'value_json' => $items,
            ];
        }

        if ($type === QuestionType::Boolean && is_bool($value)) {
            return [
                'value' => $value ? '1' : '0',
                'value_numeric' => $value ? 1.0 : 0.0,
                'value_json' => null,
            ];
        }

        if ($type->isNumeric() && is_numeric($value)) {
            return [
                'value' => (string) $value,
                'value_numeric' => (float) $value,
                'value_json' => null,
            ];
        }

        return [
            'value' => is_scalar($value) ? (string) $value : null,
            'value_numeric' => null,
            'value_json' => null,
        ];
    }
}
