<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SurveyCsvExporter
{
    private const UTF8_BOM = "\xEF\xBB\xBF";

    /** @var list<string> */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public function streamDownload(Survey|string $survey, ?string $filename = null): ?StreamedResponse
    {
        $surveyKey = $survey instanceof Survey ? $survey->key() : $survey;

        $record = SurveyRecord::query()
            ->byKey($surveyKey)
            ->with('questions')
            ->first();

        if ($record === null) {
            return null;
        }

        $resolvedFilename = ($filename !== null && $filename !== '')
            ? $filename
            : sprintf('survey-%s-results.csv', Str::slug($record->key));

        return response()->streamDownload(function () use ($record): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, self::UTF8_BOM);

            $questions = $record->questions;
            $hasDuration = false;
            $hasLocale = false;
            /** @var array<string, true> $trackingKeySet */
            $trackingKeySet = [];

            SurveyResponse::query()
                ->forSurvey($record)
                ->select(['id', 'metadata'])
                ->orderBy('id')
                ->chunkById(200, function (Collection $responses) use (&$hasDuration, &$hasLocale, &$trackingKeySet): void {
                    /** @var SurveyResponse $response */
                    foreach ($responses as $response) {
                        $metadata = $response->metadata;

                        if (! is_array($metadata)) {
                            continue;
                        }

                        if (isset($metadata['duration_ms']) && is_numeric($metadata['duration_ms'])) {
                            $hasDuration = true;
                        }

                        if (isset($metadata['locale']) && is_string($metadata['locale']) && trim($metadata['locale']) !== '') {
                            $hasLocale = true;
                        }

                        $tracking = $metadata['tracking'] ?? null;

                        if (is_array($tracking)) {
                            foreach (array_keys($tracking) as $trackingKey) {
                                if (is_string($trackingKey) && $trackingKey !== '') {
                                    $trackingKeySet[$trackingKey] = true;
                                }
                            }
                        }
                    }
                });

            $trackingKeys = array_keys($trackingKeySet);
            sort($trackingKeys);

            $headers = [
                'Response ID',
                'Respondent Type',
                'Respondent ID',
                'Submitted At',
                'Page URL',
            ];

            foreach ($questions as $question) {
                $headers[] = $question->label;
            }

            if ($hasDuration) {
                $headers[] = 'Duration (ms)';
            }

            if ($hasLocale) {
                $headers[] = 'Locale';
            }

            foreach ($trackingKeys as $trackingKey) {
                $headers[] = $trackingKey;
            }

            fputcsv($handle, array_map($this->sanitizeCsvCell(...), $headers));

            SurveyResponse::query()
                ->forSurvey($record)
                ->with(['answers.question'])
                ->orderBy('id')
                ->chunkById(200, function (Collection $responses) use ($handle, $questions, $hasDuration, $hasLocale, $trackingKeys): void {
                    /** @var SurveyResponse $response */
                    foreach ($responses as $response) {
                        /** @var array<int, SurveyAnswer> $answersByQuestionId */
                        $answersByQuestionId = [];
                        foreach ($response->answers as $answer) {
                            $answersByQuestionId[$answer->question_id] = $answer;
                        }

                        $metadata = is_array($response->metadata) ? $response->metadata : [];

                        $url = is_string($metadata['url'] ?? null)
                            ? $metadata['url']
                            : '';

                        $row = [
                            $this->sanitizeCsvCell((string) $response->getKey()),
                            $this->sanitizeCsvCell($response->respondent_type),
                            $this->sanitizeCsvCell((string) $response->respondent_id),
                            $this->sanitizeCsvCell($response->created_at?->toDateTimeString() ?? ''),
                            $this->sanitizeCsvCell($url),
                        ];

                        foreach ($questions as $question) {
                            $answer = $answersByQuestionId[$question->getKey()] ?? null;
                            $row[] = $this->formatAnswerForCsv($answer?->typedValue());
                        }

                        if ($hasDuration) {
                            $duration = $metadata['duration_ms'] ?? null;
                            $row[] = is_numeric($duration) ? (string) (int) $duration : '';
                        }

                        if ($hasLocale) {
                            $locale = $metadata['locale'] ?? null;
                            $row[] = is_string($locale) ? $this->sanitizeCsvCell(trim($locale)) : '';
                        }

                        $tracking = is_array($metadata['tracking'] ?? null) ? $metadata['tracking'] : [];

                        foreach ($trackingKeys as $trackingKey) {
                            $row[] = $this->formatAnswerForCsv($tracking[$trackingKey] ?? null);
                        }

                        fputcsv($handle, $row);
                    }
                });

            fclose($handle);
        }, $resolvedFilename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function formatAnswerForCsv(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return is_finite($value) ? (string) $value : '';
        }

        if (is_array($value)) {
            $joined = implode(', ', array_map(
                static fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
                $value,
            ));

            return $this->sanitizeCsvCell($joined);
        }

        return is_scalar($value) ? $this->sanitizeCsvCell((string) $value) : '';
    }

    private function sanitizeCsvCell(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^-[0-9]+(?:\.[0-9]+)?$/', $value) === 1 && is_finite((float) $value)) {
            return $value;
        }

        if (in_array($value[0], self::FORMULA_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }
}
