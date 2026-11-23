<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers\Admin;

use DanielCristea\SimpleSurveys\Services\SurveyResultsAggregator;
use Illuminate\Contracts\View\View;

final class SurveyResultsController
{
    public function __construct(
        private readonly SurveyResultsAggregator $aggregator,
    ) {}

    public function index(): View
    {
        $summaries = $this->aggregator->summaries();

        /** @var view-string $view */
        $view = 'simple-surveys::admin.index';

        return view($view, [
            'summaries' => $summaries,
        ]);
    }

    public function show(string $surveyKey): View
    {
        $results = $this->aggregator->forSurvey($surveyKey);

        if ($results === null) {
            abort(404, 'Survey not found.');
        }

        /** @var view-string $view */
        $view = 'simple-surveys::admin.show';

        return view($view, [
            'results' => $results,
        ]);
    }
}
