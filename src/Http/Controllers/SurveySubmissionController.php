<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers;

use DanielCristea\SimpleSurveys\Enums\QuestionType;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Models\SurveyQuestion;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveySynchronizer;
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

        $existingRecord = SurveyRecord::query()->byKey($survey->key())->first();

        $isActive = $existingRecord !== null
            ? $existingRecord->status === SurveyStatus::Active
            : $survey->isActive();

        if (! $isActive) {
            abort(403, 'Survey is not active.');
        }

        if ($existingRecord !== null) {
            $alreadyInteracted = SurveyResponse::query()
                ->forSurvey($existingRecord)
                ->forRespondent($user)
                ->exists()
                || SurveyDismissal::query()
                    ->forSurvey($existingRecord)
                    ->forRespondent($user)
                    ->exists();

            if ($alreadyInteracted) {
                abort(409, 'Survey has already been submitted or dismissed.');
            }
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

        /** @var SurveyResponse $response */
        $response = DB::transaction(function () use ($survey, $user, $answers, $metadata): SurveyResponse {
            $record = $this->synchronizer->sync($survey);

            $response = SurveyResponse::query()->create([
                'survey_id' => $record->getKey(),
                'respondent_type' => $user->getMorphClass(),
                'respondent_id' => $user->getKey(),
                'metadata' => $metadata !== [] ? $metadata : null,
                'completed_at' => now(),
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

        SurveySubmitted::dispatch($survey, $response, $user);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'message' => $survey->getThankYouMessage(),
                'survey_key' => $survey->key(),
            ], 201);
        }

        return back()->with('simple_surveys_status', $survey->getThankYouMessage());
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
