<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers\Admin;

use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SurveyExportController
{
    public function __invoke(string $surveyKey): StreamedResponse
    {
        $record = SurveyRecord::query()
            ->byKey($surveyKey)
            ->with('questions')
            ->first();

        if ($record === null) {
            abort(404, 'Survey not found.');
        }

        $filename = sprintf('survey-%s-results.csv', Str::slug($record->key));

        return response()->streamDownload(function () use ($record): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            $questions = $record->questions;

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

            fputcsv($handle, $headers);

            SurveyResponse::query()
                ->forSurvey($record)
                ->with(['answers.question'])
                ->orderBy('id')
                ->chunkById(200, function (Collection $responses) use ($handle, $questions): void {
                    /** @var SurveyResponse $response */
                    foreach ($responses as $response) {
                        /** @var array<int, SurveyAnswer> $answersByQuestionId */
                        $answersByQuestionId = [];
                        foreach ($response->answers as $answer) {
                            $answersByQuestionId[$answer->question_id] = $answer;
                        }

                        $url = is_array($response->metadata) && is_string($response->metadata['url'] ?? null)
                            ? $response->metadata['url']
                            : '';

                        $row = [
                            (string) $response->getKey(),
                            $response->respondent_type,
                            (string) $response->respondent_id,
                            $response->created_at?->toDateTimeString() ?? '',
                            $url,
                        ];

                        foreach ($questions as $question) {
                            $answer = $answersByQuestionId[$question->getKey()] ?? null;
                            $row[] = $this->formatAnswerForCsv($answer?->typedValue());
                        }

                        fputcsv($handle, $row);
                    }
                });

            fclose($handle);
        }, $filename, [
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

        if (is_array($value)) {
            return implode(', ', array_map(
                static fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
                $value,
            ));
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
