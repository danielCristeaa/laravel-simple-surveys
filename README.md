# Laravel Simple Surveys

A lightweight, code-first Laravel package for running targeted in-app surveys with a drop-in bottom-right Blade widget and a protected admin results dashboard.

## Features

- **Code-first fluent API:** Define surveys, questions, and targeting rules directly in PHP (or dedicated `SurveyDefinition` classes).
- **Drop-in Blade widget:** Mount `<x-simple-surveys::widget />` once in your layout — zero CSS/JS conflicts, light/dark/auto presets, 5 screen placements (including centered modal), and zero build steps required.
- **Flexible appearance customization:** Style all surveys globally or target individual surveys in your own CSS (`php artisan vendor:publish --tag="simple-surveys-styles"`), or fine-tune any survey visually with a live preview in the Admin Dashboard **Appearance** tab.
- **Eloquent audience targeting:** Target authenticated users with real Eloquent queries (`where`, `whereHas`, scopes) or direct user predicates.
- **8 built-in input types & conditional follow-ups:** `text`, `textarea`, `number`, `select`, `radio`, `checkbox`, `rating`, and `boolean`, with live client-side and server-validated conditional visibility (`->when(...)`).
- **Multi-language & localization ready:** Publishable widget UI translations (`simple-surveys-translations`), convention-based `lang/{locale}/simple-surveys.php` survey translations with a scaffolding Artisan command (`simple-surveys:translations`), and automatic respondent `locale` recording.
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

#### Conditional Follow-Up Questions

Show or hide follow-up questions dynamically based on earlier answers using `->when(...)` (and helpers `whenIn()`, `whenNotIn()`, `whenContains()`, `whenNotContains()`):

```php
Survey::make('csat-survey')
    ->title('Quick Feedback')
    ->questions([
        Question::rating('experience', 'How would you rate your experience?')
            ->scale(1, 5)
            ->required(),

        // Only shown (and required) when experience <= 3
        Question::textarea('low_score_reason', 'What could we have done better?')
            ->when('experience', '<=', 3)
            ->required(),

        Question::radio('source', 'How did you hear about us?')
            ->options(['google' => 'Google', 'friend' => 'Friend', 'other' => 'Other']),

        // 2-argument shorthand for equality ('=')
        Question::text('other_source', 'Please specify')
            ->when('source', 'other')
            ->required(),
    ]);
```

Supported operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not_in`, `contains`, and `not_contains`.

- Chaining multiple `->when(...)` calls combines them with logical **AND**.
- Conditional questions stay hidden until the referenced question is answered and matches; chained dependencies (e.g. `A → B → C`) cascade automatically.
- Server-side validation skips hidden questions (even if marked `->required()`) and discards stale answers if a respondent changes an earlier answer before submitting.
- The `GET /simple-surveys/eligible` endpoint includes each question's `conditions` array for custom SPA frontends.

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

{{-- Customize or hide the banner icon (preset, "none", or public/ .png/.svg path) --}}
<x-simple-surveys::widget icon="sparkles" />
<x-simple-surveys::widget icon="images/survey-icon.svg" />
<x-simple-surveys::widget icon="none" />

{{-- Enable dynamic SPA mode for Inertia.js / Livewire wire:navigate --}}
<x-simple-surveys::widget dynamic />
```

Supported `position` values: `bottom-right` (default), `bottom-left`, `top-right`, `top-left`, and `center`.
Supported `theme` values: `light` (default), `dark`, and `auto` (follows `prefers-color-scheme: dark`).
Supported `icon` values: built-in presets (`chat` default, `star`, `sparkles`, `megaphone`, `clipboard`, `heart`, `lightbulb`, `thumbs-up`, `bell`, `help`), `none` (hides the icon), or a relative path inside `public/` ending in `.png` or `.svg` (resolved via `asset()`). You can also set a global default in `config/simple-surveys.php` (`widget.icon`), per survey via `Survey::make(...)->icon(...)` / `->withoutIcon()`, or per survey in the Admin Dashboard **Appearance** tab.

The widget renders nothing for guests or authenticated users who have no eligible surveys (0 DB queries for guests).

#### SPA / Dynamic Mode (`dynamic`)

In Inertia.js, Livewire (`wire:navigate` / `@persist`), or other SPAs where your root Blade layout does not reload across visits, pass `dynamic`:

```blade
<x-simple-surveys::widget dynamic />
```

This keeps a lightweight mount container in the DOM that automatically refreshes eligibility via `GET /simple-surveys/widget` on `inertia:navigate`, `livewire:navigated`, or when you dispatch `window.dispatchEvent(new CustomEvent('simple-surveys:refresh'))`.

For custom React/Vue/Svelte frontends that want raw JSON instead of the built-in Blade widget, see [Headless / API-Only Laravel + React/Vue Setup](#headless--api-only-laravel--reactvue-setup) below.

#### Headless / API-Only Laravel + React/Vue Setup

If your Laravel backend serves a separate SPA (React, Vue, Next.js, Nuxt, etc.) over Sanctum, Passport, or another API guard, you can use the built-in JSON endpoints without rendering the Blade widget.

##### 1. Configure Guard, Prefix & Middleware

Publish and update `config/simple-surveys.php`:

```php
'guard' => 'sanctum', // Required for stateless Bearer tokens (e.g. 'sanctum', 'api', 'passport')

'routes' => [
    'enabled' => true,
    'prefix' => 'api/simple-surveys',
    'middleware' => ['api', 'auth:sanctum'],
],
```

> **Important (Bearer Tokens):** `GET /{prefix}/eligible` intentionally omits `auth:*` middleware so unauthenticated visitors receive `200 {"survey": null}` instead of `401`. Because of this, you **must** set `'guard' => 'sanctum'` (or your API guard) when using stateless `Authorization: Bearer ...` tokens—leaving `'guard' => null` falls back to the `web` session guard and returns `{"survey": null}`.

##### 2. Configure CORS & Page Targeting

- **Cross-origin SPAs:** Add your route prefix (e.g. `'api/simple-surveys/*'`) to `paths` in Laravel's `config/cors.php`.
- **Page targeting:** Because Laravel does not know client-side React Router / Vue Router route names, pass the current browser path as `?url=/dashboard` to `GET /eligible` and use `->onPaths(...)` / `->exceptPaths(...)` in your survey definitions instead of `->onRoutes(...)`.
- **Admin Dashboard:** `/simple-surveys/admin` is a session-authenticated Blade UI. Keep a lightweight web login for admins, or set `'admin' => ['enabled' => false]` if you do not expose web routes.

##### 3. Available Headless JSON Endpoints

| Endpoint | Method | Purpose |
| :--- | :--- | :--- |
| `/{prefix}/eligible?url=/path` | `GET` | Returns `200 {"survey": {...}}` (with questions, `appearance`, and action `urls`) or `{"survey": null}` |
| `/{prefix}/{key}/impression` | `POST` | Records unique `seen` impression (`200`) |
| `/{prefix}/{key}/open` | `POST` | Records survey open (`200`; also powers server-side `duration_ms` fallback) |
| `/{prefix}/{key}/responses` | `POST` | Validates and stores `{ answers, url, duration_ms }` (`201`, or `422` validation errors) |
| `/{prefix}/{key}/dismiss` | `POST` | Dismisses survey with `{ source: 'banner' \| 'card', url }` (`200`) |

##### 4. Minimal React Widget Example

```jsx
import { useEffect, useRef, useState } from 'react';

export function SurveyWidget({ apiBase = 'https://api.example.com', token, pathname }) {
  const [survey, setSurvey] = useState(null);
  const [open, setOpen] = useState(false);
  const [answers, setAnswers] = useState({});
  const [errors, setErrors] = useState({});
  const [thanks, setThanks] = useState('');
  const openedAt = useRef(null);

  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
  };

  useEffect(() => {
    if (!token) return;
    const url = `${apiBase}/api/simple-surveys/eligible?url=${encodeURIComponent(pathname)}`;

    fetch(url, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
      .then((r) => r.json())
      .then(({ survey: next }) => {
        setSurvey(next);
        setOpen(false);
        setAnswers({});
        setErrors({});
        setThanks('');
        openedAt.current = null;

        if (next && !sessionStorage.getItem(`lss_seen_${next.key}`)) {
          sessionStorage.setItem(`lss_seen_${next.key}`, '1');
          fetch(next.urls.impression, {
            method: 'POST',
            headers,
            body: JSON.stringify({ url: pathname }),
          }).catch(() => sessionStorage.removeItem(`lss_seen_${next.key}`));
        }
      });
  }, [apiBase, token, pathname]);

  if (!survey) return null;

  const handleOpen = () => {
    setOpen(true);
    if (!openedAt.current) {
      openedAt.current = Date.now();
      fetch(survey.urls.open, {
        method: 'POST',
        headers,
        body: JSON.stringify({ url: pathname }),
      }).catch(() => {});
    }
  };

  const handleDismiss = async (source) => {
    await fetch(survey.urls.dismiss, {
      method: 'POST',
      headers,
      body: JSON.stringify({ source, url: pathname }),
    });
    setSurvey(null);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({});

    const res = await fetch(survey.urls.submit, {
      method: 'POST',
      headers,
      body: JSON.stringify({
        answers,
        url: pathname,
        duration_ms: openedAt.current ? Date.now() - openedAt.current : undefined,
      }),
    });

    const data = await res.json().catch(() => ({}));
    if (res.status === 422) {
      setErrors(data.errors || {});
      return;
    }
    if (res.ok) {
      setThanks(data.message || survey.thank_you_message);
      setTimeout(() => setSurvey(null), 3000);
    }
  };

  const setAnswer = (key, value) => setAnswers((prev) => ({ ...prev, [key]: value }));

  if (!open) {
    return (
      <div className="survey-banner">
        <span>{survey.banner_text}</span>
        <button type="button" onClick={handleOpen}>Take Survey</button>
        {survey.dismissible && (
          <button type="button" onClick={() => handleDismiss('banner')} aria-label="Dismiss">&times;</button>
        )}
      </div>
    );
  }

  return (
    <div className="survey-card" role="dialog" aria-label={survey.title}>
      <h3>{survey.title}</h3>
      {thanks ? (
        <p>{thanks}</p>
      ) : (
        <form onSubmit={handleSubmit}>
          {survey.questions.map((q) => (
            <div key={q.key}>
              <label>{q.label}{q.required ? ' *' : ''}</label>
              {q.type === 'rating' && (
                <div>
                  {Array.from({ length: (q.max ?? 5) - (q.min ?? 1) + 1 }, (_, i) => (q.min ?? 1) + i).map((n) => (
                    <button key={n} type="button" onClick={() => setAnswer(q.key, n)} aria-pressed={answers[q.key] === n}>
                      {n}
                    </button>
                  ))}
                </div>
              )}
              {q.type === 'select' && (
                <select value={answers[q.key] ?? ''} onChange={(e) => setAnswer(q.key, e.target.value)}>
                  <option value="">{q.placeholder || 'Select an option...'}</option>
                  {Object.entries(q.options).map(([val, label]) => (
                    <option key={val} value={val}>{label}</option>
                  ))}
                </select>
              )}
              {q.type === 'boolean' && (
                <div>
                  <button type="button" onClick={() => setAnswer(q.key, true)}>Yes</button>
                  <button type="button" onClick={() => setAnswer(q.key, false)}>No</button>
                </div>
              )}
              {(q.type === 'text' || q.type === 'number') && (
                <input
                  type={q.type}
                  value={answers[q.key] ?? ''}
                  placeholder={q.placeholder || ''}
                  onChange={(e) => setAnswer(q.key, e.target.value)}
                />
              )}
              {q.type === 'textarea' && (
                <textarea
                  value={answers[q.key] ?? ''}
                  placeholder={q.placeholder || ''}
                  onChange={(e) => setAnswer(q.key, e.target.value)}
                />
              )}
              {errors[`answers.${q.key}`] && <p role="alert">{errors[`answers.${q.key}`][0]}</p>}
            </div>
          ))}
          <div>
            {survey.dismissible && (
              <button type="button" onClick={() => handleDismiss('card')}>
                {survey.reprompt_after_dismiss ? 'Remind me later' : "Don't show again"}
              </button>
            )}
            <button type="submit">{survey.submit_label}</button>
          </div>
        </form>
      )}
    </div>
  );
}
```

> Using Sanctum cookie-based SPA auth instead of Bearer tokens? Omit the `Authorization` header, pass `credentials: 'include'` (and the `X-XSRF-TOKEN` header on `POST` requests), and configure `SANCTUM_STATEFUL_DOMAINS`.

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
- **Banner Icon:** Choose from 10 built-in SVG presets, hide the icon (`none`), or reference a custom `.png`/`.svg` asset inside `public/`.
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

## Localization & Translatable Surveys

Single-language applications need zero translation configuration. For multi-language apps, Simple Surveys supports two clean layers resolved lazily at request time (`app()->getLocale()`):

### 1. Built-In Widget UI Strings

Publish the package's widget language file (`Take Survey`, `Yes`, `No`, `Remind me later`, `Don't show again`, default `Submit`, default thank-you message, and error labels) into `lang/vendor/simple-surveys/`:

```bash
php artisan vendor:publish --tag="simple-surveys-translations"
```

Create locale folders such as `lang/vendor/simple-surveys/fr/widget.php` to translate the built-in widget chrome.

### 2. Translating Survey Copy (`lang/{locale}/simple-surveys.php`)

Keep defining your surveys cleanly in PHP. To translate titles, banners, descriptions, submit labels, thank-you messages, question labels, help text, placeholders, and answer option labels, configure your target locales in `config/simple-surveys.php`:

```php
'translations' => [
    'file' => 'simple-surveys',
    'locales' => ['fr', 'de'],
],
```

Then scaffold and sync your Laravel translation files from all registered surveys:

```bash
php artisan simple-surveys:translations
```

Optional flags:

- `--locales=fr,de,es` — Override the target locales from config
- `--source=en` — Override the source locale (defaults to `config('app.fallback_locale')`)
- `--survey=onboarding-feedback` — Export/update a single survey key
- `--empty` — Leave newly scaffolded target locale strings empty instead of pre-filling source strings
- `--prune` — Remove survey/question entries that are no longer registered in code

Re-running `simple-surveys:translations` updates your source locale file (`lang/en/simple-surveys.php`) and merges new keys into target locale files **without overwriting existing translations**. Because it uses standard Laravel `lang/{locale}/simple-surveys.php` files, it also works out of the box with Git-connected translation platforms like Crowdin, Lokalise, Phrase, and Weblate.

```php
// lang/fr/simple-surveys.php
return [
    'onboarding-feedback' => [
        'title' => 'Sondage de bienvenue',
        'banner' => 'Vous avez 30 secondes ?',
        'questions' => [
            'source' => [
                'label' => 'Comment nous avez-vous connus ?',
                'options' => [
                    'google' => 'Moteur de recherche',
                    'friend' => 'Un ami',
                ],
            ],
        ],
    ],
];
```

- **Option keys stay canonical:** Only option *labels* are translated — stored answers, `->when(...)` conditions, and Admin Dashboard analytics continue to aggregate cleanly across all languages.
- **Canonical DB sync:** `simple_surveys` and `simple_survey_questions` always store the canonical PHP copy rather than the submitting user's locale.
- **Respondent locale recorded automatically:** Every submitted response records the active `locale` in `SurveyResponse::$metadata['locale']`, the `SurveySubmitted` event `$context`, webhook payloads, the Admin Dashboard responses table, and CSV exports (`Locale` column).

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

## Analytics, Tracking Context & Webhooks

### Browser `CustomEvent`s (GA4, PostHog, Mixpanel, Custom AJAX)

The built-in widget dispatches bubbling `CustomEvent`s on `window` across the entire lifecycle so you can forward events to any frontend analytics SDK or custom endpoint:

- `simple-surveys:seen` — `{ surveyKey, url, firstInSession }`
- `simple-surveys:opened` — `{ surveyKey, url }`
- `simple-surveys:minimized` — `{ surveyKey, url, durationMs }`
- `simple-surveys:dismissed` — `{ surveyKey, url, source, durationMs }` (`source`: `'banner'` or `'card'`)
- `simple-surveys:submitted` — `{ surveyKey, url, answers, durationMs }`

```js
window.addEventListener('simple-surveys:submitted', ({ detail }) => {
    posthog.capture('survey_submitted', detail);
});
```

### Tracking Context & Metadata Resolver

Capture cookies (such as `distinct_id` or `click_id`) in `config/simple-surveys.php`:

```php
'tracking' => [
    'impressions' => true,
    'cookies' => [
        'distinct_id' => 'ph_distinct_id',
        'click_id',
    ],
],
```

> **Note:** For unencrypted cookies set by frontend scripts or third-party analytics SDKs (such as `ph_distinct_id` or `click_id`), exclude those cookie names from Laravel's cookie encryption via `$middleware->encryptCookies(except: ['ph_distinct_id', 'click_id'])` in `bootstrap/app.php`.

Or register a server-side callback in `AppServiceProvider::boot()` to snapshot user traits and request attributes across all lifecycle events, stored response `metadata`, CSV exports, and webhook payloads:

```php
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use Illuminate\Http\Request;

SimpleSurveys::resolveTrackingContext(fn (Request $request, $user, $survey): array => [
    'distinct_id' => $request->cookie('ph_distinct_id'),
    'click_id' => $request->cookie('click_id'),
    'plan' => $user->plan,
]);
```

When responses include `duration_ms` (measured client-side by the widget, or derived server-side from `first_opened_at` to submission time for custom frontends) or resolved point-in-time tracking/cohort keys (such as `plan`, `distinct_id`, or `click_id`), the Admin Dashboard **Export CSV** automatically appends `Duration (ms)` and each tracking key as dedicated columns.

### Lifecycle Webhooks

Forward `seen`, `opened`, `dismissed`, and `submitted` events server-to-server with queued delivery, retries, custom headers, and optional HMAC-SHA256 signing (`X-Simple-Surveys-Signature`). Disabled by default (`webhooks.enabled => false`).

Configure globally in `config/simple-surveys.php` (supports either a single default `url` or per-event URL overrides):

```php
'webhooks' => [
    'enabled' => true,
    'url' => env('SIMPLE_SURVEYS_WEBHOOK_URL'),
    'events' => ['seen', 'opened', 'dismissed', 'submitted'],
    'secret' => env('SIMPLE_SURVEYS_WEBHOOK_SECRET'),
    'headers' => [
        'Authorization' => 'Bearer ' . env('SIMPLE_SURVEYS_WEBHOOK_TOKEN'),
    ],
    'queued' => true,
    'queue' => 'webhooks',
],
```

Or attach webhooks directly to individual surveys (or opt a survey out of global webhooks via `->withoutWebhooks()`):

```php
Survey::make('onboarding-feedback')
    ->webhook(
        url: 'https://data.example.com/hooks/surveys',
        events: ['seen', 'opened', 'dismissed', 'submitted'],
        secret: env('SURVEY_WEBHOOK_SECRET'),
    );
```

### Submission Notifications (Mail, Slack & Custom Channels)

Optionally dispatch built-in Laravel notifications whenever a survey response is submitted. Completely disabled by default (`notifications.enabled => false`), supports queued delivery (`queued => true`) so submissions stay fast, and works with Mail, Slack incoming webhooks (zero extra packages required), or any custom Laravel notification channel.

Configure globally in `config/simple-surveys.php`:

```php
'notifications' => [
    'enabled' => true,
    'notification' => \DanielCristea\SimpleSurveys\Notifications\SurveySubmittedNotification::class,
    'channels' => ['mail', 'slack'],
    'routes' => [
        'mail' => ['product@example.com', 'ops@example.com'],
        'slack' => env('SIMPLE_SURVEYS_SLACK_WEBHOOK_URL'),
    ],
    'queued' => true,
    'queue' => 'notifications',
],
```

You can also resolve global `Notifiable` models (such as admin users) in `AppServiceProvider::boot()`:

```php
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;

SimpleSurveys::resolveNotifiables(fn (SurveySubmitted $event) => User::where('is_admin', true)->get());
```

Or configure notifications per survey (works even when global `notifications.enabled` is `false`, or suppress global notifications on a specific survey with `->withoutNotifications()`):

```php
Survey::make('checkout-feedback')
    ->notifyMail('checkout-team@example.com')
    ->notifySlack(env('CHECKOUT_SLACK_WEBHOOK_URL'))
    ->notifyUsers(fn (SurveySubmitted $event) => User::where('is_admin', true)->get(), channels: ['mail', 'database']);
```

To customize the notification content, extend `SurveySubmittedNotification` (or pass any Laravel `Notification` class) via `simple-surveys.notifications.notification` or per-survey `->notification(CustomSurveyNotification::class)`.

## Events

The package dispatches four Laravel events you can listen to (each includes `$context` and `$tracking` arrays):

- `DanielCristea\SimpleSurveys\Events\SurveySeen` (`$survey`, `$impression`, `$respondent`, `$context`, `$tracking`) — dispatched on a respondent's first recorded impression
- `DanielCristea\SimpleSurveys\Events\SurveyOpened` (`$survey`, `$open`, `$respondent`, `$context`, `$tracking`)
- `DanielCristea\SimpleSurveys\Events\SurveySubmitted` (`$survey`, `$response`, `$respondent`, `$context`, `$tracking`)
- `DanielCristea\SimpleSurveys\Events\SurveyDismissed` (`$survey`, `$dismissal`, `$respondent`, `$context`, `$tracking`)

## Configuration

Key options in `config/simple-surveys.php`:

- `tables.*` — Customize the 7 database table names (`simple_surveys`, `simple_survey_questions`, `simple_survey_responses`, `simple_survey_answers`, `simple_survey_dismissals`, `simple_survey_opens`, `simple_survey_impressions`).
- `respondent_key_type` — `'id'` (default), `'uuid'`, or `'ulid'`.
- `guard` — Auth guard name (`null` uses your app's default guard).
- `routes` — Enable/disable customer submission/dismissal routes, prefix, and middleware.
- `widget` — Default customer widget settings (`widget.icon` — built-in preset, `'none'`, or relative `public/` `.png`/`.svg` path).
- `translations` — Convention translation filename (`translations.file`, default `'simple-surveys'`) and default target locale codes (`translations.locales`) for `php artisan simple-surveys:translations`.
- `tracking` — Toggle impression tracking (`tracking.impressions`) and auto-captured cookies (`tracking.cookies`).
- `webhooks` — Enable/configure server-to-server lifecycle webhooks (`enabled`, `url`, `events`, `secret`, `signature_header`, `headers`, `queued`, `queue`, `timeout`, `tries`, `backoff`).
- `notifications` — Enable/configure optional submission notifications (`enabled`, `notification`, `channels`, `routes`, `queued`, `queue`, `slack_timeout`).
- `admin` — Enable/disable admin dashboard routes, prefix, middleware, Gate ability name, and `appearance_editor` toggle.

## Testing & Quality

```bash
composer test
composer analyse
composer format:check
```

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
