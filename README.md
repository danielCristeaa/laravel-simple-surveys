# Laravel Simple Surveys

A lightweight, code-first Laravel package for running targeted in-app surveys with a drop-in bottom-right Blade widget and a protected admin results dashboard.

## Features

- **Code-first fluent API:** Define surveys, questions, and targeting rules directly in PHP (or dedicated `SurveyDefinition` classes).
- **Drop-in Blade widget:** Mount `<x-simple-surveys::widget />` once in your layout — zero CSS/JS conflicts, light/dark/auto presets, 5 screen placements (including centered modal), and zero build steps required.
- **Flexible appearance customization:** Style all surveys globally or target individual surveys in your own CSS (`php artisan vendor:publish --tag="simple-surveys-styles"`), or fine-tune any survey visually with a live preview in the Admin Dashboard **Appearance** tab.
- **Eloquent audience targeting:** Target authenticated users with real Eloquent queries (`where`, `whereHas`, scopes) or direct user predicates.
- **8 built-in input types:** `text`, `textarea`, `number`, `select`, `radio`, `checkbox`, `rating`, and `boolean`.
- **Normalized 7-table schema:** Stores surveys, questions, responses, typed answers, dismissals, opens, and impressions cleanly with native SQL analytics.
- **Protected Admin Results Dashboard:** View running, scheduled, closed, and archived surveys, inspect funnel metrics (seen, opened, answered, abandoned, dismissed, response rate, and completion rate) and visual breakdowns, end/re-open/reset surveys, and export CSVs.
- **Octane-safe & strictly typed:** PHP 8.2+, Laravel 11.x, 12.x & 13.x, PHPStan Level 8+.

## Requirements

- PHP `8.2+`
- Laravel `11.x`, `12.x`, or `13.x`

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
{{-- Render a specific survey (if eligible) in the bottom-left corner with dark mode --}}
<x-simple-surveys::widget survey="onboarding-feedback" position="bottom-left" theme="dark" />

{{-- Use centered modal placement (subtle bottom-center banner that opens into a centered modal) --}}
<x-simple-surveys::widget position="center" theme="auto" />

{{-- Enable dynamic SPA mode for Inertia.js / Livewire wire:navigate --}}
<x-simple-surveys::widget dynamic />
```

Supported `position` values: `bottom-right` (default), `bottom-left`, `top-right`, `top-left`, and `center`.
Supported `theme` values: `light` (default), `dark`, and `auto` (follows `prefers-color-scheme: dark`).

The widget renders nothing for guests or authenticated users who have no eligible surveys (0 DB queries for guests).

#### SPA / Dynamic Mode (`dynamic`)

In Inertia.js, Livewire (`wire:navigate` / `@persist`), or other SPAs where your root Blade layout does not reload across visits, pass `dynamic`:

```blade
<x-simple-surveys::widget dynamic />
```

This keeps a lightweight mount container in the DOM that automatically refreshes eligibility via `GET /simple-surveys/widget` on `inertia:navigate`, `livewire:navigated`, or when you dispatch `window.dispatchEvent(new CustomEvent('simple-surveys:refresh'))`.

For custom React/Vue/Svelte frontends that want raw JSON instead of the built-in Blade widget, query `GET /simple-surveys/eligible` (returns `200 { "survey": ... }` including resolved `appearance` presets & Dashboard overrides, or `{ "survey": null }`).

## Appearance & Styling Customization

Every built-in widget CSS rule uses zero-specificity `:where(...)` selectors and CSS custom properties (`--lss-*`), so your application's stylesheet always overrides defaults cleanly without `!important`.

**Style precedence (lowest → highest):**
1. Built-in widget defaults (`light` / `dark` / `auto` preset)
2. Global CSS overrides in your codebase (`.lss-root` or `:root`)
3. Per-survey CSS overrides in your codebase (`[data-lss-survey="your-survey-key"]`)
4. Per-survey overrides saved from the Admin Dashboard **Appearance** tab

### 1. Global & Per-Survey CSS in Your Codebase

Publish the starter override stylesheet into `resources/css/simple-surveys.css`:

```bash
php artisan vendor:publish --tag="simple-surveys-styles"
```

Then import it into your main stylesheet (e.g. `resources/css/app.css`):

```css
@import './simple-surveys.css';
```

Edit `resources/css/simple-surveys.css` to style all surveys globally or customize individual surveys using `data-lss-survey`:

```css
/* Global overrides (applies to every survey) */
.lss-root {
    --lss-accent: #10b981;
    --lss-radius: 14px;
    --lss-card-width: 420px;
}

/* Per-survey overrides */
[data-lss-survey="onboarding-feedback"] {
    --lss-accent: #0284c7;
    --lss-bg: #f8fafc;
    --lss-card-width: 460px;
}

[data-lss-survey="onboarding-feedback"] .lss-btn-primary {
    border-radius: 9999px;
}
```

For full markup customization, you can also publish the Blade views:

```bash
php artisan vendor:publish --tag="simple-surveys-views"
```

### 2. Per-Survey Visual Styling in the Admin Dashboard

Open any registered survey in `/simple-surveys/admin/{surveyKey}` and click the **Appearance** tab to visually customize:

- **Presets & Placement:** Theme (`light`, `dark`, `auto`), placement (`bottom-right`, `bottom-left`, `top-right`, `top-left`, `center`), and quick accent palettes.
- **Colors:** Button/accent, hover, button text, background, surface, border, primary text, and muted text.
- **Dimensions & Shape:** Card width, card max height, banner max width, and card/button/input border radii.
- **Typography:** Base font size and font family.

Changes update a **live interactive preview** (both expanded card and collapsed banner) before saving. Any field left blank continues to inherit from your codebase CSS, and clicking **Reset to Defaults** clears all Dashboard overrides for that survey.

To enforce strictly CSS-only styling across your team, disable the Dashboard editor in `config/simple-surveys.php`:

```php
'admin' => [
    'appearance_editor' => false,
],
```

### 3. Sync Surveys to the Database (Optional)

Surveys automatically sync to the database on the first submission, dismissal, or admin status change. You can also sync all code-registered surveys upfront (e.g. during deployment):

```bash
php artisan simple-surveys:sync
```

## Audience Targeting

Surveys only target **authenticated users** and automatically exclude users who have already submitted or dismissed the survey.

- **Eloquent query targeting:** `->target(fn (Builder $query) => ...)` — safely nested inside `WHERE id = ? AND (...)` so `orWhere` clauses cannot leak across users.
- **Direct user predicate:** `->targetUser(fn ($user): bool => $user->isBetaTester())` — evaluated in memory with zero extra queries.
- **URL & route targeting:** `->onRoutes(['dashboard', 'billing.*'])`, `->exceptRoutes('admin.*')`, `->onPaths('/app/*')`, and `->exceptPaths('/checkout/*')` restrict which pages display the survey (works for both Blade and SPA `dynamic` mode).
- **Scheduling & flags:** `->startsAt(...)`, `->endsAt(...)`, `->until(...)`, `->activeBetween($start, $end)`, `->draft()`, `->closed()`, `->dismissible(false)`, `->priority(int)`.
- **Re-prompt after dismissal:** `->repromptAfterDays(14)` (also `->repromptAfterHours(...)`, `->repromptAfterMinutes(...)`, or `->repromptAfter(CarbonInterval::weeks(2))`) re-shows a dismissed survey once the cooldown expires (switches the dismiss button label to "Remind me later"). Completed responses never re-prompt.

## Admin Results Dashboard

Visit `/simple-surveys/admin` (configurable via `config/simple-surveys.php`) to view:

- Overview of active, scheduled, draft, closed, and archived surveys with seen (unique impressions), opened, abandoned, response, and dismissal counts plus **Response Rate** (`responses / seen`) and **Completion Rate** (`responses / opens`).
- Per-question analytics (averages/min/max for numeric & rating questions, percentage bar distributions for choice/boolean/rating questions, and recent text answers).
- Paginated individual responses and streamed **CSV export**.
- **End Survey Now**, **Re-open Survey**, and **Reset Responses** controls (with confirmation modals).
- **Appearance** tab per survey with a live card/banner preview for customizing theme, placement, colors, sizing, radii, and typography (toggleable via `admin.appearance_editor`).

### Controlling Admin Access

Define the `viewSimpleSurveys` Gate in your `AppServiceProvider::boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewSimpleSurveys', function ($user): bool {
    return $user->is_admin; // Or $user->can('view-surveys')
});
```

## Events

The package dispatches four events you can listen to:

- `DanielCristea\SimpleSurveys\Events\SurveySeen` (`$survey`, `$impression`, `$respondent`) — dispatched on a respondent's first recorded impression
- `DanielCristea\SimpleSurveys\Events\SurveyOpened` (`$survey`, `$open`, `$respondent`)
- `DanielCristea\SimpleSurveys\Events\SurveySubmitted` (`$survey`, `$response`, `$respondent`)
- `DanielCristea\SimpleSurveys\Events\SurveyDismissed` (`$survey`, `$dismissal`, `$respondent`)

## Configuration

Key options in `config/simple-surveys.php`:

- `tables.*` — Customize the 7 database table names (`simple_surveys`, `simple_survey_questions`, `simple_survey_responses`, `simple_survey_answers`, `simple_survey_dismissals`, `simple_survey_opens`, `simple_survey_impressions`).
- `respondent_key_type` — `'id'` (default), `'uuid'`, or `'ulid'`.
- `guard` — Auth guard name (`null` uses your app's default guard).
- `routes` — Enable/disable customer submission/dismissal routes, prefix, and middleware.
- `tracking.impressions` — Enable/disable client-side impression ("Seen") tracking (`true` by default).
- `admin` — Enable/disable admin dashboard routes, prefix, middleware, Gate ability name, and `appearance_editor` toggle.

## Testing & Quality

```bash
composer test
composer analyse
composer format:check
```

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
