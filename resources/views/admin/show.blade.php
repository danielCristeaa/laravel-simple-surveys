@extends('simple-surveys::admin.layout')

@section('title', $results['title'] . ' — Simple Surveys Admin')

@section('content')
    <div style="margin-bottom: 14px;">
        <a href="{{ route('simple-surveys.admin.index') }}">&larr; All Surveys</a>
    </div>

    <div class="page-header">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <h1 class="page-title">{{ $results['title'] }}</h1>
                <span class="badge badge-{{ $results['status']->value }}">{{ $results['status']->value }}</span>
                @if (! $results['registered'])
                    <span class="badge badge-closed">archived</span>
                @endif
            </div>
            @if ($results['description'])
                <p class="page-subtitle">{{ $results['description'] }}</p>
            @endif
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            @if ($results['registered'] && $results['status'] === \DanielCristea\SimpleSurveys\Enums\SurveyStatus::Active)
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
            @elseif ($results['registered'] && $results['status'] === \DanielCristea\SimpleSurveys\Enums\SurveyStatus::Closed)
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

            @if ($results['responses_count'] > 0 || $results['dismissals_count'] > 0 || $results['opens_count'] > 0 || $results['impressions_count'] > 0)
                <form
                    method="POST"
                    action="{{ route('simple-surveys.admin.reset', $results['key']) }}"
                    class="inline-form"
                    data-confirm-title="Reset survey responses?"
                    data-confirm-message="This will permanently delete all submitted responses, answers, dismissals, open tracking, and impression tracking for this survey. Eligible users will be able to see and take it again."
                    data-confirm-button="Reset Responses"
                    data-confirm-variant="danger"
                >
                    @csrf
                    <button type="submit" class="btn btn-danger-solid">Reset Responses</button>
                </form>
            @endif

            @if ($results['responses_count'] > 0)
                <a href="{{ route('simple-surveys.admin.export', $results['key']) }}" class="btn btn-primary">
                    Export CSV
                </a>
            @endif
        </div>
    </div>

    @if ($results['registered'] && config('simple-surveys.admin.appearance_editor', true))
        <nav class="tabs" aria-label="Survey sections">
            <a href="{{ route('simple-surveys.admin.show', $results['key']) }}" class="tab-link is-active" aria-current="page">
                Results &amp; Metrics
            </a>
            <a href="{{ route('simple-surveys.admin.appearance.edit', $results['key']) }}" class="tab-link">
                Appearance
            </a>
        </nav>
    @endif

    <div class="kpi-grid">
        <div class="card">
            <div class="kpi-label">Seen</div>
            <div class="kpi-value">{{ $results['impressions_count'] }}</div>
        </div>
        <div class="card">
            <div class="kpi-label">Opened</div>
            <div class="kpi-value">{{ $results['opens_count'] }}</div>
        </div>
        <div class="card">
            <div class="kpi-label">Responses</div>
            <div class="kpi-value">{{ $results['responses_count'] }}</div>
        </div>
        <div class="card">
            <div class="kpi-label">Abandoned</div>
            <div class="kpi-value">{{ $results['abandoned_count'] }}</div>
        </div>
        <div class="card">
            <div class="kpi-label">Dismissals</div>
            <div class="kpi-value">{{ $results['dismissals_count'] }}</div>
        </div>
        <div class="card">
            <div class="kpi-label">Response Rate</div>
            <div class="kpi-value">
                {{ $results['response_rate'] !== null ? $results['response_rate'] . '%' : '—' }}
            </div>
            <div class="kpi-hint">Responses / Seen</div>
        </div>
        <div class="card">
            <div class="kpi-label">Completion Rate</div>
            <div class="kpi-value">
                {{ $results['completion_rate'] !== null ? $results['completion_rate'] . '%' : '—' }}
            </div>
            <div class="kpi-hint">Responses / Opened</div>
        </div>
        <div class="card">
            <div class="kpi-label">Questions</div>
            <div class="kpi-value">{{ count($results['questions']) }}</div>
        </div>
    </div>

    <div class="stack" style="margin-bottom: 28px;">
        @foreach ($results['questions'] as $question)
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <h2 style="margin: 0; font-size: 16px; font-weight: 600;">{{ $question['label'] }}</h2>
                        <span class="muted" style="font-size: 12px;">
                            <code>{{ $question['key'] }}</code> &middot; {{ $question['type']->value }}
                        </span>
                    </div>
                    <span class="muted" style="font-size: 12.5px;">
                        {{ $question['answered_count'] }} {{ $question['answered_count'] === 1 ? 'answer' : 'answers' }}
                    </span>
                </div>

                @if ($question['stats'] !== null)
                    <div class="stat-pills">
                        <div class="stat-pill">Average:<strong>{{ $question['stats']['avg'] ?? '—' }}</strong></div>
                        <div class="stat-pill">Min:<strong>{{ $question['stats']['min'] ?? '—' }}</strong></div>
                        <div class="stat-pill">Max:<strong>{{ $question['stats']['max'] ?? '—' }}</strong></div>
                    </div>
                @endif

                @if (! empty($question['breakdown']))
                    @foreach ($question['breakdown'] as $item)
                        <div class="bar-row">
                            <div class="bar-meta">
                                <span>{{ $item['label'] }}</span>
                                <span class="muted">{{ $item['count'] }} ({{ $item['percentage'] }}%)</span>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width: {{ min(100, $item['percentage']) }}%;"></div>
                            </div>
                        </div>
                    @endforeach
                @endif

                @if (! empty($question['recent_answers']))
                    <div class="quote-list">
                        @foreach ($question['recent_answers'] as $textAnswer)
                            <div class="quote-item">
                                <div>{{ $textAnswer['value'] }}</div>
                                @if ($textAnswer['submitted_at'])
                                    <div class="quote-time">{{ $textAnswer['submitted_at'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @elseif (empty($question['breakdown']) && $question['stats'] === null)
                    <p class="muted" style="margin: 0;">No answers submitted yet.</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="card" style="padding: 0;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border);">
            <h2 style="margin: 0; font-size: 16px; font-weight: 600;">Individual Responses</h2>
        </div>

        @if ($results['responses'] === null || $results['responses']->isEmpty())
            <div class="empty">No responses recorded for this survey yet.</div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Respondent</th>
                            <th>Submitted At</th>
                            @foreach ($results['questions'] as $question)
                                <th>{{ $question['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results['responses'] as $response)
                            <tr>
                                <td>{{ $response->id }}</td>
                                <td>
                                    <span class="muted" style="font-size: 12px;">#{{ $response->respondent_id }}</span>
                                </td>
                                <td style="white-space: nowrap;">{{ $response->created_at?->toDateTimeString() ?? '—' }}</td>
                                @foreach ($results['questions'] as $question)
                                    @php
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
                                    @endphp
                                    <td>{{ $display }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($results['responses']->hasPages())
                <div style="padding: 14px 20px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                    <span class="muted" style="font-size: 12.5px;">
                        Page {{ $results['responses']->currentPage() }} of {{ $results['responses']->lastPage() }}
                    </span>
                    <div style="display: flex; gap: 8px;">
                        @if ($results['responses']->previousPageUrl())
                            <a href="{{ $results['responses']->previousPageUrl() }}" class="btn">Previous</a>
                        @endif
                        @if ($results['responses']->nextPageUrl())
                            <a href="{{ $results['responses']->nextPageUrl() }}" class="btn">Next</a>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>
@endsection
