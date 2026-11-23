<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use DanielCristea\SimpleSurveys\Enums\QuestionType;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyQuestion;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class SurveyResultsAggregator
{
    public function __construct(
        private readonly SurveyRegistry $registry,
    ) {}

    /**
     * @return Collection<int, array{
     *     key: string,
     *     title: string,
     *     description: string|null,
     *     status: SurveyStatus,
     *     registered: bool,
     *     questions_count: int,
     *     responses_count: int,
     *     dismissals_count: int,
     *     completion_rate: float|null
     * }>
     */
    public function summaries(): Collection
    {
        $records = SurveyRecord::query()
            ->withCount(['questions', 'responses', 'dismissals'])
            ->orderByDesc('priority')
            ->orderBy('title')
            ->get()
            ->keyBy('key');

        $registeredSurveys = $this->registry->all();

        /** @var array<int, array{
         *     key: string,
         *     title: string,
         *     description: string|null,
         *     status: SurveyStatus,
         *     registered: bool,
         *     questions_count: int,
         *     responses_count: int,
         *     dismissals_count: int,
         *     completion_rate: float|null
         * }> $summaries */
        $summaries = [];

        foreach ($registeredSurveys as $survey) {
            /** @var SurveyRecord|null $record */
            $record = $records->get($survey->key());
            $responsesCount = $record !== null ? (int) $record->getAttribute('responses_count') : 0;
            $dismissalsCount = $record !== null ? (int) $record->getAttribute('dismissals_count') : 0;

            $summaries[] = [
                'key' => $survey->key(),
                'title' => $survey->getTitle(),
                'description' => $survey->getDescription(),
                'status' => $survey->status(),
                'registered' => true,
                'questions_count' => count($survey->getQuestions()),
                'responses_count' => $responsesCount,
                'dismissals_count' => $dismissalsCount,
                'completion_rate' => $this->completionRate($responsesCount, $dismissalsCount),
            ];
        }

        // Include historical/archived DB surveys that are no longer registered in code
        foreach ($records as $key => $record) {
            if ($registeredSurveys->has($key)) {
                continue;
            }

            $responsesCount = (int) $record->getAttribute('responses_count');
            $dismissalsCount = (int) $record->getAttribute('dismissals_count');

            $summaries[] = [
                'key' => $record->key,
                'title' => $record->title,
                'description' => $record->description,
                'status' => SurveyStatus::Closed,
                'registered' => false,
                'questions_count' => (int) $record->getAttribute('questions_count'),
                'responses_count' => $responsesCount,
                'dismissals_count' => $dismissalsCount,
                'completion_rate' => $this->completionRate($responsesCount, $dismissalsCount),
            ];
        }

        return new Collection($summaries);
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string|null,
     *     status: SurveyStatus,
     *     registered: bool,
     *     responses_count: int,
     *     dismissals_count: int,
     *     completion_rate: float|null,
     *     questions: array<int, array<string, mixed>>,
     *     responses: LengthAwarePaginator<int, SurveyResponse>|null
     * }|null
     */
    public function forSurvey(string $surveyKey, int $perPage = 15): ?array
    {
        $record = SurveyRecord::query()
            ->byKey($surveyKey)
            ->with('questions')
            ->withCount(['responses', 'dismissals'])
            ->first();

        $registered = $this->registry->find($surveyKey);

        if ($record === null && $registered === null) {
            return null;
        }

        if ($record === null) {
            $emptyQuestions = [];

            foreach ($registered->getQuestions() as $question) {
                $emptyQuestions[] = [
                    'key' => $question->key(),
                    'label' => $question->label(),
                    'type' => $question->type(),
                    'answered_count' => 0,
                    'breakdown' => [],
                    'stats' => null,
                    'recent_answers' => [],
                ];
            }

            return [
                'key' => $registered->key(),
                'title' => $registered->getTitle(),
                'description' => $registered->getDescription(),
                'status' => $registered->status(),
                'registered' => true,
                'responses_count' => 0,
                'dismissals_count' => 0,
                'completion_rate' => null,
                'questions' => $emptyQuestions,
                'responses' => null,
            ];
        }

        /** @var SurveyRecord $record */
        $responsesCount = (int) $record->getAttribute('responses_count');
        $dismissalsCount = (int) $record->getAttribute('dismissals_count');

        $questionsData = [];

        foreach ($record->questions as $question) {
            $questionsData[] = $this->aggregateQuestion($question);
        }

        $responses = SurveyResponse::query()
            ->forSurvey($record)
            ->with(['answers.question'])
            ->latest('completed_at')
            ->paginate($perPage);

        return [
            'key' => $record->key,
            'title' => $registered !== null ? $registered->getTitle() : $record->title,
            'description' => $registered !== null ? $registered->getDescription() : $record->description,
            'status' => $registered !== null ? $registered->status() : SurveyStatus::Closed,
            'registered' => $registered !== null,
            'responses_count' => $responsesCount,
            'dismissals_count' => $dismissalsCount,
            'completion_rate' => $this->completionRate($responsesCount, $dismissalsCount),
            'questions' => $questionsData,
            'responses' => $responses,
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     type: QuestionType,
     *     answered_count: int,
     *     breakdown: array<int, array{label: string, value: string, count: int, percentage: float}>,
     *     stats: array{avg: float|null, min: float|null, max: float|null}|null,
     *     recent_answers: array<int, array{value: string, submitted_at: string|null}>
     * }
     */
    private function aggregateQuestion(SurveyQuestion $question): array
    {
        return match ($question->type) {
            QuestionType::Number, QuestionType::Rating => $this->aggregateNumericQuestion($question),
            QuestionType::Select, QuestionType::Radio => $this->aggregateChoiceQuestion($question),
            QuestionType::Boolean => $this->aggregateBooleanQuestion($question),
            QuestionType::Checkbox => $this->aggregateCheckboxQuestion($question),
            QuestionType::Text, QuestionType::Textarea => $this->aggregateTextQuestion($question),
        };
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     type: QuestionType,
     *     answered_count: int,
     *     breakdown: array<int, array{label: string, value: string, count: int, percentage: float}>,
     *     stats: array{avg: float|null, min: float|null, max: float|null},
     *     recent_answers: array<int, array{value: string, submitted_at: string|null}>
     * }
     */
    private function aggregateNumericQuestion(SurveyQuestion $question): array
    {
        $row = SurveyAnswer::query()
            ->where('question_id', $question->getKey())
            ->whereNotNull('value_numeric')
            ->selectRaw('COUNT(*) as answered_count, AVG(value_numeric) as avg_val, MIN(value_numeric) as min_val, MAX(value_numeric) as max_val')
            ->first();

        $answeredCount = $row !== null ? (int) $row->getAttribute('answered_count') : 0;
        $avgRaw = $row?->getAttribute('avg_val');
        $minRaw = $row?->getAttribute('min_val');
        $maxRaw = $row?->getAttribute('max_val');

        $breakdown = [];

        if ($question->type === QuestionType::Rating) {
            /** @var array<int|string, int> $counts */
            $counts = SurveyAnswer::query()
                ->where('question_id', $question->getKey())
                ->whereNotNull('value')
                ->selectRaw('value, COUNT(*) as aggregate_count')
                ->groupBy('value')
                ->pluck('aggregate_count', 'value')
                ->map(static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0)
                ->all();

            $constraints = $question->constraints ?? [];
            $minScale = is_numeric($constraints['min'] ?? null) ? (int) $constraints['min'] : 1;
            $maxScale = is_numeric($constraints['max'] ?? null) ? (int) $constraints['max'] : 5;

            for ($score = $minScale; $score <= $maxScale; $score++) {
                $scoreKey = (string) $score;
                $count = $counts[$score] ?? $counts[$scoreKey] ?? 0;
                $breakdown[] = [
                    'label' => $scoreKey,
                    'value' => $scoreKey,
                    'count' => $count,
                    'percentage' => $answeredCount > 0 ? round(($count / $answeredCount) * 100, 1) : 0.0,
                ];
            }
        }

        return [
            'key' => $question->key,
            'label' => $question->label,
            'type' => $question->type,
            'answered_count' => $answeredCount,
            'breakdown' => $breakdown,
            'stats' => [
                'avg' => is_numeric($avgRaw) ? round((float) $avgRaw, 2) : null,
                'min' => is_numeric($minRaw) ? (float) $minRaw : null,
                'max' => is_numeric($maxRaw) ? (float) $maxRaw : null,
            ],
            'recent_answers' => [],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     type: QuestionType,
     *     answered_count: int,
     *     breakdown: array<int, array{label: string, value: string, count: int, percentage: float}>,
     *     stats: null,
     *     recent_answers: array<int, array{value: string, submitted_at: string|null}>
     * }
     */
    private function aggregateChoiceQuestion(SurveyQuestion $question): array
    {
        /** @var array<string, int> $counts */
        $counts = SurveyAnswer::query()
            ->where('question_id', $question->getKey())
            ->whereNotNull('value')
            ->selectRaw('value, COUNT(*) as aggregate_count')
            ->groupBy('value')
            ->pluck('aggregate_count', 'value')
            ->map(static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0)
            ->all();

        $answeredCount = array_sum($counts);
        $options = $question->options ?? [];
        $breakdown = [];

        foreach ($options as $optionValue => $optionLabel) {
            $count = $counts[$optionValue] ?? 0;
            $breakdown[] = [
                'label' => $optionLabel,
                'value' => $optionValue,
                'count' => $count,
                'percentage' => $answeredCount > 0 ? round(($count / $answeredCount) * 100, 1) : 0.0,
            ];
            unset($counts[$optionValue]);
        }

        // Preserve historical option values if an option was later removed from code
        foreach ($counts as $value => $count) {
            $breakdown[] = [
                'label' => $value,
                'value' => $value,
                'count' => $count,
                'percentage' => $answeredCount > 0 ? round(($count / $answeredCount) * 100, 1) : 0.0,
            ];
        }

        return [
            'key' => $question->key,
            'label' => $question->label,
            'type' => $question->type,
            'answered_count' => $answeredCount,
            'breakdown' => $breakdown,
            'stats' => null,
            'recent_answers' => [],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     type: QuestionType,
     *     answered_count: int,
     *     breakdown: array<int, array{label: string, value: string, count: int, percentage: float}>,
     *     stats: null,
     *     recent_answers: array<int, array{value: string, submitted_at: string|null}>
     * }
     */
    private function aggregateBooleanQuestion(SurveyQuestion $question): array
    {
        /** @var array<int|string, int> $counts */
        $counts = SurveyAnswer::query()
            ->where('question_id', $question->getKey())
            ->whereNotNull('value')
            ->selectRaw('value, COUNT(*) as aggregate_count')
            ->groupBy('value')
            ->pluck('aggregate_count', 'value')
            ->map(static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0)
            ->all();

        $yesCount = $counts[1] ?? $counts['1'] ?? 0;
        $noCount = $counts[0] ?? $counts['0'] ?? 0;
        $answeredCount = $yesCount + $noCount;

        return [
            'key' => $question->key,
            'label' => $question->label,
            'type' => $question->type,
            'answered_count' => $answeredCount,
            'breakdown' => [
                [
                    'label' => 'Yes',
                    'value' => '1',
                    'count' => $yesCount,
                    'percentage' => $answeredCount > 0 ? round(($yesCount / $answeredCount) * 100, 1) : 0.0,
                ],
                [
                    'label' => 'No',
                    'value' => '0',
                    'count' => $noCount,
                    'percentage' => $answeredCount > 0 ? round(($noCount / $answeredCount) * 100, 1) : 0.0,
                ],
            ],
            'stats' => null,
            'recent_answers' => [],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     type: QuestionType,
     *     answered_count: int,
     *     breakdown: array<int, array{label: string, value: string, count: int, percentage: float}>,
     *     stats: null,
     *     recent_answers: array<int, array{value: string, submitted_at: string|null}>
     * }
     */
    private function aggregateCheckboxQuestion(SurveyQuestion $question): array
    {
        $answeredCount = 0;
        /** @var array<string, int> $counts */
        $counts = [];

        SurveyAnswer::query()
            ->where('question_id', $question->getKey())
            ->whereNotNull('value_json')
            ->select(['id', 'value_json'])
            ->chunkById(200, function (Collection $answers) use (&$answeredCount, &$counts): void {
                foreach ($answers as $answer) {
                    $selected = $answer->value_json;

                    if (! is_array($selected) || $selected === []) {
                        continue;
                    }

                    $answeredCount++;

                    foreach ($selected as $optionValue) {
                        $counts[$optionValue] = ($counts[$optionValue] ?? 0) + 1;
                    }
                }
            });

        $options = $question->options ?? [];
        $breakdown = [];

        foreach ($options as $optionValue => $optionLabel) {
            $count = $counts[$optionValue] ?? 0;
            $breakdown[] = [
                'label' => $optionLabel,
                'value' => $optionValue,
                'count' => $count,
                'percentage' => $answeredCount > 0 ? round(($count / $answeredCount) * 100, 1) : 0.0,
            ];
            unset($counts[$optionValue]);
        }

        foreach ($counts as $value => $count) {
            $breakdown[] = [
                'label' => $value,
                'value' => $value,
                'count' => $count,
                'percentage' => $answeredCount > 0 ? round(($count / $answeredCount) * 100, 1) : 0.0,
            ];
        }

        return [
            'key' => $question->key,
            'label' => $question->label,
            'type' => $question->type,
            'answered_count' => $answeredCount,
            'breakdown' => $breakdown,
            'stats' => null,
            'recent_answers' => [],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     type: QuestionType,
     *     answered_count: int,
     *     breakdown: array<int, array{label: string, value: string, count: int, percentage: float}>,
     *     stats: null,
     *     recent_answers: array<int, array{value: string, submitted_at: string|null}>
     * }
     */
    private function aggregateTextQuestion(SurveyQuestion $question): array
    {
        $baseQuery = SurveyAnswer::query()
            ->where('question_id', $question->getKey())
            ->whereNotNull('value')
            ->where('value', '!=', '');

        $answeredCount = (clone $baseQuery)->count();

        $recentAnswers = $baseQuery
            ->latest('id')
            ->limit(20)
            ->get(['value', 'created_at'])
            ->map(static fn (SurveyAnswer $answer): array => [
                'value' => (string) $answer->value,
                'submitted_at' => $answer->created_at?->toDateTimeString(),
            ])
            ->all();

        return [
            'key' => $question->key,
            'label' => $question->label,
            'type' => $question->type,
            'answered_count' => $answeredCount,
            'breakdown' => [],
            'stats' => null,
            'recent_answers' => $recentAnswers,
        ];
    }

    private function completionRate(int $responses, int $dismissals): ?float
    {
        $total = $responses + $dismissals;

        if ($total === 0) {
            return null;
        }

        return round(($responses / $total) * 100, 1);
    }
}
