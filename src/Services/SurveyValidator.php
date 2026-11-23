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
        $validator = Validator::make(
            $input,
            $this->rulesFor($survey),
            [],
            $this->attributesFor($survey),
        );

        /** @var array<string, mixed> $validated */
        $validated = $validator->validate();

        /** @var array<string, mixed> $rawAnswers */
        $rawAnswers = is_array($validated['answers'] ?? null) ? $validated['answers'] : [];

        return $this->normalizeAnswers($survey, $rawAnswers);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rulesFor(Survey $survey): array
    {
        $rules = [
            'answers' => ['present', 'array'],
        ];

        foreach ($survey->getQuestions() as $question) {
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
            $attributes['answers.'.$question->key()] = $question->label();
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
     * @return array<string, mixed>
     */
    private function normalizeAnswers(Survey $survey, array $rawAnswers): array
    {
        $normalized = [];

        foreach ($survey->getQuestions() as $question) {
            $key = $question->key();

            if (! array_key_exists($key, $rawAnswers) || $rawAnswers[$key] === null || $rawAnswers[$key] === '') {
                $normalized[$key] = null;

                continue;
            }

            $value = $rawAnswers[$key];

            $normalized[$key] = match ($question->type()) {
                QuestionType::Rating => is_numeric($value) ? (int) $value : null,
                QuestionType::Number => $this->normalizeNumber($value),
                QuestionType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                QuestionType::Checkbox => is_array($value) ? array_values($value) : [],
                default => is_scalar($value) ? (string) $value : null,
            };
        }

        return $normalized;
    }

    private function normalizeNumber(mixed $value): int|float|null
    {
        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return floor($float) === $float ? (int) $float : $float;
    }
}
