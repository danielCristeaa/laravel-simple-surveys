@extends('simple-surveys::admin.layout')

@section('title', $results['title'] . ' — Simple Surveys Admin')

@section('content')
    @php
        use DanielCristea\SimpleSurveys\Enums\QuestionType;
        use DanielCristea\SimpleSurveys\Enums\SurveyStatus;

        $activeTab = $activeTab ?? 'overview';
        $range = $filters['range'] ?? 'all';
        $searchQuery = $filters['q'] ?? '';
        $localeFilter = $filters['locale'] ?? '';
        $questionFilter = $filters['question'] ?? '';
        $answerFilter = $filters['answer'] ?? '';
        $perPage = $perPage ?? 15;
        $surveyOptions = $surveyOptions ?? [];

        $questionsCount = count($results['questions']);
        $totalResponsesAllTime = $results['total_responses_count'] ?? $results['responses_count'];
        $totalTelemetryAllTime = $results['total_telemetry_count'] ?? ($results['responses_count'] + $results['dismissals_count'] + $results['opens_count'] + $results['impressions_count']);
        $appearanceEnabled = $results['registered'] && config('simple-surveys.admin.appearance_editor', true);

        $openRate = $results['impressions_count'] > 0
            ? min(100.0, round(($results['opens_count'] / $results['impressions_count']) * 100, 1))
            : null;

        $funnelBase = max(1, $results['impressions_count'], $results['opens_count'], $results['responses_count']);
        $seenPct = $results['impressions_count'] > 0 ? round(($results['impressions_count'] / $funnelBase) * 100, 1) : 0;
        $openedPct = $results['opens_count'] > 0 ? round(($results['opens_count'] / $funnelBase) * 100, 1) : 0;
        $respPct = $results['responses_count'] > 0 ? round(($results['responses_count'] / $funnelBase) * 100, 1) : 0;

        $dailyResponses = $results['daily_responses'] ?? [];
        $dailyMax = 1;
        $dailyTotal = 0;
        foreach ($dailyResponses as $pt) {
            if ($pt['count'] > $dailyMax) {
                $dailyMax = $pt['count'];
            }
            $dailyTotal += $pt['count'];
        }

        $rangeLabels = [
            'all' => 'All',
            '7d' => '7d',
            '30d' => '30d',
            '90d' => '90d',
        ];

        $matchedQuestionLabel = null;
        if ($questionFilter !== '') {
            foreach ($results['questions'] as $qItem) {
                if ($qItem['key'] === $questionFilter) {
                    $matchedQuestionLabel = $qItem['label'];
                    break;
                }
            }
        }

        $hasResponseFilters = $searchQuery !== '' || $localeFilter !== '' || ($questionFilter !== '' && $answerFilter !== '');
        $routeParams = static fn (array $params): array => array_filter(
            $params,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
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

    <div class="breadcrumb-bar">
        <a href="{{ route('simple-surveys.admin.index') }}" class="breadcrumb-link">&larr; All Surveys</a>
        @if (count($surveyOptions) > 1)
            <span class="breadcrumb-sep" aria-hidden="true">/</span>
            <select class="survey-switcher" data-survey-switcher aria-label="Switch survey">
                @foreach ($surveyOptions as $opt)
                    <option
                        value="{{ route('simple-surveys.admin.show', $routeParams(['surveyKey' => $opt['key'], 'tab' => $activeTab === 'responses' ? 'responses' : null])) }}"
                        @selected($opt['key'] === $results['key'])
                    >
                        {{ $opt['title'] }} ({{ $opt['registered'] ? $opt['status']->value : 'archived' }})
                    </option>
                @endforeach
            </select>
        @endif
    </div>

    <div class="page-header">
        <div class="page-header-main">
            <div class="page-title-row">
                <h1 class="page-title">{{ $results['title'] }}</h1>
                <span class="badge badge-{{ $results['status']->value }}">
                    <span class="badge-dot" aria-hidden="true"></span>
                    {{ $results['status']->value }}
                </span>
                @if (! $results['registered'])
                    <span class="badge badge-closed">archived</span>
                @endif
            </div>

            @if ($results['description'])
                <p class="page-subtitle">{{ $results['description'] }}</p>
            @endif

            <div class="meta-strip">
                <code>{{ $results['key'] }}</code>
                <span class="meta-pill {{ ($results['priority'] ?? 0) > 0 ? 'meta-pill-accent' : '' }}" title="Higher priority surveys are shown first when multiple surveys match">
                    Priority: {{ $results['priority'] ?? 0 }}
                </span>
                <span class="meta-pill">
                    {{ $questionsCount }} {{ $questionsCount === 1 ? 'question' : 'questions' }}
                </span>
                @if (! empty($results['starts_at']) || ! empty($results['ends_at']))
                    <span class="meta-pill">
                        Schedule:
                        {{ $results['starts_at'] ? \Carbon\Carbon::parse($results['starts_at'])->format('M j, Y') : 'Always' }}
                        &rarr;
                        {{ $results['ends_at'] ? \Carbon\Carbon::parse($results['ends_at'])->format('M j, Y') : 'No end' }}
                    </span>
                @endif
                @if (($results['dismissible'] ?? true) === false)
                    <span class="meta-pill">Non-dismissible</span>
                @endif
            </div>
        </div>

        <div class="header-actions">
            @if ($results['registered'] && $results['status'] === SurveyStatus::Active)
                <form
                    method="POST"
                    action="{{ route('simple-surveys.admin.close', $results['key']) }}"
                    class="inline-form"
                    data-confirm-title="End survey now?"
                    data-confirm-message="Are you sure you want to end this survey now? Users will no longer see it."
                    data-confirm-button="End Survey"
                    data-confirm-variant="warning"
                >
                    @csrf
                    <button type="submit" class="btn btn-warning">End Survey Now</button>
                </form>
            @elseif ($results['registered'] && $results['status'] === SurveyStatus::Closed)
                <form
                    method="POST"
                    action="{{ route('simple-surveys.admin.reopen', $results['key']) }}"
                    class="inline-form"
                    data-confirm-title="Re-open survey?"
                    data-confirm-message="Are you sure you want to re-open this survey? Eligible users will see it again."
                    data-confirm-button="Re-open Survey"
                    data-confirm-variant="success"
                >
                    @csrf
                    <button type="submit" class="btn btn-success">Re-open Survey</button>
                </form>
            @endif

            @if ($totalResponsesAllTime > 0)
                <a href="{{ route('simple-surveys.admin.export', $results['key']) }}" class="btn btn-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Export CSV
                </a>
            @endif

            @if ($totalTelemetryAllTime > 0 || $appearanceEnabled)
                <div class="dropdown">
                    <button
                        type="button"
                        class="btn btn-icon-only"
                        data-dropdown-toggle
                        aria-haspopup="menu"
                        aria-expanded="false"
                        aria-label="More survey actions"
                        title="More actions"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="1"></circle>
                            <circle cx="19" cy="12" r="1"></circle>
                            <circle cx="5" cy="12" r="1"></circle>
                        </svg>
                    </button>
                    <div class="dropdown-menu" role="menu">
                        @if ($appearanceEnabled)
                            <a href="{{ route('simple-surveys.admin.appearance.edit', $results['key']) }}" class="dropdown-item" role="menuitem">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <circle cx="13.5" cy="6.5" r=".5"></circle>
                                    <circle cx="17.5" cy="10.5" r=".5"></circle>
                                    <circle cx="8.5" cy="7.5" r=".5"></circle>
                                    <circle cx="6.5" cy="12.5" r=".5"></circle>
                                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"></path>
                                </svg>
                                Customize Appearance
                            </a>
                        @endif

                        @if ($appearanceEnabled && $totalTelemetryAllTime > 0)
                            <div class="dropdown-sep"></div>
                        @endif

                        @if ($totalTelemetryAllTime > 0)
                            <form
                                method="POST"
                                action="{{ route('simple-surveys.admin.reset', $results['key']) }}"
                                class="m-0"
                                data-confirm-title="Reset survey responses?"
                                data-confirm-message="This will permanently delete all submitted responses, answers, dismissals, open tracking, and impression tracking for this survey. Eligible users will be able to see and take it again."
                                data-confirm-button="Reset Responses"
                                data-confirm-variant="danger"
                            >
                                @csrf
                                <button type="submit" class="dropdown-item dropdown-item-danger" role="menuitem">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                    Reset Responses
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="tabs-bar">
        <nav class="tabs" aria-label="Survey sections">
            <a
                href="{{ route('simple-surveys.admin.show', $routeParams(['surveyKey' => $results['key'], 'range' => $range !== 'all' ? $range : null])) }}"
                class="tab-link {{ $activeTab === 'overview' ? 'is-active' : '' }}"
                @if ($activeTab === 'overview') aria-current="page" @endif
            >
                Results &amp; Metrics
            </a>
            <a
                href="{{ route('simple-surveys.admin.show', $routeParams(['surveyKey' => $results['key'], 'tab' => 'responses', 'range' => $range !== 'all' ? $range : null])) }}"
                class="tab-link {{ $activeTab === 'responses' ? 'is-active' : '' }}"
                @if ($activeTab === 'responses') aria-current="page" @endif
            >
                <span>Responses</span>
                <span class="tab-count">{{ number_format($results['responses_count']) }}</span>
            </a>
            @if ($appearanceEnabled)
                <a href="{{ route('simple-surveys.admin.appearance.edit', $results['key']) }}" class="tab-link">
                    Appearance
                </a>
            @endif
        </nav>

        <div class="segmented mb-6" role="group" aria-label="Date range filter">
            @foreach ($rangeLabels as $rangeKey => $rangeLabel)
                <a
                    href="{{ route('simple-surveys.admin.show', $routeParams([
                        'surveyKey' => $results['key'],
                        'tab' => $activeTab === 'responses' ? 'responses' : null,
                        'range' => $rangeKey === 'all' ? null : $rangeKey,
                        'q' => $activeTab === 'responses' && $searchQuery !== '' ? $searchQuery : null,
                        'locale' => $activeTab === 'responses' && $localeFilter !== '' ? $localeFilter : null,
                        'question' => $activeTab === 'responses' && $questionFilter !== '' ? $questionFilter : null,
                        'answer' => $activeTab === 'responses' && $answerFilter !== '' ? $answerFilter : null,
                    ])) }}"
                    class="segmented-btn {{ $range === $rangeKey ? 'is-active' : '' }}"
                >{{ $rangeLabel }}</a>
            @endforeach
        </div>
    </div>

    @if ($activeTab === 'overview')
        {{-- Compact Uniform 5-Stat Strip --}}
        <div class="stat-strip">
            <div class="stat-card">
                <div class="stat-label">Seen</div>
                <div class="stat-value-row">
                    <div class="stat-value">{{ number_format($results['impressions_count']) }}</div>
                </div>
                <div class="stat-sub">Unique impressions</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Opened</div>
                <div class="stat-value-row">
                    <div class="stat-value">{{ number_format($results['opens_count']) }}</div>
                </div>
                <div class="stat-sub">
                    {{ $openRate !== null ? $openRate . '% of seen' : 'Survey opens' }}
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Responses</div>
                <div class="stat-value-row">
                    <div class="stat-value">{{ number_format($results['responses_count']) }}</div>
                </div>
                <div class="stat-sub">
                    {{ number_format($results['abandoned_count']) }} abandoned &middot; {{ number_format($results['dismissals_count']) }} dismissed
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Response Rate</div>
                <div class="stat-value-row">
                    <div class="stat-value">{{ $results['response_rate'] !== null ? $results['response_rate'] . '%' : '—' }}</div>
                </div>
                <div class="stat-sub">Responses / Seen</div>
                <div class="stat-bar" aria-hidden="true">
                    <div class="stat-bar-fill" data-lss-bar="{{ $registerBarWidth($results['response_rate']) }}"></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Completion Rate</div>
                <div class="stat-value-row">
                    <div class="stat-value">{{ $results['completion_rate'] !== null ? $results['completion_rate'] . '%' : '—' }}</div>
                </div>
                <div class="stat-sub">Responses / Opened</div>
                <div class="stat-bar" aria-hidden="true">
                    <div class="stat-bar-fill stat-bar-fill-success" data-lss-bar="{{ $registerBarWidth($results['completion_rate']) }}"></div>
                </div>
            </div>
        </div>

        {{-- Analytics Visuals Row: Funnel + Responses Over Time SVG --}}
        <div class="analytics-row">
            <div class="card">
                <div class="panel-head">
                    <h2 class="panel-title">Conversion Funnel</h2>
                    <span class="panel-meta">
                        {{ $range === 'all' ? 'All time' : 'Last ' . $range }}
                    </span>
                </div>

                <div class="funnel-steps">
                    <div>
                        <div class="funnel-step-head">
                            <span class="funnel-step-name">Seen</span>
                            <span class="funnel-step-val">
                                <strong>{{ number_format($results['impressions_count']) }}</strong>
                                @if ($results['impressions_count'] > 0)
                                    <span>({{ $seenPct }}%)</span>
                                @endif
                            </span>
                        </div>
                        <div class="funnel-track">
                            <div class="funnel-fill funnel-fill-seen" data-lss-bar="{{ $registerBarWidth($seenPct) }}"></div>
                        </div>
                    </div>

                    <div>
                        <div class="funnel-step-head">
                            <span class="funnel-step-name">Opened</span>
                            <span class="funnel-step-val">
                                <strong>{{ number_format($results['opens_count']) }}</strong>
                                @if ($openRate !== null)
                                    <span>({{ $openRate }}%)</span>
                                @endif
                            </span>
                        </div>
                        <div class="funnel-track">
                            <div class="funnel-fill funnel-fill-opened" data-lss-bar="{{ $registerBarWidth($openedPct) }}"></div>
                        </div>
                    </div>

                    <div>
                        <div class="funnel-step-head">
                            <span class="funnel-step-name">Submitted</span>
                            <span class="funnel-step-val">
                                <strong>{{ number_format($results['responses_count']) }}</strong>
                                @if ($results['response_rate'] !== null)
                                    <span>({{ $results['response_rate'] }}%)</span>
                                @endif
                            </span>
                        </div>
                        <div class="funnel-track">
                            <div class="funnel-fill funnel-fill-responses" data-lss-bar="{{ $registerBarWidth($respPct) }}"></div>
                        </div>
                    </div>
                </div>

                <div class="funnel-dropoff">
                    <span class="dropoff-chip">
                        <span class="dropoff-dot dropoff-dot-abandoned"></span>
                        Abandoned: <strong>{{ number_format($results['abandoned_count']) }}</strong>
                    </span>
                    <span class="dropoff-chip">
                        <span class="dropoff-dot dropoff-dot-dismissed"></span>
                        Dismissals: <strong>{{ number_format($results['dismissals_count']) }}</strong>
                    </span>
                </div>
            </div>

            <div class="card">
                <div class="panel-head">
                    <h2 class="panel-title">Responses Over Time</h2>
                    <span class="trend-readout" id="lss-trend-readout">
                        {{ number_format($dailyTotal) }} {{ $dailyTotal === 1 ? 'response' : 'responses' }} in window
                    </span>
                </div>

                @if ($dailyResponses !== [])
                    @php
                        $svgW = 540;
                        $svgH = 136;
                        $padTop = 14;
                        $padBottom = 24;
                        $padLeft = 26;
                        $padRight = 8;
                        $plotW = $svgW - $padLeft - $padRight;
                        $plotH = $svgH - $padTop - $padBottom;
                        $barCount = count($dailyResponses);
                        $slotW = $plotW / max(1, $barCount);
                        $barW = max(4, min(26, $slotW * 0.64));
                        $labelStep = max(1, (int) ceil($barCount / 6));
                    @endphp
                    <div class="trend-chart-wrap">
                        <svg class="trend-svg" viewBox="0 0 {{ $svgW }} {{ $svgH }}" role="img" aria-label="Responses over time chart">
                            {{-- Horizontal gridlines --}}
                            <line class="trend-grid-line" x1="{{ $padLeft }}" y1="{{ $padTop }}" x2="{{ $svgW - $padRight }}" y2="{{ $padTop }}" />
                            <line class="trend-grid-line" x1="{{ $padLeft }}" y1="{{ $padTop + ($plotH / 2) }}" x2="{{ $svgW - $padRight }}" y2="{{ $padTop + ($plotH / 2) }}" />
                            <line class="trend-grid-line" x1="{{ $padLeft }}" y1="{{ $padTop + $plotH }}" x2="{{ $svgW - $padRight }}" y2="{{ $padTop + $plotH }}" />

                            <text class="trend-axis-label" x="{{ $padLeft - 6 }}" y="{{ $padTop + 4 }}" text-anchor="end">{{ $dailyMax }}</text>
                            <text class="trend-axis-label" x="{{ $padLeft - 6 }}" y="{{ $padTop + $plotH + 3 }}" text-anchor="end">0</text>

                            @foreach ($dailyResponses as $idx => $point)
                                @php
                                    $xCenter = $padLeft + ($idx * $slotW) + ($slotW / 2);
                                    $xBar = $xCenter - ($barW / 2);
                                    $h = $point['count'] > 0
                                        ? max(4, ($point['count'] / $dailyMax) * $plotH)
                                        : 2;
                                    $yBar = $padTop + $plotH - $h;
                                @endphp
                                <rect
                                    class="{{ $point['count'] > 0 ? 'trend-bar' : 'trend-bar-zero' }}"
                                    x="{{ round($xBar, 1) }}"
                                    y="{{ round($yBar, 1) }}"
                                    width="{{ round($barW, 1) }}"
                                    height="{{ round($h, 1) }}"
                                    rx="3"
                                    data-trend-label="{{ $point['label'] }}"
                                    data-trend-count="{{ $point['count'] }}"
                                >
                                    <title>{{ $point['label'] }}: {{ $point['count'] }} {{ $point['count'] === 1 ? 'response' : 'responses' }}</title>
                                </rect>
                                @if ($idx % $labelStep === 0 || $idx === $barCount - 1)
                                    <text class="trend-axis-label" x="{{ round($xCenter, 1) }}" y="{{ $svgH - 4 }}" text-anchor="middle">{{ $point['label'] }}</text>
                                @endif
                            @endforeach
                        </svg>
                    </div>
                @endif
            </div>
        </div>

        {{-- Respondent Locales Breakdown Strip (when locales exist) --}}
        @if (! empty($results['locales']))
            <div class="locales-strip">
                <span class="locales-strip-label">Locale</span>
                @foreach ($results['locales'] as $localeItem)
                    <a
                        href="{{ route('simple-surveys.admin.show', $routeParams([
                            'surveyKey' => $results['key'],
                            'tab' => 'responses',
                            'locale' => $localeItem['locale'],
                            'range' => $range !== 'all' ? $range : null,
                        ])) }}"
                        class="locale-chip"
                        title="Filter responses by locale {{ $localeItem['locale'] }}"
                    >
                        <code>{{ $localeItem['locale'] }}</code>
                        <strong>{{ $localeItem['count'] }}</strong>
                        <span class="muted">({{ $localeItem['percentage'] }}%)</span>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Questions Breakdown --}}
        @if ($questionsCount > 0)
            <div class="questions-toolbar">
                <h2 class="questions-heading">
                    Questions
                    <span class="tab-count">{{ $questionsCount }}</span>
                </h2>

                @if ($questionsCount >= 4)
                    <div class="search-wrap">
                        <span class="search-icon" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </span>
                        <input
                            type="search"
                            id="lss-question-filter"
                            class="search-input"
                            placeholder="Filter questions..."
                            aria-label="Filter questions"
                        >
                    </div>
                @endif
            </div>

            <div class="questions-grid" id="lss-questions-grid">
                @foreach ($results['questions'] as $qIndex => $question)
                    @php
                        $qType = $question['type'];
                        $maxChoiceCount = 0;
                        foreach ($question['breakdown'] as $bItem) {
                            if ($bItem['count'] > $maxChoiceCount) {
                                $maxChoiceCount = $bItem['count'];
                            }
                        }
                    @endphp

                    <section
                        class="question-card"
                        id="question-{{ $question['key'] }}"
                        data-question-search="{{ mb_strtolower($question['label'] . ' ' . $question['key'] . ' ' . $qType->value) }}"
                    >
                        <div class="question-head">
                            <div class="question-head-main">
                                <span class="question-index">Q{{ $qIndex + 1 }}</span>
                                <div class="min-w-0">
                                    <h3 class="question-title">{{ $question['label'] }}</h3>
                                    <div class="question-meta">
                                        <code>{{ $question['key'] }}</code>
                                        <span class="question-type-pill">{{ $qType->value }}</span>
                                    </div>
                                </div>
                            </div>
                            <span class="question-answer-count">
                                {{ number_format($question['answered_count']) }} {{ $question['answered_count'] === 1 ? 'answer' : 'answers' }}
                            </span>
                        </div>

                        <div class="question-body">
                            {{-- RATING QUESTION: Score Hero + Vertical SVG Bar Chart + Optional NPS --}}
                            @if ($qType === QuestionType::Rating)
                                @if ($question['stats'] !== null)
                                    <div class="score-hero-row">
                                        <div class="score-hero-main">
                                            <span class="score-hero-value">{{ $question['stats']['avg'] ?? '—' }}</span>
                                            @if (($question['scale_max'] ?? null) !== null)
                                                <span class="score-hero-scale">/ {{ $question['scale_max'] }} avg</span>
                                            @endif
                                        </div>
                                        <div class="stat-pills">
                                            <div class="stat-pill">Min<strong>{{ $question['stats']['min'] ?? '—' }}</strong></div>
                                            <div class="stat-pill">Max<strong>{{ $question['stats']['max'] ?? '—' }}</strong></div>
                                        </div>
                                    </div>
                                @endif

                                @if (! empty($question['breakdown']))
                                    @php
                                        $rCount = count($question['breakdown']);
                                        $rSvgW = max(280, $rCount * 46);
                                        $rSvgH = 98;
                                        $rPlotH = 60;
                                        $rTop = 16;
                                        $rSlot = $rSvgW / $rCount;
                                        $rBarW = min(32, $rSlot * 0.62);
                                        $rMax = max(1, $maxChoiceCount);
                                    @endphp
                                    <svg class="histogram-svg" viewBox="0 0 {{ $rSvgW }} {{ $rSvgH }}" role="img" aria-label="Rating distribution for {{ $question['label'] }}">
                                        @foreach ($question['breakdown'] as $bIdx => $item)
                                            @php
                                                $cx = ($bIdx * $rSlot) + ($rSlot / 2);
                                                $bx = $cx - ($rBarW / 2);
                                                $bh = $item['count'] > 0 ? max(5, ($item['count'] / $rMax) * $rPlotH) : 3;
                                                $by = $rTop + $rPlotH - $bh;
                                                $filterHref = route('simple-surveys.admin.show', $routeParams([
                                                    'surveyKey' => $results['key'],
                                                    'tab' => 'responses',
                                                    'question' => $question['key'],
                                                    'answer' => $item['value'],
                                                    'range' => $range !== 'all' ? $range : null,
                                                ]));
                                            @endphp
                                            <a href="{{ $filterHref }}" class="hist-bar-link">
                                                <title>Score {{ $item['label'] }}: {{ $item['count'] }} ({{ $item['percentage'] }}%) — Click to filter responses</title>
                                                @if ($item['count'] > 0)
                                                    <text class="hist-count" x="{{ round($cx, 1) }}" y="{{ round($by - 4, 1) }}" text-anchor="middle">{{ $item['count'] }}</text>
                                                @endif
                                                <rect
                                                    class="{{ $item['count'] > 0 ? 'hist-bar' : 'hist-bar-empty' }}"
                                                    x="{{ round($bx, 1) }}"
                                                    y="{{ round($by, 1) }}"
                                                    width="{{ round($rBarW, 1) }}"
                                                    height="{{ round($bh, 1) }}"
                                                    rx="4"
                                                />
                                                <text class="hist-label" x="{{ round($cx, 1) }}" y="{{ $rSvgH - 4 }}" text-anchor="middle">{{ $item['label'] }}</text>
                                            </a>
                                        @endforeach
                                    </svg>
                                @endif

                                @if (! empty($question['nps']))
                                    @php $nps = $question['nps']; @endphp
                                    <div class="nps-box">
                                        <div class="nps-head">
                                            <span class="muted font-semibold">Net Promoter Score (NPS)</span>
                                            <span class="nps-score-badge {{ $nps['score'] >= 0 ? 'badge-active' : 'btn-danger' }}">
                                                {{ $nps['score'] > 0 ? '+' . $nps['score'] : $nps['score'] }}
                                            </span>
                                        </div>
                                        <div class="nps-bar" aria-hidden="true">
                                            @if ($nps['detractors_pct'] > 0)
                                                <div class="nps-seg-det" data-lss-bar="{{ $registerBarWidth($nps['detractors_pct']) }}"></div>
                                            @endif
                                            @if ($nps['passives_pct'] > 0)
                                                <div class="nps-seg-pas" data-lss-bar="{{ $registerBarWidth($nps['passives_pct']) }}"></div>
                                            @endif
                                            @if ($nps['promoters_pct'] > 0)
                                                <div class="nps-seg-pro" data-lss-bar="{{ $registerBarWidth($nps['promoters_pct']) }}"></div>
                                            @endif
                                        </div>
                                        <div class="nps-legend">
                                            <span>Detractors (0–6): {{ $nps['detractors_pct'] }}%</span>
                                            <span>Passives (7–8): {{ $nps['passives_pct'] }}%</span>
                                            <span>Promoters (9–10): {{ $nps['promoters_pct'] }}%</span>
                                        </div>
                                    </div>
                                @endif

                            {{-- BOOLEAN QUESTION: Donut SVG + Split Bar --}}
                            @elseif ($qType === QuestionType::Boolean)
                                @php
                                    $yesItem = $question['breakdown'][0] ?? ['label' => 'Yes', 'value' => '1', 'count' => 0, 'percentage' => 0];
                                    $noItem = $question['breakdown'][1] ?? ['label' => 'No', 'value' => '0', 'count' => 0, 'percentage' => 0];
                                    $circ = 2 * M_PI * 24;
                                    $yesDash = ($yesItem['percentage'] / 100) * $circ;
                                @endphp
                                <div class="question-body-centered">
                                    <div class="boolean-visual">
                                        <svg class="boolean-donut" viewBox="0 0 64 64" role="img" aria-label="Yes {{ $yesItem['percentage'] }}%, No {{ $noItem['percentage'] }}%">
                                            <circle cx="32" cy="32" r="24" fill="none" stroke="var(--chart-track)" stroke-width="8" />
                                            @if ($question['answered_count'] > 0)
                                                <circle
                                                    cx="32"
                                                    cy="32"
                                                    r="24"
                                                    fill="none"
                                                    stroke="var(--chart-no)"
                                                    stroke-width="8"
                                                />
                                                <circle
                                                    cx="32"
                                                    cy="32"
                                                    r="24"
                                                    fill="none"
                                                    stroke="var(--chart-yes)"
                                                    stroke-width="8"
                                                    stroke-dasharray="{{ round($yesDash, 2) }} {{ round($circ, 2) }}"
                                                    transform="rotate(-90 32 32)"
                                                />
                                            @endif
                                            <text x="32" y="36" text-anchor="middle" fill="var(--text)" font-size="12" font-weight="700">
                                                {{ $question['answered_count'] > 0 ? round($yesItem['percentage']) . '%' : '—' }}
                                            </text>
                                        </svg>

                                        <div class="boolean-bars">
                                            <div class="split-bar" aria-hidden="true">
                                                @if ($question['answered_count'] > 0)
                                                    <div class="split-yes" data-lss-bar="{{ $registerBarWidth($yesItem['percentage']) }}"></div>
                                                    <div class="split-no" data-lss-bar="{{ $registerBarWidth($noItem['percentage']) }}"></div>
                                                @endif
                                            </div>
                                            <div class="boolean-legend">
                                                <a
                                                    href="{{ route('simple-surveys.admin.show', $routeParams(['surveyKey' => $results['key'], 'tab' => 'responses', 'question' => $question['key'], 'answer' => '1', 'range' => $range !== 'all' ? $range : null])) }}"
                                                    class="boolean-item"
                                                    title="Filter responses where Yes"
                                                >
                                                    <span class="dropoff-dot dropoff-dot-yes"></span>
                                                    <span>Yes</span>
                                                    <strong>{{ $yesItem['count'] }}</strong>
                                                    <span class="muted">({{ $yesItem['percentage'] }}%)</span>
                                                </a>
                                                <a
                                                    href="{{ route('simple-surveys.admin.show', $routeParams(['surveyKey' => $results['key'], 'tab' => 'responses', 'question' => $question['key'], 'answer' => '0', 'range' => $range !== 'all' ? $range : null])) }}"
                                                    class="boolean-item"
                                                    title="Filter responses where No"
                                                >
                                                    <span class="dropoff-dot dropoff-dot-no"></span>
                                                    <span>No</span>
                                                    <strong>{{ $noItem['count'] }}</strong>
                                                    <span class="muted">({{ $noItem['percentage'] }}%)</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            {{-- NUMBER QUESTION: Avg/Min/Max + Histogram SVG --}}
                            @elseif ($qType === QuestionType::Number)
                                @if ($question['stats'] !== null)
                                    <div class="score-hero-row">
                                        <div class="score-hero-main">
                                            <span class="score-hero-value">{{ $question['stats']['avg'] ?? '—' }}</span>
                                            <span class="score-hero-scale">average</span>
                                        </div>
                                        <div class="stat-pills">
                                            <div class="stat-pill">Min<strong>{{ $question['stats']['min'] ?? '—' }}</strong></div>
                                            <div class="stat-pill">Max<strong>{{ $question['stats']['max'] ?? '—' }}</strong></div>
                                        </div>
                                    </div>
                                @endif

                                @if (! empty($question['histogram']))
                                    @php
                                        $hBins = $question['histogram'];
                                        $hCount = count($hBins);
                                        $hSvgW = 360;
                                        $hSvgH = 98;
                                        $hPlotH = 60;
                                        $hTop = 16;
                                        $hSlot = $hSvgW / max(1, $hCount);
                                        $hBarW = min(40, $hSlot * 0.64);
                                        $hMax = 1;
                                        foreach ($hBins as $hb) {
                                            if ($hb['count'] > $hMax) {
                                                $hMax = $hb['count'];
                                            }
                                        }
                                    @endphp
                                    <svg class="histogram-svg" viewBox="0 0 {{ $hSvgW }} {{ $hSvgH }}" role="img" aria-label="Numeric distribution for {{ $question['label'] }}">
                                        @foreach ($hBins as $hIdx => $bin)
                                            @php
                                                $cx = ($hIdx * $hSlot) + ($hSlot / 2);
                                                $bx = $cx - ($hBarW / 2);
                                                $bh = $bin['count'] > 0 ? max(5, ($bin['count'] / $hMax) * $hPlotH) : 3;
                                                $by = $hTop + $hPlotH - $bh;
                                            @endphp
                                            <g>
                                                <title>{{ $bin['label'] }}: {{ $bin['count'] }} ({{ $bin['percentage'] }}%)</title>
                                                @if ($bin['count'] > 0)
                                                    <text class="hist-count" x="{{ round($cx, 1) }}" y="{{ round($by - 4, 1) }}" text-anchor="middle">{{ $bin['count'] }}</text>
                                                @endif
                                                <rect
                                                    class="{{ $bin['count'] > 0 ? 'hist-bar' : 'hist-bar-empty' }}"
                                                    x="{{ round($bx, 1) }}"
                                                    y="{{ round($by, 1) }}"
                                                    width="{{ round($hBarW, 1) }}"
                                                    height="{{ round($bh, 1) }}"
                                                    rx="4"
                                                />
                                                <text class="hist-label" x="{{ round($cx, 1) }}" y="{{ $hSvgH - 4 }}" text-anchor="middle">{{ $bin['label'] }}</text>
                                            </g>
                                        @endforeach
                                    </svg>
                                @elseif ($question['answered_count'] === 0)
                                    <div class="question-empty">No answers submitted yet.</div>
                                @endif

                            {{-- CHOICE / CHECKBOX QUESTIONS: Clickable Distribution Bars --}}
                            @elseif (! empty($question['breakdown']))
                                @foreach ($question['breakdown'] as $item)
                                    @php
                                        $isTop = $maxChoiceCount > 0 && $item['count'] === $maxChoiceCount;
                                        $filterHref = route('simple-surveys.admin.show', $routeParams([
                                            'surveyKey' => $results['key'],
                                            'tab' => 'responses',
                                            'question' => $question['key'],
                                            'answer' => $item['value'],
                                            'range' => $range !== 'all' ? $range : null,
                                        ]));
                                    @endphp
                                    <a href="{{ $filterHref }}" class="bar-row" title="Click to view responses that selected &ldquo;{{ $item['label'] }}&rdquo;">
                                        <div class="bar-meta">
                                            <span class="bar-label">{{ $item['label'] }}</span>
                                            <span class="bar-stats">
                                                <strong>{{ $item['count'] }}</strong> ({{ $item['percentage'] }}%)
                                            </span>
                                        </div>
                                        <div class="bar-track">
                                            <div class="bar-fill {{ $isTop ? '' : 'bar-fill-secondary' }}" data-lss-bar="{{ $registerBarWidth($item['percentage']) }}"></div>
                                        </div>
                                    </a>
                                @endforeach

                            {{-- TEXT / TEXTAREA QUESTIONS: Scrollable Quote List --}}
                            @elseif (! empty($question['recent_answers']))
                                @if (count($question['recent_answers']) > 3)
                                    <input
                                        type="search"
                                        class="quote-filter-input"
                                        placeholder="Search recent answers..."
                                        aria-label="Search recent answers for {{ $question['label'] }}"
                                        data-quote-filter
                                    >
                                @endif
                                <div class="quote-list" data-quote-list>
                                    @foreach ($question['recent_answers'] as $textAnswer)
                                        <div class="quote-item" data-quote-text="{{ mb_strtolower($textAnswer['value']) }}">
                                            <div>{{ $textAnswer['value'] }}</div>
                                            @if ($textAnswer['submitted_at'])
                                                <div class="quote-time">{{ $textAnswer['submitted_at'] }}</div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                @if ($question['answered_count'] > count($question['recent_answers']))
                                    <div class="quote-footer">
                                        <a href="{{ route('simple-surveys.admin.show', $routeParams(['surveyKey' => $results['key'], 'tab' => 'responses', 'range' => $range !== 'all' ? $range : null])) }}">
                                            View all {{ number_format($question['answered_count']) }} answers in Responses &rarr;
                                        </a>
                                    </div>
                                @endif

                            @else
                                <div class="question-empty">No answers submitted yet.</div>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

    @else
        {{-- TAB 2: INDIVIDUAL RESPONSES --}}
        <div class="card card-flush">
            <div class="toolbar">
                <form method="GET" action="{{ route('simple-surveys.admin.show', $results['key']) }}" class="toolbar-controls flex-1">
                    <input type="hidden" name="tab" value="responses">
                    @if ($range !== 'all')
                        <input type="hidden" name="range" value="{{ $range }}">
                    @endif
                    @if ($questionFilter !== '' && $answerFilter !== '')
                        <input type="hidden" name="question" value="{{ $questionFilter }}">
                        <input type="hidden" name="answer" value="{{ $answerFilter }}">
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
                            placeholder="Search respondent, URL, or answer..."
                            aria-label="Search responses"
                        >
                    </div>

                    @if (! empty($results['locales']))
                        <select name="locale" class="select-sm" aria-label="Filter by locale" data-auto-submit>
                            <option value="">All Locales</option>
                            @foreach ($results['locales'] as $localeItem)
                                <option value="{{ $localeItem['locale'] }}" @selected($localeFilter === $localeItem['locale'])>
                                    Locale: {{ $localeItem['locale'] }} ({{ $localeItem['count'] }})
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <select name="per_page" class="select-sm" aria-label="Rows per page" data-auto-submit>
                        <option value="15" @selected($perPage === 15)>15 / page</option>
                        <option value="25" @selected($perPage === 25)>25 / page</option>
                        <option value="50" @selected($perPage === 50)>50 / page</option>
                    </select>

                    <button type="submit" class="btn btn-sm">Filter</button>

                    @if ($hasResponseFilters)
                        <a
                            href="{{ route('simple-surveys.admin.show', $routeParams(['surveyKey' => $results['key'], 'tab' => 'responses', 'range' => $range !== 'all' ? $range : null])) }}"
                            class="btn btn-sm"
                        >Clear</a>
                    @endif
                </form>

                @if ($questionFilter !== '' && $answerFilter !== '')
                    <span class="active-filter-chip">
                        <span>{{ $matchedQuestionLabel ?? $questionFilter }} = {{ $answerFilter }}</span>
                        <a
                            href="{{ route('simple-surveys.admin.show', $routeParams([
                                'surveyKey' => $results['key'],
                                'tab' => 'responses',
                                'range' => $range !== 'all' ? $range : null,
                                'q' => $searchQuery !== '' ? $searchQuery : null,
                                'locale' => $localeFilter !== '' ? $localeFilter : null,
                            ])) }}"
                            aria-label="Remove answer filter"
                            title="Remove answer filter"
                        >&times;</a>
                    </span>
                @endif
            </div>

            @if ($results['responses'] === null || $results['responses']->isEmpty())
                <div class="empty">
                    <p class="empty-title">
                        {{ $hasResponseFilters || $range !== 'all' ? 'No matching responses found' : 'No responses recorded yet' }}
                    </p>
                    <p class="m-0">
                        {{ $hasResponseFilters || $range !== 'all' ? 'Try widening your date range or clearing active filters.' : 'Submissions will appear here as soon as respondents complete the survey.' }}
                    </p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table-responses">
                        <thead>
                            <tr>
                                <th class="sticky-col">Response</th>
                                <th>Locale</th>
                                <th>Submitted At</th>
                                @foreach ($results['questions'] as $question)
                                    <th>{{ \Illuminate\Support\Str::limit($question['label'], 36) }}</th>
                                @endforeach
                                <th class="col-actions">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results['responses'] as $response)
                                @php
                                    $meta = is_array($response->metadata) ? $response->metadata : [];
                                    $responseLocale = is_string($meta['locale'] ?? null) && trim($meta['locale']) !== ''
                                        ? trim($meta['locale'])
                                        : null;
                                    $qaPairs = [];
                                    foreach ($results['questions'] as $question) {
                                        $val = $response->answerFor($question['key']);
                                        if (is_bool($val)) {
                                            $display = $val ? 'Yes' : 'No';
                                        } elseif (is_array($val)) {
                                            $display = implode(', ', $val);
                                        } elseif ($val === null || $val === '') {
                                            $display = '—';
                                        } else {
                                            $display = (string) $val;
                                        }
                                        $qaPairs[] = [
                                            'key' => $question['key'],
                                            'label' => $question['label'],
                                            'value' => $display,
                                        ];
                                    }

                                    $drawerPayload = [
                                        'id' => $response->id,
                                        'respondent' => '#' . $response->respondent_id,
                                        'respondent_type' => class_basename((string) $response->respondent_type),
                                        'submitted_at' => $response->created_at?->toDateTimeString() ?? '—',
                                        'locale' => $responseLocale,
                                        'url' => is_string($meta['url'] ?? null) ? $meta['url'] : null,
                                        'duration_ms' => is_numeric($meta['duration_ms'] ?? null) ? (int) $meta['duration_ms'] : null,
                                        'tracking' => is_array($meta['tracking'] ?? null) ? $meta['tracking'] : [],
                                        'answers' => $qaPairs,
                                    ];
                                @endphp
                                <tr class="is-clickable" data-response-row data-response-payload='@json($drawerPayload)'>
                                    <td class="sticky-col nowrap">
                                        <div class="font-semibold">#{{ $response->id }}</div>
                                        <div class="muted text-115">Respondent #{{ $response->respondent_id }}</div>
                                    </td>
                                    <td class="nowrap">
                                        @if ($responseLocale !== null)
                                            <code>{{ $responseLocale }}</code>
                                        @else
                                            <span class="muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="nowrap tabular">
                                        {{ $response->created_at?->toDateTimeString() ?? '—' }}
                                    </td>
                                    @foreach ($qaPairs as $pair)
                                        <td>
                                            <div class="cell-truncate" title="{{ $pair['value'] }}">{{ $pair['value'] }}</div>
                                        </td>
                                    @endforeach
                                    <td class="col-actions">
                                        <button type="button" class="btn btn-sm" data-inspect-response>Inspect</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pagination-bar">
                    <span class="muted tabular text-125">
                        Showing {{ $results['responses']->firstItem() }}–{{ $results['responses']->lastItem() }} of {{ number_format($results['responses']->total()) }} responses
                    </span>

                    @if ($results['responses']->hasPages())
                        @php
                            $currentPage = $results['responses']->currentPage();
                            $lastPage = $results['responses']->lastPage();
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($lastPage, $currentPage + 2);
                        @endphp
                        <div class="pagination-links">
                            @if ($results['responses']->previousPageUrl())
                                <a href="{{ $results['responses']->previousPageUrl() }}" class="btn btn-sm">Previous</a>
                            @endif

                            @for ($p = $startPage; $p <= $endPage; $p++)
                                <a
                                    href="{{ $results['responses']->url($p) }}"
                                    class="page-num {{ $p === $currentPage ? 'is-active' : '' }}"
                                    @if ($p === $currentPage) aria-current="page" @endif
                                >{{ $p }}</a>
                            @endfor

                            @if ($results['responses']->nextPageUrl())
                                <a href="{{ $results['responses']->nextPageUrl() }}" class="btn btn-sm">Next</a>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Slide-Over Response Detail Drawer --}}
        <div class="drawer-backdrop" id="lss-response-drawer" role="dialog" aria-modal="true" aria-labelledby="lss-drawer-title" aria-hidden="true">
            <div class="drawer-panel">
                <div class="drawer-head">
                    <h2 class="drawer-title" id="lss-drawer-title">Response Details</h2>
                    <button type="button" class="btn btn-sm" id="lss-drawer-close" aria-label="Close drawer">&times; Close</button>
                </div>
                <div class="drawer-body" id="lss-drawer-body"></div>
            </div>
        </div>
    @endif

    @if ($barRules !== [])
        <style{!! $cspNonceAttr !!}>@foreach ($barRules as $barIdx => $barWidth)[data-lss-bar="{{ $barIdx }}"]{width:{{ $barWidth }}%;}@endforeach</style>
    @endif

    <script{!! $cspNonceAttr !!}>
        (function () {
            /* Trend chart hover readout */
            var trendReadout = document.getElementById('lss-trend-readout');
            if (trendReadout) {
                var defaultTrendText = trendReadout.textContent;
                document.querySelectorAll('[data-trend-label]').forEach(function (bar) {
                    bar.addEventListener('mouseenter', function () {
                        var count = Number(bar.dataset.trendCount || '0');
                        trendReadout.textContent = bar.dataset.trendLabel + ': ' + count + (count === 1 ? ' response' : ' responses');
                    });
                    bar.addEventListener('mouseleave', function () {
                        trendReadout.textContent = defaultTrendText;
                    });
                });
            }

            /* Question list client-side filter */
            var questionFilterInput = document.getElementById('lss-question-filter');
            if (questionFilterInput) {
                var questionCards = document.querySelectorAll('#lss-questions-grid .question-card');
                questionFilterInput.addEventListener('input', function () {
                    var needle = questionFilterInput.value.trim().toLowerCase();
                    questionCards.forEach(function (card) {
                        var haystack = card.dataset.questionSearch || '';
                        card.classList.toggle('is-hidden-by-filter', needle !== '' && haystack.indexOf(needle) === -1);
                    });
                });
            }

            /* Per-question text answer filter */
            document.querySelectorAll('[data-quote-filter]').forEach(function (input) {
                var card = input.closest('.question-card');
                if (!card) return;
                var items = card.querySelectorAll('.quote-item');
                input.addEventListener('input', function () {
                    var needle = input.value.trim().toLowerCase();
                    items.forEach(function (item) {
                        var text = item.dataset.quoteText || '';
                        item.classList.toggle('is-hidden', needle !== '' && text.indexOf(needle) === -1);
                    });
                });
            });

            /* Response Detail Slide-Over Drawer */
            var drawer = document.getElementById('lss-response-drawer');
            var drawerTitle = document.getElementById('lss-drawer-title');
            var drawerBody = document.getElementById('lss-drawer-body');
            var drawerClose = document.getElementById('lss-drawer-close');

            function createEl(tag, className, text) {
                var el = document.createElement(tag);
                if (className) el.className = className;
                if (text !== undefined && text !== null) el.textContent = String(text);
                return el;
            }

            function openDrawer(payload) {
                if (!drawer || !drawerBody) return;
                drawerTitle.textContent = 'Response #' + payload.id;
                drawerBody.innerHTML = '';

                var metaGrid = createEl('div', 'drawer-meta-grid');
                var metaEntries = [
                    ['Respondent', payload.respondent + (payload.respondent_type ? ' (' + payload.respondent_type + ')' : '')],
                    ['Submitted At', payload.submitted_at || '—'],
                    ['Locale', payload.locale || '—'],
                    ['Page URL', payload.url || '—']
                ];
                if (payload.duration_ms !== null && payload.duration_ms !== undefined) {
                    metaEntries.push(['Duration', (payload.duration_ms / 1000).toFixed(1) + 's']);
                }
                if (payload.tracking && typeof payload.tracking === 'object') {
                    Object.keys(payload.tracking).forEach(function (k) {
                        var v = payload.tracking[k];
                        if (v !== null && v !== undefined && v !== '') {
                            metaEntries.push([k, typeof v === 'object' ? JSON.stringify(v) : String(v)]);
                        }
                    });
                }

                metaEntries.forEach(function (entry) {
                    var box = createEl('div', 'drawer-meta-item');
                    box.appendChild(createEl('div', 'drawer-meta-label', entry[0]));
                    box.appendChild(createEl('div', 'drawer-meta-val', entry[1]));
                    metaGrid.appendChild(box);
                });
                drawerBody.appendChild(metaGrid);

                var qaTitle = createEl('h3', 'panel-title', 'Answers');
                drawerBody.appendChild(qaTitle);

                var qaList = createEl('div', 'drawer-qa-list');
                (payload.answers || []).forEach(function (item) {
                    var qa = createEl('div', 'drawer-qa-item');
                    qa.appendChild(createEl('div', 'drawer-qa-q', item.label));
                    qa.appendChild(createEl('div', 'drawer-qa-a', item.value));
                    qaList.appendChild(qa);
                });
                drawerBody.appendChild(qaList);

                drawer.classList.add('is-open');
                drawer.setAttribute('aria-hidden', 'false');
                if (drawerClose) drawerClose.focus();
            }

            function closeDrawer() {
                if (!drawer) return;
                drawer.classList.remove('is-open');
                drawer.setAttribute('aria-hidden', 'true');
            }

            if (drawer) {
                document.querySelectorAll('[data-response-row]').forEach(function (row) {
                    row.addEventListener('click', function () {
                        try {
                            var payload = JSON.parse(row.dataset.responsePayload || '{}');
                            openDrawer(payload);
                        } catch (e) {}
                    });
                });

                if (drawerClose) {
                    drawerClose.addEventListener('click', closeDrawer);
                }

                drawer.addEventListener('click', function (event) {
                    if (event.target === drawer) {
                        closeDrawer();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
                        closeDrawer();
                    }
                });
            }
        })();
    </script>
@endsection
