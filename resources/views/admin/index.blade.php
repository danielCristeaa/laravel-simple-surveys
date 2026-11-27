@extends('simple-surveys::admin.layout')

@section('title', 'Surveys — Simple Surveys Admin')

@section('content')
    @php
        use DanielCristea\SimpleSurveys\Enums\SurveyStatus;

        $allList = $allSummaries ?? $summaries;
        $totalSurveys = $allList->count();
        $activeSurveys = $allList->filter(fn (array $s): bool => $s['registered'] && $s['status'] === SurveyStatus::Active)->count();
        $totalImpressions = $allList->sum('impressions_count');
        $totalOpens = $allList->sum('opens_count');
        $totalResponses = $allList->sum('responses_count');
        $totalAbandoned = $allList->sum('abandoned_count');
        $totalDismissals = $allList->sum('dismissals_count');

        $overallResponseRate = $totalImpressions > 0 ? min(100.0, round(($totalResponses / $totalImpressions) * 100, 1)) : null;
        $overallCompletionRate = $totalOpens > 0 ? min(100.0, round(($totalResponses / $totalOpens) * 100, 1)) : null;

        $avgDurationMs = $completionTime['avg_ms'] ?? null;
        $timedResponsesCount = $completionTime['count'] ?? 0;

        if ($avgDurationMs === null) {
            $formattedAvgTime = '—';
        } elseif ($avgDurationMs < 1000) {
            $formattedAvgTime = $avgDurationMs . 'ms';
        } elseif ($avgDurationMs < 60000) {
            $seconds = round($avgDurationMs / 1000, 1);
            $formattedAvgTime = rtrim(rtrim(number_format($seconds, 1, '.', ''), '0'), '.') . 's';
        } else {
            $totalSeconds = (int) round($avgDurationMs / 1000);
            $minutes = intdiv($totalSeconds, 60);
            $remSeconds = $totalSeconds % 60;
            $formattedAvgTime = $remSeconds > 0 ? "{$minutes}m {$remSeconds}s" : "{$minutes}m";
        }

        $activeStatus = $filters['status'] ?? 'all';
        $searchQuery = $filters['q'] ?? '';
        $activeSort = $filters['sort'] ?? 'priority';
        $activeDir = $filters['dir'] ?? 'desc';
        $hasActiveFilters = $searchQuery !== '' || $activeStatus !== 'all';

        $statusLabels = [
            'all' => 'All',
            'active' => 'Active',
            'scheduled' => 'Scheduled',
            'draft' => 'Draft',
            'closed' => 'Closed',
            'archived' => 'Archived',
        ];
        $resolvedCspNonce = array_key_exists('cspNonce', get_defined_vars())
            ? (is_string($cspNonce) && trim($cspNonce) !== '' ? trim($cspNonce) : null)
            : app(\DanielCristea\SimpleSurveys\Services\SurveyCspNonceResolver::class)->resolve();
        $cspNonceAttr = $resolvedCspNonce !== null ? ' nonce="'.e($resolvedCspNonce).'"' : '';
        $barRules = [];
        $registerBarWidth = static function (int|float|null $pct) use (&$barRules): int {
            $clamped = max(0.0, min(100.0, (float) ($pct ?? 0)));
            $formatted = rtrim(rtrim(number_format($clamped, 2, '.', ''), '0'), '.');
            $idx = count($barRules) + 1;
            $barRules[$idx] = $formatted;

            return $idx;
        };
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Survey Results</h1>
            <p class="page-subtitle">Monitor active, scheduled, and completed surveys across your application.</p>
        </div>
    </div>

    <div class="stat-strip stat-strip-4">
        <div class="stat-card">
            <div class="stat-label">Surveys</div>
            <div class="stat-value-row">
                <div class="stat-value">{{ number_format($totalSurveys) }}</div>
            </div>
            <div class="stat-sub">
                {{ $activeSurveys }} active &middot; {{ max(0, $totalSurveys - $activeSurveys) }} other
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Avg Response Rate</div>
            <div class="stat-value-row">
                <div class="stat-value">{{ $overallResponseRate !== null ? $overallResponseRate . '%' : '—' }}</div>
            </div>
            <div class="stat-sub">Responses / Seen</div>
            <div class="stat-bar" aria-hidden="true">
                <div class="stat-bar-fill" data-lss-bar="{{ $registerBarWidth($overallResponseRate) }}"></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Avg Completion Rate</div>
            <div class="stat-value-row">
                <div class="stat-value">{{ $overallCompletionRate !== null ? $overallCompletionRate . '%' : '—' }}</div>
            </div>
            <div class="stat-sub">Responses / Opened</div>
            <div class="stat-bar" aria-hidden="true">
                <div class="stat-bar-fill stat-bar-fill-success" data-lss-bar="{{ $registerBarWidth($overallCompletionRate) }}"></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Avg Completion Time</div>
            <div class="stat-value-row">
                <div class="stat-value">{{ $formattedAvgTime }}</div>
            </div>
            <div class="stat-sub">
                @if ($timedResponsesCount > 0)
                    Across {{ number_format($timedResponsesCount) }} {{ $timedResponsesCount === 1 ? 'response' : 'responses' }}
                @else
                    No timing data yet
                @endif
            </div>
        </div>
    </div>

    <div class="card card-flush">
        @if ($allList->isNotEmpty())
            <div class="toolbar">
                <div class="filter-pills" role="navigation" aria-label="Filter surveys by status">
                    @foreach ($statusLabels as $statusKey => $label)
                        @php
                            $count = $statusCounts[$statusKey] ?? 0;
                            $shouldShow = $statusKey === 'all' || $statusKey === 'active' || $count > 0 || $activeStatus === $statusKey;
                        @endphp
                        @if ($shouldShow)
                            <a
                                href="{{ route('simple-surveys.admin.index', array_filter([
                                    'status' => $statusKey === 'all' ? null : $statusKey,
                                    'q' => $searchQuery !== '' ? $searchQuery : null,
                                    'sort' => $activeSort !== 'priority' ? $activeSort : null,
                                    'dir' => $activeSort !== 'priority' ? $activeDir : null,
                                ], static fn (mixed $value): bool => $value !== null && $value !== '')) }}"
                                class="filter-pill {{ $activeStatus === $statusKey ? 'is-active' : '' }}"
                            >
                                <span>{{ $label }}</span>
                                <span class="filter-pill-count">{{ $count }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>

                <form method="GET" action="{{ route('simple-surveys.admin.index') }}" class="toolbar-controls" id="lss-index-filter-form">
                    @if ($activeStatus !== 'all')
                        <input type="hidden" name="status" value="{{ $activeStatus }}">
                    @endif

                    <div class="search-wrap">
                        <span class="search-icon" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </span>
                        <input
                            type="search"
                            name="q"
                            value="{{ $searchQuery }}"
                            class="search-input"
                            placeholder="Search surveys by title or key..."
                            aria-label="Search surveys"
                        >
                    </div>

                    <select name="sort" class="select-sm" aria-label="Sort surveys" data-auto-submit>
                        <option value="priority" @selected($activeSort === 'priority')>Sort: Priority</option>
                        <option value="responses" @selected($activeSort === 'responses')>Sort: Most Responses</option>
                        <option value="seen" @selected($activeSort === 'seen')>Sort: Most Seen</option>
                        <option value="response_rate" @selected($activeSort === 'response_rate')>Sort: Response Rate</option>
                        <option value="completion_rate" @selected($activeSort === 'completion_rate')>Sort: Completion Rate</option>
                        <option value="title" @selected($activeSort === 'title')>Sort: Title (A–Z)</option>
                    </select>

                    <button type="submit" class="btn btn-sm">Apply</button>

                    @if ($hasActiveFilters)
                        <a href="{{ route('simple-surveys.admin.index') }}" class="btn btn-sm">Clear</a>
                    @endif
                </form>
            </div>
        @endif

        @if ($allList->isEmpty())
            <div class="empty">
                <p class="empty-title">No surveys found yet</p>
                <p class="m-0">Register a survey in your application using <code>SimpleSurveys::register(...)</code>.</p>
            </div>
        @elseif ($summaries->isEmpty())
            <div class="empty">
                <p class="empty-title">No surveys match your current filters</p>
                <p class="mb-12">Try adjusting your search query or status filter.</p>
                <a href="{{ route('simple-surveys.admin.index') }}" class="btn btn-sm">Clear filters</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="table-surveys">
                    <thead>
                        <tr>
                            <th>Survey</th>
                            <th>Status</th>
                            <th class="col-center" title="Higher priority surveys are displayed first when multiple surveys match">Priority</th>
                            <th class="col-num">Seen</th>
                            <th class="col-num">Opened</th>
                            <th class="col-num">Responses</th>
                            <th class="col-num" title="Responses / Seen">Response Rate</th>
                            <th class="col-num" title="Responses / Opened">Completion Rate</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summaries as $survey)
                            <tr>
                                <td>
                                    <a href="{{ route('simple-surveys.admin.show', $survey['key']) }}" class="survey-title-link">
                                        {{ $survey['title'] }}
                                    </a>
                                    <div class="muted survey-sub-meta">
                                        <code>{{ $survey['key'] }}</code>
                                        <span>&middot;</span>
                                        <span>{{ $survey['questions_count'] }} {{ $survey['questions_count'] === 1 ? 'question' : 'questions' }}</span>
                                    </div>
                                </td>
                                <td class="nowrap">
                                    <span class="badge badge-{{ $survey['status']->value }}">
                                        <span class="badge-dot" aria-hidden="true"></span>
                                        {{ $survey['status']->value }}
                                    </span>
                                    @if (! $survey['registered'])
                                        <span class="badge badge-closed">archived</span>
                                    @endif
                                </td>
                                <td class="col-center">
                                    <span
                                        class="priority-badge {{ $survey['priority'] > 0 ? 'is-elevated' : '' }}"
                                        title="Priority {{ $survey['priority'] }} (higher displays first)"
                                    >{{ $survey['priority'] }}</span>
                                </td>
                                <td class="col-num">{{ number_format($survey['impressions_count']) }}</td>
                                <td class="col-num">{{ number_format($survey['opens_count']) }}</td>
                                <td class="col-num">
                                    <strong>{{ number_format($survey['responses_count']) }}</strong>
                                    @if ($survey['abandoned_count'] > 0 || $survey['dismissals_count'] > 0)
                                        <div class="muted text-11">
                                            {{ $survey['abandoned_count'] }} abnd &middot; {{ $survey['dismissals_count'] }} dism
                                        </div>
                                    @endif
                                </td>
                                <td class="col-num">
                                    @if ($survey['response_rate'] !== null)
                                        <div class="rate-cell">
                                            <span>{{ $survey['response_rate'] }}%</span>
                                            <div class="mini-bar-track" aria-hidden="true">
                                                <div class="mini-bar-fill" data-lss-bar="{{ $registerBarWidth($survey['response_rate']) }}"></div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="muted">&mdash;</span>
                                    @endif
                                </td>
                                <td class="col-num">
                                    @if ($survey['completion_rate'] !== null)
                                        <div class="rate-cell">
                                            <span>{{ $survey['completion_rate'] }}%</span>
                                            <div class="mini-bar-track" aria-hidden="true">
                                                <div class="mini-bar-fill mini-bar-fill-success" data-lss-bar="{{ $registerBarWidth($survey['completion_rate']) }}"></div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="muted">&mdash;</span>
                                    @endif
                                </td>
                                <td class="col-actions">
                                    <div class="inline-actions">
                                        <a href="{{ route('simple-surveys.admin.show', $survey['key']) }}" class="btn btn-sm">
                                            View
                                        </a>

                                        @if ($survey['registered'] && $survey['status'] === SurveyStatus::Active)
                                            <form
                                                method="POST"
                                                action="{{ route('simple-surveys.admin.close', $survey['key']) }}"
                                                class="inline-form"
                                                data-confirm-title="End survey now?"
                                                data-confirm-message="Are you sure you want to end this survey now? Users will no longer see it."
                                                data-confirm-button="End Survey"
                                                data-confirm-variant="warning"
                                            >
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-warning">End Now</button>
                                            </form>
                                        @elseif ($survey['registered'] && $survey['status'] === SurveyStatus::Closed)
                                            <form
                                                method="POST"
                                                action="{{ route('simple-surveys.admin.reopen', $survey['key']) }}"
                                                class="inline-form"
                                                data-confirm-title="Re-open survey?"
                                                data-confirm-message="Are you sure you want to re-open this survey? Eligible users will see it again."
                                                data-confirm-button="Re-open Survey"
                                                data-confirm-variant="success"
                                            >
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">Re-open</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($barRules !== [])
        <style{!! $cspNonceAttr !!}>@foreach ($barRules as $barIdx => $barWidth)[data-lss-bar="{{ $barIdx }}"]{width:{{ $barWidth }}%;}@endforeach</style>
    @endif
@endsection
