<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use DanielCristea\SimpleSurveys\Enums\QuestionType;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SurveyValidator
{
    public function __construct(
        private readonly SurveyTranslator $translator,
    ) {}

    /**
     * Validate request payload against the survey's questions and return normalized answers.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(Survey $survey, array $input): array
    {
        /** @var array<string, mixed> $rawInputAnswers */
        $rawInputAnswers = is_array($input['answers'] ?? null) ? $input['answers'] : [];
        $visibility = $this->resolveVisibility($survey, $rawInputAnswers);

        $validator = Validator::make(
            $input,
            $this->rulesForVisibility($survey, $visibility),
            [],
            $this->attributesFor($survey),
        );

        /** @var array<string, mixed> $validated */
        $validated = $validator->validate();

        /** @var array<string, mixed> $validatedAnswers */
        $validatedAnswers = is_array($validated['answers'] ?? null) ? $validated['answers'] : [];

        return $this->normalizeAnswers($survey, $validatedAnswers, $visibility);
    }

    /**
     * @param  array<string, mixed>|null  $rawAnswers
     * @return array<string, array<int, mixed>>
     */
    public function rulesFor(Survey $survey, ?array $rawAnswers = null): array
    {
        $visibility = $rawAnswers !== null
            ? $this->resolveVisibility($survey, $rawAnswers)
            : null;

        return $this->rulesForVisibility($survey, $visibility);
    }

    /**
     * Determine which questions are visible given a raw answers map.
     *
     * @param  array<string, mixed>  $rawAnswers
     * @return array<string, bool>
     */
    public function resolveVisibility(Survey $survey, array $rawAnswers): array
    {
        $preNormalized = $this->normalizeAnswers($survey, $rawAnswers);

        /** @var array<string, bool> $visibility */
        $visibility = [];

        /** @var array<string, true> $visiting */
        $visiting = [];

        foreach ($survey->getQuestions() as $question) {
            $this->isQuestionVisible($survey, $question, $preNormalized, $visibility, $visiting);
        }

        return $visibility;
    }

    /**
     * @param  array<string, bool>|null  $visibility
     * @return array<string, array<int, mixed>>
     */
    private function rulesForVisibility(Survey $survey, ?array $visibility): array
    {
        $rules = [
            'answers' => ['present', 'array'],
        ];

        foreach ($survey->getQuestions() as $question) {
            if ($visibility !== null && ($visibility[$question->key()] ?? true) === false) {
                continue;
            }

            $field = 'answers.'.$question->key();
            $rules[$field] = $this->rulesForQuestion($question);

            if ($question->type() === QuestionType::Checkbox && $question->getOptions() !== []) {
                $rules[$field.'.*'] = [
                    'string',
                    Rule::in(array_keys($question->getOptions())),
                ];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributesFor(Survey $survey): array
    {
        $attributes = [];

        foreach ($survey->getQuestions() as $question) {
            $attributes['answers.'.$question->key()] = $this->translator->questionLabel($survey, $question);
        }

        return $attributes;
    }

    /**
     * @return array<int, mixed>
     */
    private function rulesForQuestion(Question $question): array
    {
        $rules = [
            $question->isRequired() ? 'required' : 'nullable',
        ];

        switch ($question->type()) {
            case QuestionType::Text:
            case QuestionType::Textarea:
                $rules[] = 'string';

                if ($question->getMaxLength() !== null) {
                    $rules[] = 'max:'.$question->getMaxLength();
                }
                break;

            case QuestionType::Number:
                $rules[] = 'numeric';

                if ($question->getMin() !== null) {
                    $rules[] = 'min:'.$question->getMin();
                }

                if ($question->getMax() !== null) {
                    $rules[] = 'max:'.$question->getMax();
                }
                break;

            case QuestionType::Rating:
                $rules[] = 'integer';

                if ($question->getMin() !== null) {
                    $rules[] = 'min:'.$question->getMin();
                }

                if ($question->getMax() !== null) {
                    $rules[] = 'max:'.$question->getMax();
                }
                break;

            case QuestionType::Select:
            case QuestionType::Radio:
                $rules[] = 'string';

                if ($question->getOptions() !== []) {
                    $rules[] = Rule::in(array_keys($question->getOptions()));
                }
                break;

            case QuestionType::Checkbox:
                $rules[] = 'array';
                break;

            case QuestionType::Boolean:
                $rules[] = 'boolean';
                break;
        }

        return [...$rules, ...$question->getRules()];
    }

    /**
     * @param  array<string, mixed>  $rawAnswers
     * @param  array<string, bool>|null  $visibility
     * @return array<string, mixed>
     */
    private function normalizeAnswers(Survey $survey, array $rawAnswers, ?array $visibility = null): array
    {
        $normalized = [];

        foreach ($survey->getQuestions() as $question) {
            $key = $question->key();

            if ($visibility !== null && ($visibility[$key] ?? true) === false) {
                $normalized[$key] = null;

                continue;
            }

            if (! array_key_exists($key, $rawAnswers) || $rawAnswers[$key] === null || $rawAnswers[$key] === '') {
                $normalized[$key] = null;

                continue;
            }

            $value = $rawAnswers[$key];

            $normalized[$key] = match ($question->type()) {
                QuestionType::Rating => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : null,
                QuestionType::Number => $this->normalizeNumber($value),
                QuestionType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                QuestionType::Checkbox => is_array($value) ? array_values($value) : [],
                default => is_scalar($value) ? (string) $value : null,
            };
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $normalizedAnswers
     * @param  array<string, bool>  $visibility
     * @param  array<string, true>  $visiting
     */
    private function isQuestionVisible(
        Survey $survey,
        Question $question,
        array $normalizedAnswers,
        array &$visibility,
        array &$visiting,
    ): bool {
        $key = $question->key();

        if (array_key_exists($key, $visibility)) {
            return $visibility[$key];
        }

        if (! $question->isConditional()) {
            $visibility[$key] = true;

            return true;
        }

        if (isset($visiting[$key])) {
            return false;
        }

        $visiting[$key] = true;
        $visible = true;

        foreach ($question->getConditions() as $condition) {
            $target = $survey->question($condition->questionKey);

            if ($target === null) {
                $visible = false;
                break;
            }

            if (! $this->isQuestionVisible($survey, $target, $normalizedAnswers, $visibility, $visiting)) {
                $visible = false;
                break;
            }

            if (! $condition->matches($normalizedAnswers[$target->key()] ?? null)) {
                $visible = false;
                break;
            }
        }

        unset($visiting[$key]);
        $visibility[$key] = $visible;

        return $visible;
    }

    private function normalizeNumber(mixed $value): int|float|null
    {
        if (is_bool($value) || ! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return floor($float) === $float ? (int) $float : $float;
    }
}
