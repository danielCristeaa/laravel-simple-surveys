@extends('simple-surveys::admin.layout')

@section('title', 'Surveys — Simple Surveys Admin')

@section('content')
    @php
        use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
        $totalSurveys = $summaries->count();
        $activeSurveys = $summaries->filter(fn (array $s): bool => $s['status'] === SurveyStatus::Active)->count();
        $totalResponses = $summaries->sum('responses_count');
        $totalDismissals = $summaries->sum('dismissals_count');
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Survey Results</h1>
            <p class="page-subtitle">Overview of running, scheduled, and past surveys.</p>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="card">
            <div class="kpi-label">Total Surveys</div>
            <div class="kpi-value">{{ $totalSurveys }}</div>
        </div>
        <div class="card">
            <div class="kpi-label">Active Now</div>
            <div class="kpi-value">{{ $activeSurveys }}</div>
        </div>
        <div class="card">
            <div class="kpi-label">Total Responses</div>
            <div class="kpi-value">{{ $totalResponses }}</div>
        </div>
        <div class="card">
            <div class="kpi-label">Total Dismissals</div>
            <div class="kpi-value">{{ $totalDismissals }}</div>
        </div>
    </div>

    <div class="card" style="padding: 0;">
        @if ($summaries->isEmpty())
            <div class="empty">
                No surveys found yet. Register a survey in your application using <code>SimpleSurveys::register(...)</code>.
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Survey</th>
                            <th>Status</th>
                            <th>Questions</th>
                            <th>Responses</th>
                            <th>Dismissals</th>
                            <th>Completion Rate</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summaries as $survey)
                            <tr>
                                <td>
                                    <a href="{{ route('simple-surveys.admin.show', $survey['key']) }}" style="font-weight: 600;">
                                        {{ $survey['title'] }}
                                    </a>
                                    <div class="muted" style="font-size: 12px;"><code>{{ $survey['key'] }}</code></div>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $survey['status']->value }}">
                                        {{ $survey['status']->value }}
                                    </span>
                                    @if (! $survey['registered'])
                                        <span class="badge badge-closed">archived</span>
                                    @endif
                                </td>
                                <td>{{ $survey['questions_count'] }}</td>
                                <td><strong>{{ $survey['responses_count'] }}</strong></td>
                                <td>{{ $survey['dismissals_count'] }}</td>
                                <td>
                                    @if ($survey['completion_rate'] !== null)
                                        {{ $survey['completion_rate'] }}%
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td style="white-space: nowrap;">
                                    <a href="{{ route('simple-surveys.admin.show', $survey['key']) }}" class="btn">
                                        View Results
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
