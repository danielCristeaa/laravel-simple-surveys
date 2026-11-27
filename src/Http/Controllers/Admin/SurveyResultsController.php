<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Http\Controllers\Admin;

use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Services\SurveyResultsAggregator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class SurveyResultsController
{
    public function __construct(
        private readonly SurveyResultsAggregator $aggregator,
    ) {}

    public function index(Request $request): View
    {
        $allSummaries = $this->aggregator->summaries();

        $search = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('status', 'all'));
        $allowedStatuses = ['all', 'active', 'scheduled', 'draft', 'closed', 'archived'];
        if (! in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = 'all';
        }

        $sort = trim((string) $request->query('sort', 'priority'));
        $allowedSorts = ['priority', 'title', 'responses', 'seen', 'response_rate', 'completion_rate'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'priority';
        }

        $defaultDir = $sort === 'title' ? 'asc' : 'desc';
        $dir = trim((string) $request->query('dir', $defaultDir));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = $defaultDir;
        }

        $statusCounts = [
            'all' => $allSummaries->count(),
            'active' => $allSummaries->filter(static fn (array $s): bool => $s['registered'] && $s['status'] === SurveyStatus::Active)->count(),
            'scheduled' => $allSummaries->filter(static fn (array $s): bool => $s['registered'] && $s['status'] === SurveyStatus::Scheduled)->count(),
            'draft' => $allSummaries->filter(static fn (array $s): bool => $s['registered'] && $s['status'] === SurveyStatus::Draft)->count(),
            'closed' => $allSummaries->filter(static fn (array $s): bool => $s['registered'] && $s['status'] === SurveyStatus::Closed)->count(),
            'archived' => $allSummaries->filter(static fn (array $s): bool => ! $s['registered'])->count(),
        ];

        $filtered = $allSummaries->filter(static function (array $survey) use ($search, $statusFilter): bool {
            if ($statusFilter === 'archived' && $survey['registered']) {
                return false;
            }

            if (in_array($statusFilter, ['active', 'scheduled', 'draft', 'closed'], true)) {
                if (! $survey['registered'] || $survey['status']->value !== $statusFilter) {
                    return false;
                }
            }

            if ($search !== '') {
                $needle = mb_strtolower($search);
                $haystack = mb_strtolower(
                    $survey['title'].' '.$survey['key'].' '.($survey['description'] ?? '')
                );

                if (! str_contains($haystack, $needle)) {
                    return false;
                }
            }

            return true;
        });

        $sorted = $filtered->sortBy(
            static fn (array $survey): mixed => match ($sort) {
                'title' => mb_strtolower($survey['title']),
                'responses' => $survey['responses_count'],
                'seen' => $survey['impressions_count'],
                'response_rate' => $survey['response_rate'] ?? -1.0,
                'completion_rate' => $survey['completion_rate'] ?? -1.0,
                default => $survey['priority'],
            },
            SORT_REGULAR,
            $dir === 'desc',
        )->values();

        /** @var view-string $view */
        $view = 'simple-surveys::admin.index';

        return view($view, [
            'summaries' => $sorted,
            'allSummaries' => $allSummaries,
            'statusCounts' => $statusCounts,
            'completionTime' => $this->aggregator->averageCompletionTime(),
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    public function show(Request $request, string $surveyKey): View
    {
        $tab = trim((string) $request->query('tab', 'overview'));
        if (! in_array($tab, ['overview', 'responses'], true)) {
            $tab = 'overview';
        }

        $range = trim((string) $request->query('range', 'all'));
        if (! in_array($range, ['all', '7d', '30d', '90d'], true)) {
            $range = 'all';
        }

        $perPageRaw = (int) $request->query('per_page', 15);
        $perPage = in_array($perPageRaw, [15, 25, 50], true) ? $perPageRaw : 15;

        $filters = [
            'range' => $range,
            'q' => trim((string) $request->query('q', '')),
            'locale' => trim((string) $request->query('locale', '')),
            'question' => trim((string) $request->query('question', '')),
            'answer' => trim((string) $request->query('answer', '')),
        ];

        $results = $this->aggregator->forSurvey($surveyKey, $perPage, $filters);

        if ($results === null) {
            abort(404, 'Survey not found.');
        }

        if ($results['responses'] !== null) {
            $results['responses']->withQueryString();
        }

        /** @var view-string $view */
        $view = 'simple-surveys::admin.show';

        return view($view, [
            'results' => $results,
            'activeTab' => $tab,
            'perPage' => $perPage,
            'filters' => $filters,
            'surveyOptions' => $this->aggregator->surveyOptions(),
        ]);
    }
}
