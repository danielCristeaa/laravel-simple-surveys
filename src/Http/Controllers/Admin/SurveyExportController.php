<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers\Admin;

use DanielCristea\SimpleSurveys\Services\SurveyCsvExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SurveyExportController
{
    public function __construct(
        private readonly SurveyCsvExporter $exporter,
    ) {}

    public function __invoke(string $surveyKey): StreamedResponse
    {
        $response = $this->exporter->streamDownload($surveyKey);

        if ($response === null) {
            abort(404, 'Survey not found.');
        }

        return $response;
    }
}
