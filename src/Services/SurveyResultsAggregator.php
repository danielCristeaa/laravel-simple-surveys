<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DanielCristea\SimpleSurveys\Enums\QuestionType;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Models\SurveyImpression;
use DanielCristea\SimpleSurveys\Models\SurveyOpen;
use DanielCristea\SimpleSurveys\Models\SurveyQuestion;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

final class SurveyResultsAggregator
{
    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveyStatusResolver $statusResolver,
    ) {}

    /**
     * @return Collection<int, array{
     *     key: string,
     *     title: string,
     *     description: string|null,
     *     status: SurveyStatus,
     *     registered: bool,
     *     priority: int,
     *     starts_at: string|null,
     *     ends_at: string|null,
     *     questions_count: int,
     *     impressions_count: int,
     *     opens_count: int,
     *     abandoned_count: int,
     *     responses_count: int,
     *     dismissals_count: int,
     *     response_rate: float|null,
     *     completion_rate: float|null
     * }>
     */
    public function summaries(): Collection
    {
        $records = SurveyRecord::query()
            ->withCount(['questions', 'responses', 'dismissals', 'opens', 'impressions'])
            ->orderByDesc('priority')
            ->orderBy('title')
            ->get()
            ->keyBy('key');

        /** @var array<int, int> $recordIds */
        $recordIds = [];
        foreach ($records as $record) {
            $recordIds[] = (int) $record->getKey();
        }

        $abandonedBySurveyId = $this->abandonedCountsBySurveyId($recordIds);
        $registeredSurveys = $this->registry->all();

        /** @var array<int, array{
         *     key: string,
         *     title: string,
         *     description: string|null,
         *     status: SurveyStatus,
         *     registered: bool,
         *     priority: int,
         *     starts_at: string|null,
         *     ends_at: string|null,
         *     questions_count: int,
         *     impressions_count: int,
         *     opens_count: int,
         *     abandoned_count: int,
         *     responses_count: int,
         *     dismissals_count: int,
         *     response_rate: float|null,
         *     completion_rate: float|null
         * }> $summaries */
        $summaries = [];

        foreach ($registeredSurveys as $survey) {
            /** @var SurveyRecord|null $record */
            $record = $records->get($survey->key());
            $recordId = $record !== null ? (int) $record->getKey() : null;
            $responsesCount = $record !== null ? (int) $record->getAttribute('responses_count') : 0;
            $dismissalsCount = $record !== null ? (int) $record->getAttribute('dismissals_count') : 0;
            $opensCount = $record !== null ? (int) $record->getAttribute('opens_count') : 0;
            $impressionsCount = $record !== null ? (int) $record->getAttribute('impressions_count') : 0;
            $abandonedCount = $recordId !== null ? ($abandonedBySurveyId[$recordId] ?? 0) : 0;

            $summaries[] = [
                'key' => $survey->key(),
                'title' => $survey->getTitle(),
                'description' => $survey->getDescription(),
                'status' => $this->statusResolver->resolve($survey, $record),
                'registered' => true,
                'priority' => $survey->getPriority(),
                'starts_at' => $this->statusResolver->effectiveStartsAt($survey, $record)?->toDateTimeString(),
                'ends_at' => $this->statusResolver->effectiveEndsAt($survey, $record)?->toDateTimeString(),
                'questions_count' => count($survey->getQuestions()),
                'impressions_count' => $impressionsCount,
                'opens_count' => $opensCount,
                'abandoned_count' => $abandonedCount,
                'responses_count' => $responsesCount,
                'dismissals_count' => $dismissalsCount,
                'response_rate' => $this->rate($responsesCount, $impressionsCount),
                'completion_rate' => $this->rate($responsesCount, $opensCount),
            ];
        }

        // Include historical/archived DB surveys that are no longer registered in code
        foreach ($records as $key => $record) {
            if ($registeredSurveys->has($key)) {
                continue;
            }

            $recordId = (int) $record->getKey();
            $responsesCount = (int) $record->getAttribute('responses_count');
            $dismissalsCount = (int) $record->getAttribute('dismissals_count');
            $opensCount = (int) $record->getAttribute('opens_count');
            $impressionsCount = (int) $record->getAttribute('impressions_count');
            $abandonedCount = $abandonedBySurveyId[$recordId] ?? 0;

            $summaries[] = [
                'key' => $record->key,
                'title' => $record->title,
                'description' => $record->description,
                'status' => SurveyStatus::Closed,
                'registered' => false,
                'priority' => (int) $record->priority,
                'starts_at' => $record->starts_at?->toDateTimeString(),
                'ends_at' => $record->ends_at?->toDateTimeString(),
                'questions_count' => (int) $record->getAttribute('questions_count'),
                'impressions_count' => $impressionsCount,
                'opens_count' => $opensCount,
                'abandoned_count' => $abandonedCount,
                'responses_count' => $responsesCount,
                'dismissals_count' => $dismissalsCount,
                'response_rate' => $this->rate($responsesCount, $impressionsCount),
                'completion_rate' => $this->rate($responsesCount, $opensCount),
            ];
        }

        return new Collection($summaries);
    }

    /**
     * Lightweight survey list for navigation switchers.
     *
     * @return array<int, array{key: string, title: string, status: SurveyStatus, registered: bool}>
     */
    public function surveyOptions(): array
    {
        $records = SurveyRecord::query()
            ->orderByDesc('priority')
            ->orderBy('title')
            ->get(['key', 'title', 'status', 'starts_at', 'ends_at'])
            ->keyBy('key');

        $registeredSurveys = $this->registry->all();
        $options = [];

        foreach ($registeredSurveys as $survey) {
            /** @var SurveyRecord|null $record */
            $record = $records->get($survey->key());

            $options[] = [
                'key' => $survey->key(),
                'title' => $survey->getTitle(),
                'status' => $this->statusResolver->resolve($survey, $record),
                'registered' => true,
            ];
        }

        foreach ($records as $key => $record) {
            if ($registeredSurveys->has($key)) {
                continue;
            }

            $options[] = [
                'key' => $record->key,
                'title' => $record->title,
                'status' => SurveyStatus::Closed,
                'registered' => false,
            ];
        }

        return $options;
    }

    /**
     * Compute average completion time (in milliseconds) across responses with recorded duration_ms.
     *
     * @return array{avg_ms: int|null, count: int}
     */
    public function averageCompletionTime(): array
    {
        $totalMs = 0;
        $count = 0;

        SurveyResponse::query()
            ->whereNotNull('metadata')
            ->select(['id', 'metadata'])
            ->chunkById(500, static function (Collection $chunk) use (&$totalMs, &$count): void {
                foreach ($chunk as $response) {
                    $meta = $response->metadata;
                    $duration = is_array($meta) ? ($meta['duration_ms'] ?? null) : null;

                    if (is_numeric($duration) && (int) $duration > 0) {
                        $totalMs += (int) $duration;
                        $count++;
                    }
                }
            });

        return [
            'avg_ms' => $count > 0 ? (int) round($totalMs / $count) : null,
            'count' => $count,
        ];
    }

    /**
     * @param  array{
     *     range?: string|null,
     *     q?: string|null,
     *     locale?: string|null,
     *     question?: string|null,
     *     answer?: string|null
     * }  $filters
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string|null,
     *     status: SurveyStatus,
     *     registered: bool,
     *     priority: int,
     *     starts_at: string|null,
     *     ends_at: string|null,
     *     dismissible: bool,
     *     impressions_count: int,
     *     opens_count: int,
     *     abandoned_count: int,
     *     responses_count: int,
     *     dismissals_count: int,
     *     total_responses_count: int,
     *     total_telemetry_count: int,
     *     response_rate: float|null,
     *     completion_rate: float|null,
     *     daily_responses: array<int, array{date: string, label: string, count: int}>,
     *     locales: array<int, array{locale: string, count: int, percentage: float}>,
     *     questions: array<int, array<string, mixed>>,
     *     responses: LengthAwarePaginator<int, SurveyResponse>|null
     * }|null
     */
    public function forSurvey(string $surveyKey, int $perPage = 15, array $filters = []): ?array
    {
        $record = SurveyRecord::query()
            ->byKey($surveyKey)
            ->with('questions')
            ->withCount(['responses', 'dismissals', 'opens', 'impressions'])
            ->first();

        $registered = $this->registry->find($surveyKey);

        if ($record === null && $registered === null) {
            return null;
        }

        $range = $this->normalizeRange($filters['range'] ?? null);
        $since = $this->sinceForRange($range);

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
                    'histogram' => [],
                    'nps' => null,
                    'scale_min' => $question->type() === QuestionType::Rating ? (int) ($question->getMin() ?? 1) : null,
                    'scale_max' => $question->type() === QuestionType::Rating ? (int) ($question->getMax() ?? 5) : null,
                    'recent_answers' => [],
                ];
            }

            return [
                'key' => $registered->key(),
                'title' => $registered->getTitle(),
                'description' => $registered->getDescription(),
                'status' => $this->statusResolver->resolve($registered, null),
                'registered' => true,
                'priority' => $registered->getPriority(),
                'starts_at' => $this->statusResolver->effectiveStartsAt($registered, null)?->toDateTimeString(),
                'ends_at' => $this->statusResolver->effectiveEndsAt($registered, null)?->toDateTimeString(),
                'dismissible' => $registered->isDismissible(),
                'impressions_count' => 0,
                'opens_count' => 0,
                'abandoned_count' => 0,
                'responses_count' => 0,
                'dismissals_count' => 0,
                'total_responses_count' => 0,
                'total_telemetry_count' => 0,
                'response_rate' => null,
                'completion_rate' => null,
                'daily_responses' => $this->buildDailyBuckets([], $range),
                'locales' => [],
                'questions' => $emptyQuestions,
                'responses' => null,
            ];
        }

        /** @var SurveyRecord $record */
        $recordId = (int) $record->getKey();
        $allTimeResponsesCount = (int) $record->getAttribute('responses_count');
        $allTimeDismissalsCount = (int) $record->getAttribute('dismissals_count');
        $allTimeOpensCount = (int) $record->getAttribute('opens_count');
        $allTimeImpressionsCount = (int) $record->getAttribute('impressions_count');
        $totalTelemetryCount = $allTimeResponsesCount + $allTimeDismissalsCount + $allTimeOpensCount + $allTimeImpressionsCount;

        if ($since === null) {
            $responsesCount = $allTimeResponsesCount;
            $dismissalsCount = $allTimeDismissalsCount;
            $opensCount = $allTimeOpensCount;
            $impressionsCount = $allTimeImpressionsCount;
            $abandonedCounts = $this->abandonedCountsBySurveyId([$recordId]);
            $abandonedCount = $abandonedCounts[$recordId] ?? 0;
        } else {
            $responsesCount = SurveyResponse::query()
                ->where('survey_id', $recordId)
                ->where('created_at', '>=', $since)
                ->count();
            $dismissalsCount = SurveyDismissal::query()
                ->where('survey_id', $recordId)
                ->where('dismissed_at', '>=', $since)
                ->count();
            $opensCount = SurveyOpen::query()
                ->where('survey_id', $recordId)
                ->where('first_opened_at', '>=', $since)
                ->count();
            $impressionsCount = SurveyImpression::query()
                ->where('survey_id', $recordId)
                ->where('first_seen_at', '>=', $since)
                ->count();
            $abandonedCounts = $this->abandonedCountsBySurveyId([$recordId], $since);
            $abandonedCount = $abandonedCounts[$recordId] ?? 0;
        }

        $questionsData = [];

        foreach ($record->questions as $question) {
            $questionsData[] = $this->aggregateQuestion($question, $since);
        }

        $responsesQuery = SurveyResponse::query()
            ->forSurvey($record)
            ->with(['answers.question']);

        if ($since !== null) {
            $responsesQuery->where('created_at', '>=', $since);
        }

        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $responsesQuery->where(static function (EloquentBuilder $query) use ($like, $search): void {
                $query->where('respondent_id', 'like', $like)
                    ->orWhere('metadata->url', 'like', $like)
                    ->orWhere('metadata->locale', 'like', $like)
                    ->orWhereHas('answers', static function (EloquentBuilder $answerQuery) use ($like, $search): void {
                        $answerQuery->where('value', 'like', $like)
                            ->orWhereJsonContains('value_json', $search);
                    });
            });
        }

        $localeFilter = trim($filters['locale'] ?? '');
        if ($localeFilter !== '') {
            $responsesQuery->where('metadata->locale', $localeFilter);
        }

        $questionFilter = trim($filters['question'] ?? '');
        $answerFilter = trim($filters['answer'] ?? '');

        if ($questionFilter !== '' && $answerFilter !== '') {
            /** @var SurveyQuestion|null $matchedQuestion */
            $matchedQuestion = $record->questions->firstWhere('key', $questionFilter);

            if ($matchedQuestion !== null) {
                $questionId = (int) $matchedQuestion->getKey();
                $isCheckbox = $matchedQuestion->type === QuestionType::Checkbox;

                $responsesQuery->whereHas('answers', static function (EloquentBuilder $answerQuery) use ($questionId, $answerFilter, $isCheckbox): void {
                    $answerQuery->where('question_id', $questionId);

                    if ($isCheckbox) {
                        $answerQuery->whereJsonContains('value_json', $answerFilter);
                    } else {
                        $answerQuery->where('value', $answerFilter);
                    }
                });
            }
        }

        $responses = $responsesQuery
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);

        $dailyResponses = $this->aggregateDailyResponses($recordId, $range, $since);
        $locales = $this->aggregateLocales($recordId, $since);

        return [
            'key' => $record->key,
            'title' => $registered !== null ? $registered->getTitle() : $record->title,
            'description' => $registered !== null ? $registered->getDescription() : $record->description,
            'status' => $registered !== null
                ? $this->statusResolver->resolve($registered, $record)
                : SurveyStatus::Closed,
            'registered' => $registered !== null,
            'priority' => $registered !== null ? $registered->getPriority() : (int) $record->priority,
            'starts_at' => ($registered !== null
                ? $this->statusResolver->effectiveStartsAt($registered, $record)
                : $record->starts_at)?->toDateTimeString(),
            'ends_at' => ($registered !== null
                ? $this->statusResolver->effectiveEndsAt($registered, $record)
                : $record->ends_at)?->toDateTimeString(),
            'dismissible' => $registered !== null ? $registered->isDismissible() : (bool) $record->dismissible,
            'impressions_count' => $impressionsCount,
            'opens_count' => $opensCount,
            'abandoned_count' => $abandonedCount,
            'responses_count' => $responsesCount,
            'dismissals_count' => $dismissalsCount,
            'total_responses_count' => $allTimeResponsesCount,
            'total_telemetry_count' => $totalTelemetryCount,
            'response_rate' => $this->rate($responsesCount, $impressionsCount),
            'completion_rate' => $this->rate($responsesCount, $opensCount),
            'daily_responses' => $dailyResponses,
            'locales' => $locales,
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
     *     histogram: array<int, array{label: string, count: int, percentage: float}>,
     *     nps: array{score: int, promoters: int, passives: int, detractors: int, promoters_pct: float, passives_pct: float, detractors_pct: float}|null,
     *     scale_min: int|null,
     *     scale_max: int|null,
     *     recent_answers: array<int, array{value: string, submitted_at: string|null}>
     * }
     */
    private function aggregateQuestion(SurveyQuestion $question, ?CarbonInterface $since = null): array
    {
        return match ($question->type) {
            QuestionType::Number, QuestionType::Rating => $this->aggregateNumericQuestion($question, $since),
            QuestionType::Select, QuestionType::Radio => $this->aggregateChoiceQuestion($question, $since),
            QuestionType::Boolean => $this->aggregateBooleanQuestion($question, $since),
            QuestionType::Checkbox => $this->aggregateCheckboxQuestion($question, $since),
            QuestionType::Text, QuestionType::Textarea => $this->aggregateTextQuestion($question, $since),
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
     *     histogram: array<int, array{label: string, count: int, percentage: float}>,
     *     nps: array{score: int, promoters: int, passives: int, detractors: int, promoters_pct: float, passives_pct: float, detractors_pct: float}|null,
     *     scale_min: int|null,
     *     scale_max: int|null,
     *     recent_answers: array<int, array{value: string, submitted_at: string|null}>
     * }
     */
    private function aggregateNumericQuestion(SurveyQuestion $question, ?CarbonInterface $since = null): array
    {
        $row = SurveyAnswer::query()
            ->where('question_id', $question->getKey())
            ->whereNotNull('value_numeric')
            ->when($since !== null, static fn (EloquentBuilder $q): EloquentBuilder => $q->where('created_at', '>=', $since))
            ->selectRaw('COUNT(*) as answered_count, AVG(value_numeric) as avg_val, MIN(value_numeric) as min_val, MAX(value_numeric) as max_val')
            ->first();

        $answeredCount = $row !== null ? (int) $row->getAttribute('answered_count') : 0;
        $avgRaw = $row?->getAttribute('avg_val');
        $minRaw = $row?->getAttribute('min_val');
        $maxRaw = $row?->getAttribute('max_val');

        $breakdown = [];
        $histogram = [];
        $nps = null;
        $scaleMin = null;
        $scaleMax = null;

        if ($question->type === QuestionType::Rating) {
            /** @var array<int|string, int> $counts */
            $counts = SurveyAnswer::query()
                ->where('question_id', $question->getKey())
                ->whereNotNull('value')
                ->when($since !== null, static fn (EloquentBuilder $q): EloquentBuilder => $q->where('created_at', '>=', $since))
                ->selectRaw('value, COUNT(*) as aggregate_count')
                ->groupBy('value')
                ->pluck('aggregate_count', 'value')
                ->map(static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0)
                ->all();

            $constraints = $question->constraints ?? [];
            $scaleMin = is_numeric($constraints['min'] ?? null) ? (int) $constraints['min'] : 1;
            $scaleMax = is_numeric($constraints['max'] ?? null) ? (int) $constraints['max'] : 5;

            $promoters = 0;
            $passives = 0;
            $detractors = 0;

            for ($score = $scaleMin; $score <= $scaleMax; $score++) {
                $scoreKey = (string) $score;
                $count = $counts[$score] ?? $counts[$scoreKey] ?? 0;
                $breakdown[] = [
                    'label' => $scoreKey,
                    'value' => $scoreKey,
                    'count' => $count,
                    'percentage' => $answeredCount > 0 ? round(($count / $answeredCount) * 100, 1) : 0.0,
                ];

                if ($scaleMin === 0 && $scaleMax === 10) {
                    if ($score >= 9) {
                        $promoters += $count;
                    } elseif ($score >= 7) {
                        $passives += $count;
                    } else {
                        $detractors += $count;
                    }
                }
            }

            if ($scaleMin === 0 && $scaleMax === 10 && $answeredCount > 0) {
                $promotersPct = round(($promoters / $answeredCount) * 100, 1);
                $passivesPct = round(($passives / $answeredCount) * 100, 1);
                $detractorsPct = round(($detractors / $answeredCount) * 100, 1);

                $nps = [
                    'score' => (int) round((($promoters - $detractors) / $answeredCount) * 100),
                    'promoters' => $promoters,
                    'passives' => $passives,
                    'detractors' => $detractors,
                    'promoters_pct' => $promotersPct,
                    'passives_pct' => $passivesPct,
                    'detractors_pct' => $detractorsPct,
                ];
            }
        } elseif ($answeredCount > 0 && is_numeric($minRaw) && is_numeric($maxRaw)) {
            $histogram = $this->buildNumericHistogram(
                $question,
                (float) $minRaw,
                (float) $maxRaw,
                $answeredCount,
                $since,
            );
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
            'histogram' => $histogram,
            'nps' => $nps,
            'scale_min' => $scaleMin,
            'scale_max' => $scaleMax,
            'recent_answers' => [],
        ];
    }

    /**
     * @return array<int, array{label: string, count: int, percentage: float}>
     */
    private function buildNumericHistogram(
        SurveyQuestion $question,
        float $min,
        float $max,
        int $answeredCount,
        ?CarbonInterface $since,
    ): array {
        /** @var array<int, float> $values */
        $values = [];

        SurveyAnswer::query()
            ->where('question_id', $question->getKey())
            ->whereNotNull('value_numeric')
            ->when($since !== null, static fn (EloquentBuilder $q): EloquentBuilder => $q->where('created_at', '>=', $since))
            ->select(['id', 'value_numeric'])
            ->chunkById(500, static function (Collection $chunk) use (&$values): void {
                foreach ($chunk as $answer) {
                    if (is_numeric($answer->value_numeric)) {
                        $values[] = (float) $answer->value_numeric;
                    }
                }
            });

        if ($values === []) {
            return [];
        }

        if (abs($max - $min) < 0.00001) {
            return [[
                'label' => $this->formatNumber($min),
                'count' => count($values),
                'percentage' => 100.0,
            ]];
        }

        $bucketCount = min(6, max(2, count(array_unique($values))));
        $step = ($max - $min) / $bucketCount;
        $buckets = array_fill(0, $bucketCount, 0);

        foreach ($values as $value) {
            $index = (int) floor(($value - $min) / $step);
            if ($index >= $bucketCount) {
                $index = $bucketCount - 1;
            }
            if ($index < 0) {
                $index = 0;
            }
            $buckets[$index]++;
        }

        $histogram = [];

        for ($i = 0; $i < $bucketCount; $i++) {
            $start = $min + ($i * $step);
            $end = $i === $bucketCount - 1 ? $max : $min + (($i + 1) * $step);
            $count = $buckets[$i];
            $histogram[] = [
                'label' => sprintf('%s–%s', $this->formatNumber($start), $this->formatNumber($end)),
                'count' => $count,
                'percentage' => $answeredCount > 0 ? round(($count / $answeredCount) * 100, 1) : 0.0,
            ];
        }

        return $histogram;
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     type: QuestionType,
     *     answered_count: int,
     *     breakdown: array<int, array{label: string, value: string, count: int, percentage: float}>,
     *     stats: null,
     *     histogram: array<int, array{label: string, count: int, percentage: float}>,
     *     nps: null,
     *     scale_min: null,
     *     scale_max: null,
     *     recent_answers: array<int, array{value: string, submitted_at: string|null}>
     * }
     */
    private function aggregateChoiceQuestion(SurveyQuestion $question, ?CarbonInterface $since = null): array
    {
        /** @var array<string, int> $counts */
        $counts = SurveyAnswer::query()
            ->where('question_id', $question->getKey())
            ->whereNotNull('value')
            ->when($since !== null, static fn (EloquentBuilder $q): EloquentBuilder => $q->where('created_at', '>=', $since))
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
            'histogram' => [],
            'nps' => null,
            'scale_min' => null,
            'scale_max' => null,
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
     *     histogram: array<int, array{label: string, count: int, percentage: float}>,
     *     nps: null,
     *     scale_min: null,
     *     scale_max: null,
     *     recent_answers: array<int, array{value: string, submitted_at: string|null}>
     * }
     */
    private function aggregateBooleanQuestion(SurveyQuestion $question, ?CarbonInterface $since = null): array
    {
        /** @var array<int|string, int> $counts */
        $counts = SurveyAnswer::query()
            ->where('question_id', $question->getKey())
            ->whereNotNull('value')
            ->when($since !== null, static fn (EloquentBuilder $q): EloquentBuilder => $q->where('created_at', '>=', $since))
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
            'histogram' => [],
            'nps' => null,
            'scale_min' => null,
            'scale_max' => null,
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
     *     histogram: array<int, array{label: string, count: int, percentage: float}>,
     *     nps: null,
     *     scale_min: null,
     *     scale_max: null,
     *     recent_answers: array<int, array{value: string, submitted_at: string|null}>
     * }
     */
    private function aggregateCheckboxQuestion(SurveyQuestion $question, ?CarbonInterface $since = null): array
    {
        $answeredCount = 0;
        /** @var array<string, int> $counts */
        $counts = [];

        SurveyAnswer::query()
            ->where('question_id', $question->getKey())
            ->whereNotNull('value_json')
            ->when($since !== null, static fn (EloquentBuilder $q): EloquentBuilder => $q->where('created_at', '>=', $since))
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
            'histogram' => [],
            'nps' => null,
            'scale_min' => null,
            'scale_max' => null,
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
     *     histogram: array<int, array{label: string, count: int, percentage: float}>,
     *     nps: null,
     *     scale_min: null,
     *     scale_max: null,
     *     recent_answers: array<int, array{value: string, submitted_at: string|null}>
     * }
     */
    private function aggregateTextQuestion(SurveyQuestion $question, ?CarbonInterface $since = null): array
    {
        $baseQuery = SurveyAnswer::query()
            ->where('question_id', $question->getKey())
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->when($since !== null, static fn (EloquentBuilder $q): EloquentBuilder => $q->where('created_at', '>=', $since));

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
            'histogram' => [],
            'nps' => null,
            'scale_min' => null,
            'scale_max' => null,
            'recent_answers' => $recentAnswers,
        ];
    }

    /**
     * @return array<int, array{date: string, label: string, count: int}>
     */
    private function aggregateDailyResponses(int $recordId, string $range, ?CarbonInterface $since): array
    {
        /** @var array<string, int> $countsByDate */
        $countsByDate = [];

        SurveyResponse::query()
            ->where('survey_id', $recordId)
            ->when($since !== null, static fn (EloquentBuilder $q): EloquentBuilder => $q->where('created_at', '>=', $since))
            ->select(['id', 'created_at'])
            ->chunkById(500, static function (Collection $chunk) use (&$countsByDate): void {
                foreach ($chunk as $response) {
                    if ($response->created_at !== null) {
                        $dateKey = $response->created_at->format('Y-m-d');
                        $countsByDate[$dateKey] = ($countsByDate[$dateKey] ?? 0) + 1;
                    }
                }
            });

        return $this->buildDailyBuckets($countsByDate, $range);
    }

    /**
     * @param  array<string, int>  $countsByDate
     * @return array<int, array{date: string, label: string, count: int}>
     */
    private function buildDailyBuckets(array $countsByDate, string $range): array
    {
        $endDate = Carbon::now()->startOfDay();

        if ($range === '7d') {
            $days = 7;
            $stepDays = 1;
        } elseif ($range === '30d') {
            $days = 30;
            $stepDays = 1;
        } elseif ($range === '90d') {
            $days = 90;
            $stepDays = 3;
        } else {
            $days = 14;
            $stepDays = 1;

            if ($countsByDate !== []) {
                ksort($countsByDate);
                $latestKey = (string) array_key_last($countsByDate);
                $latestDate = Carbon::parse($latestKey)->startOfDay();

                if ($latestDate->lt($endDate->copy()->subDays(13))) {
                    $endDate = $latestDate;
                }
            }
        }

        $startDate = $endDate->copy()->subDays($days - 1);
        $buckets = [];
        $cursor = $startDate->copy();

        while ($cursor->lte($endDate)) {
            $bucketEnd = $cursor->copy()->addDays($stepDays - 1);
            if ($bucketEnd->gt($endDate)) {
                $bucketEnd = $endDate->copy();
            }

            $sum = 0;
            $day = $cursor->copy();
            while ($day->lte($bucketEnd)) {
                $sum += $countsByDate[$day->format('Y-m-d')] ?? 0;
                $day->addDay();
            }

            $buckets[] = [
                'date' => $cursor->format('Y-m-d'),
                'label' => $stepDays > 1
                    ? $cursor->format('M j').'–'.$bucketEnd->format('M j')
                    : $cursor->format('M j'),
                'count' => $sum,
            ];

            $cursor->addDays($stepDays);
        }

        return $buckets;
    }

    /**
     * @return array<int, array{locale: string, count: int, percentage: float}>
     */
    private function aggregateLocales(int $recordId, ?CarbonInterface $since): array
    {
        /** @var array<string, int> $counts */
        $counts = [];
        $total = 0;

        SurveyResponse::query()
            ->where('survey_id', $recordId)
            ->whereNotNull('metadata')
            ->when($since !== null, static fn (EloquentBuilder $q): EloquentBuilder => $q->where('created_at', '>=', $since))
            ->select(['id', 'metadata'])
            ->chunkById(500, static function (Collection $chunk) use (&$counts, &$total): void {
                foreach ($chunk as $response) {
                    $meta = $response->metadata;
                    $locale = is_array($meta) && is_string($meta['locale'] ?? null)
                        ? trim($meta['locale'])
                        : '';

                    if ($locale !== '') {
                        $counts[$locale] = ($counts[$locale] ?? 0) + 1;
                        $total++;
                    }
                }
            });

        if ($counts === []) {
            return [];
        }

        arsort($counts);

        $locales = [];
        foreach ($counts as $locale => $count) {
            $locales[] = [
                'locale' => $locale,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
            ];
        }

        return $locales;
    }

    /**
     * @param  array<int, int>  $surveyIds
     * @return array<int, int>
     */
    private function abandonedCountsBySurveyId(array $surveyIds, ?CarbonInterface $since = null): array
    {
        if ($surveyIds === []) {
            return [];
        }

        $opensTable = (new SurveyOpen)->getTable();
        $responsesTable = (new SurveyResponse)->getTable();

        /** @var array<int|string, mixed> $rawCounts */
        $rawCounts = SurveyOpen::query()
            ->whereIn('survey_id', $surveyIds)
            ->when($since !== null, static fn (EloquentBuilder $q): EloquentBuilder => $q->where('first_opened_at', '>=', $since))
            ->whereNotExists(static function (QueryBuilder $query) use ($opensTable, $responsesTable): void {
                $query->selectRaw('1')
                    ->from($responsesTable)
                    ->whereColumn($responsesTable.'.survey_id', $opensTable.'.survey_id')
                    ->whereColumn($responsesTable.'.respondent_type', $opensTable.'.respondent_type')
                    ->whereColumn($responsesTable.'.respondent_id', $opensTable.'.respondent_id');
            })
            ->select('survey_id')
            ->selectRaw('COUNT(*) as abandoned_count')
            ->groupBy('survey_id')
            ->pluck('abandoned_count', 'survey_id')
            ->all();

        /** @var array<int, int> $counts */
        $counts = [];

        foreach ($rawCounts as $surveyId => $count) {
            $counts[(int) $surveyId] = is_numeric($count) ? (int) $count : 0;
        }

        return $counts;
    }

    private function normalizeRange(?string $range): string
    {
        return match ($range) {
            '7d', '30d', '90d' => $range,
            default => 'all',
        };
    }

    private function sinceForRange(string $range): ?CarbonInterface
    {
        return match ($range) {
            '7d' => Carbon::now()->subDays(6)->startOfDay(),
            '30d' => Carbon::now()->subDays(29)->startOfDay(),
            '90d' => Carbon::now()->subDays(89)->startOfDay(),
            default => null,
        };
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }

        return min(100.0, round(($numerator / $denominator) * 100, 1));
    }
}
