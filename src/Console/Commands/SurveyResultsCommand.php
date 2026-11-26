<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Console\Commands;

use DanielCristea\SimpleSurveys\Enums\QuestionType;
use DanielCristea\SimpleSurveys\SimpleSurveys;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class SurveyResultsCommand extends Command
{
    protected $signature = 'simple-surveys:results
        {key? : Inspect detailed funnel and question breakdowns for a single survey key}
        {--json : Output results as formatted JSON}';

    protected $description = 'Display survey funnel summaries or detailed question results';

    public function handle(SimpleSurveys $surveys): int
    {
        $keyArgument = $this->argument('key');
        $key = is_string($keyArgument) && trim($keyArgument) !== '' ? trim($keyArgument) : null;
        $asJson = (bool) $this->option('json');

        if ($key !== null) {
            return $this->showSurvey($surveys, $key, $asJson);
        }

        return $this->showSummaries($surveys, $asJson);
    }

    private function showSummaries(SimpleSurveys $surveys, bool $asJson): int
    {
        $summaries = $surveys->summaries();

        if ($asJson) {
            $this->line((string) json_encode($summaries->values()->all(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        if ($summaries->isEmpty()) {
            $this->components->info('No surveys found.');

            return self::SUCCESS;
        }

        $rows = $summaries->map(fn (array $summary): array => [
            $summary['key'],
            Str::limit($summary['title'], 28),
            $summary['registered'] ? $summary['status']->value : 'archived',
            (string) $summary['impressions_count'],
            (string) $summary['opens_count'],
            (string) $summary['responses_count'],
            sprintf('%s / %s', $this->formatRate($summary['response_rate']), $this->formatRate($summary['completion_rate'])),
        ])->all();

        $this->table(
            ['Key', 'Title', 'Status', 'Seen', 'Open', 'Resp', 'Resp / Comp'],
            $rows,
        );

        return self::SUCCESS;
    }

    private function showSurvey(SimpleSurveys $surveys, string $key, bool $asJson): int
    {
        $results = $surveys->results($key);

        if ($results === null) {
            $this->components->error(sprintf('Survey "%s" not found.', $key));

            return self::FAILURE;
        }

        if ($asJson) {
            $this->line((string) json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $statusLabel = $results['registered'] ? $results['status']->value : 'archived';

        $this->components->info(sprintf(
            '%s (%s) [%s]',
            $results['title'],
            $results['key'],
            $statusLabel,
        ));

        $this->table(
            ['Seen', 'Opened', 'Responses', 'Abandoned', 'Dismissed', 'Response Rate', 'Completion Rate'],
            [[
                (string) $results['impressions_count'],
                (string) $results['opens_count'],
                (string) $results['responses_count'],
                (string) $results['abandoned_count'],
                (string) $results['dismissals_count'],
                $this->formatRate($results['response_rate']),
                $this->formatRate($results['completion_rate']),
            ]],
        );

        if ($results['questions'] === []) {
            $this->components->info('No questions configured for this survey.');

            return self::SUCCESS;
        }

        foreach ($results['questions'] as $question) {
            $this->renderQuestion($question);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function renderQuestion(array $question): void
    {
        $label = is_string($question['label'] ?? null) ? $question['label'] : '';
        $type = $question['type'] ?? null;
        $typeValue = $type instanceof QuestionType ? $type->value : (is_string($type) ? $type : 'unknown');
        $answeredCount = is_numeric($question['answered_count'] ?? null) ? (int) $question['answered_count'] : 0;

        $this->newLine();
        $this->line(sprintf('<comment>%s</comment> (%s — %d %s)', $label, $typeValue, $answeredCount, Str::plural('answer', $answeredCount)));

        $stats = $question['stats'] ?? null;

        if (is_array($stats) && $answeredCount > 0) {
            $this->line(sprintf(
                '  Avg: %s | Min: %s | Max: %s',
                $this->formatNumericStat($stats['avg'] ?? null),
                $this->formatNumericStat($stats['min'] ?? null),
                $this->formatNumericStat($stats['max'] ?? null),
            ));
        }

        $breakdown = $question['breakdown'] ?? null;

        if (is_array($breakdown) && $breakdown !== []) {
            $rows = [];

            foreach ($breakdown as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $itemLabel = is_string($item['label'] ?? null) ? $item['label'] : '';
                $itemCount = is_numeric($item['count'] ?? null) ? (int) $item['count'] : 0;
                $itemPercentage = is_numeric($item['percentage'] ?? null) ? (float) $item['percentage'] : 0.0;

                $rows[] = [
                    $itemLabel,
                    (string) $itemCount,
                    sprintf('%s%%', rtrim(rtrim(number_format($itemPercentage, 1, '.', ''), '0'), '.')),
                ];
            }

            if ($rows !== []) {
                $this->table(['Option', 'Count', 'Share'], $rows);
            }
        }

        $recentAnswers = $question['recent_answers'] ?? null;

        if (is_array($recentAnswers) && $recentAnswers !== []) {
            $preview = array_slice($recentAnswers, 0, 5);

            foreach ($preview as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $value = is_string($entry['value'] ?? null) ? Str::limit(str_replace(["\r", "\n"], ' ', $entry['value']), 100) : '';
                $submittedAt = is_string($entry['submitted_at'] ?? null) ? $entry['submitted_at'] : '';

                $this->line($submittedAt !== '' ? sprintf('  - %s (%s)', $value, $submittedAt) : sprintf('  - %s', $value));
            }
        }
    }

    private function formatRate(?float $rate): string
    {
        if ($rate === null) {
            return '—';
        }

        return sprintf('%s%%', rtrim(rtrim(number_format($rate, 1, '.', ''), '0'), '.'));
    }

    private function formatNumericStat(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
