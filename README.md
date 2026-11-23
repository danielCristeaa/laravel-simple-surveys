# Laravel Simple Surveys

A lightweight, code-first Laravel package for running targeted in-app surveys with a drop-in bottom-right Blade widget and a protected admin results dashboard.

## Features

- **Code-first fluent API:** Define surveys, questions, and targeting rules directly in PHP (or dedicated `SurveyDefinition` classes).
- **Drop-in Blade widget:** Mount `<x-simple-surveys::widget />` once in your layout — zero CSS/JS conflicts and zero build steps required.
- **Eloquent audience targeting:** Target authenticated users with real Eloquent queries (`where`, `whereHas`, scopes) or direct user predicates.
- **8 built-in input types:** `text`, `textarea`, `number`, `select`, `radio`, `checkbox`, `rating`, and `boolean`.
- **Normalized 5-table schema:** Stores surveys, questions, responses, typed answers, and dismissals cleanly with native SQL analytics.
- **Protected Admin Results Dashboard:** View running, scheduled, closed, and archived surveys, inspect visual breakdowns, end/re-open surveys, and export CSVs.
- **Octane-safe & strictly typed:** PHP 8.2+, Laravel 12.x & 13.x, PHPStan Level 8+.

## Requirements

- PHP `8.2+`
- Laravel `12.x` or `13.x`

## Installation

Install the package via Composer:

```bash
composer require danielcristea/laravel-simple-surveys
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="simple-surveys-migrations"
php artisan migrate
```

Optionally publish the configuration file:

```bash
php artisan vendor:publish --tag="simple-surveys-config"
```

## Quick Start

### 1. Register a Survey

Register surveys in your `AppServiceProvider::boot()` (or a dedicated service provider):

```php
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Database\Eloquent\Builder;

SimpleSurveys::register(
    Survey::make('onboarding-feedback')
        ->title('Quick Onboarding Survey')
        ->banner('Got 30 seconds? Help us improve onboarding!')
        ->description('Your feedback directly shapes what we build next.')
        ->activeBetween('2026-11-01', '2026-12-01')
        ->priority(10)
        ->target(fn (Builder $query) => $query
            ->where('created_at', '<=', now()->subDays(7))
            ->whereHas('orders', fn (Builder $q) => $q->where('status', 'paid'))
        )
        ->questions([
            Question::rating('experience', 'How would you rate your experience?')
                ->scale(1, 5)
                ->required(),
            Question::select('source', 'How did you hear about us?')
                ->options(['Google', 'Friend', 'Social Media', 'Other'])
                ->required(),
            Question::radio('usage', 'How often do you use the app?')
                ->options(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly']),
            Question::checkbox('features', 'Which features do you use most?')
                ->options(['dashboard' => 'Dashboard', 'reports' => 'Reports', 'api' => 'API']),
            Question::number('team_size', 'How many people are on your team?')
                ->min(1)
                ->max(10_000),
            Question::boolean('recommend', 'Would you recommend us to a colleague?')
                ->required(),
            Question::text('role', 'What is your role?')
                ->placeholder('e.g. Developer, Founder')
                ->maxLength(100),
            Question::textarea('notes', 'Anything we can improve?')
                ->maxLength(1000),
        ])
);
```

#### Class-Based Survey Definitions

For larger applications, organize surveys into classes implementing `SurveyDefinition`:

```php
use DanielCristea\SimpleSurveys\Contracts\SurveyDefinition;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;

final class OnboardingFeedbackSurvey implements SurveyDefinition
{
    public function definition(): Survey
    {
        return Survey::make('onboarding-feedback')
            ->title('Quick Onboarding Survey')
            ->questions([
                Question::rating('score', 'Overall experience')->scale(1, 5)->required(),
            ]);
    }
}
```

Then register the class:

```php
SimpleSurveys::register(OnboardingFeedbackSurvey::class);
```

### 2. Render the Customer Widget

Drop the Blade component into your layout before `</body>`:

```blade
<x-simple-surveys::widget />
```

Optional attributes:

```blade
{{-- Render a specific survey (if eligible) in the bottom-left corner --}}
<x-simple-surveys::widget survey="onboarding-feedback" position="bottom-left" />
```

The widget renders nothing for guests or authenticated users who have no eligible surveys (0 DB queries for guests).

### 3. Sync Surveys to the Database (Optional)

Surveys automatically sync to the database on the first submission, dismissal, or admin status change. You can also sync all code-registered surveys upfront (e.g. during deployment):

```bash
php artisan simple-surveys:sync
```

## Audience Targeting

Surveys only target **authenticated users** and automatically exclude users who have already submitted or dismissed the survey.

- **Eloquent query targeting:** `->target(fn (Builder $query) => ...)` — safely nested inside `WHERE id = ? AND (...)` so `orWhere` clauses cannot leak across users.
- **Direct user predicate:** `->targetUser(fn ($user): bool => $user->isBetaTester())` — evaluated in memory with zero extra queries.
- **Scheduling & flags:** `->startsAt(...)`, `->endsAt(...)`, `->until(...)`, `->activeBetween($start, $end)`, `->draft()`, `->closed()`, `->dismissible(false)`, `->priority(int)`.

## Admin Results Dashboard

Visit `/simple-surveys/admin` (configurable via `config/simple-surveys.php`) to view:

- Overview of active, scheduled, draft, closed, and archived surveys with response counts, dismissal counts, and completion rates.
- Per-question analytics (averages/min/max for numeric & rating questions, percentage bar distributions for choice/boolean/rating questions, and recent text answers).
- Paginated individual responses and streamed **CSV export**.
- **End Survey Now** and **Re-open Survey** controls (with confirmation modals).

### Controlling Admin Access

Define the `viewSimpleSurveys` Gate in your `AppServiceProvider::boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewSimpleSurveys', function ($user): bool {
    return $user->is_admin; // Or $user->can('view-surveys')
});
```

## Events

The package dispatches two events you can listen to:

- `DanielCristea\SimpleSurveys\Events\SurveySubmitted` (`$survey`, `$response`, `$respondent`)
- `DanielCristea\SimpleSurveys\Events\SurveyDismissed` (`$survey`, `$dismissal`, `$respondent`)

## Configuration

Key options in `config/simple-surveys.php`:

- `tables.*` — Customize the 5 database table names (`simple_surveys`, `simple_survey_questions`, `simple_survey_responses`, `simple_survey_answers`, `simple_survey_dismissals`).
- `respondent_key_type` — `'id'` (default), `'uuid'`, or `'ulid'`.
- `guard` — Auth guard name (`null` uses your app's default guard).
- `routes` — Enable/disable customer submission/dismissal routes, prefix, and middleware.
- `admin` — Enable/disable admin dashboard routes, prefix, middleware, and Gate ability name.

## Testing & Quality

```bash
composer test
composer analyse
composer format:check
```

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
