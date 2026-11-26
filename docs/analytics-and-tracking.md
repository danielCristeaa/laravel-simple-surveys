[← Back to README](../README.md)

# Analytics, Events & Tracking Context

Simple Surveys provides client-side browser `CustomEvent`s, server-side Laravel events, cookie/user-trait context snapshots, and built-in funnel telemetry.

---

## Browser `CustomEvent`s (GA4, PostHog, Mixpanel)

The built-in Blade widget dispatches bubbling `CustomEvent`s on `window` across the entire lifecycle so you can forward survey interactions to any frontend analytics SDK:

| Event Name | `event.detail` Payload | Fired When |
| :--- | :--- | :--- |
| `simple-surveys:seen` | `{ surveyKey, url, firstInSession }` | Widget banner renders in the viewport. |
| `simple-surveys:opened` | `{ surveyKey, url }` | Respondent expands the survey card. |
| `simple-surveys:minimized` | `{ surveyKey, url, durationMs }` | Respondent collapses the open card back to the banner. |
| `simple-surveys:dismissed` | `{ surveyKey, url, source, durationMs }` | Respondent dismisses from `'banner'` or `'card'`. |
| `simple-surveys:submitted` | `{ surveyKey, url, answers, durationMs }` | Survey response is accepted by the server. |

```js
window.addEventListener('simple-surveys:submitted', ({ detail }) => {
    posthog.capture('survey_submitted', detail);
});

window.addEventListener('simple-surveys:dismissed', ({ detail }) => {
    gtag('event', 'survey_dismissed', {
        survey_key: detail.surveyKey,
        dismiss_source: detail.source,
    });
});
```

---

## Tracking Context & Metadata Resolver

Snapshot request cookies and point-in-time user traits (such as subscription plan, workspace tier, or analytics distinct ID) so they travel automatically into:

- `SurveyResponse::$metadata['tracking']`
- All four Laravel lifecycle events (`$event->tracking`)
- Outgoing [lifecycle webhooks](webhooks-and-notifications.md)
- Admin Dashboard **Export CSV** (each tracking key becomes its own column automatically)

### 1. Cookie Auto-Capture (Config)

List cookie names (or `tracking_key => cookie_name` mappings) in `config/simple-surveys.php`:

```php
'tracking' => [
    'impressions' => true,
    'cookies' => [
        'distinct_id' => 'ph_distinct_id',
        'click_id',
    ],
],
```

> **Unencrypted Frontend Cookies:** For cookies set by JavaScript or third-party SDKs (like `ph_distinct_id` or `click_id`), exclude them from Laravel's cookie encryption in `bootstrap/app.php`:
>
> ```php
> $middleware->encryptCookies(except: ['ph_distinct_id', 'click_id']);
> ```

### 2. Server-Side Context Callback

Register a callback in `AppServiceProvider::boot()` for dynamic user traits or request headers:

```php
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use Illuminate\Http\Request;

SimpleSurveys::resolveTrackingContext(fn (Request $request, $user, $survey): array => [
    'distinct_id' => $request->cookie('ph_distinct_id'),
    'plan' => $user->plan,
    'account_age_days' => (int) $user->created_at->diffInDays(now()),
]);
```

Callback values merge with any cookies captured via config.

### Funnel Telemetry & Duration

- **Impressions (`seen`):** Recorded once per `(survey, respondent)` pair when `tracking.impressions` is `true` (default). Set `'impressions' => false` to disable DB impression writes.
- **Opens & Abandonment:** Every expand records `first_opened_at`, `last_opened_at`, and `open_count` in `simple_survey_opens`. Respondents who opened a survey without submitting count toward the **Abandoned** metric in the Admin Dashboard.
- **Completion Duration (`duration_ms`):** Measured client-side by the built-in widget, or computed server-side from `first_opened_at` to submission time for custom headless frontends. Automatically exported in the CSV `Duration (ms)` column.

---

## Laravel Lifecycle Events

Listen to any of the four server-side events in your application (`DanielCristea\SimpleSurveys\Events\*`):

| Event Class | Properties | Dispatched When |
| :--- | :--- | :--- |
| `SurveySeen` | `$survey`, `$impression`, `$respondent`, `$context`, `$tracking` | First unique impression is recorded for a respondent. |
| `SurveyOpened` | `$survey`, `$open`, `$respondent`, `$context`, `$tracking` | Respondent opens the survey card. |
| `SurveyDismissed` | `$survey`, `$dismissal`, `$respondent`, `$context`, `$tracking` | Respondent dismisses the survey (`$context['source']` is `'banner'` or `'card'`). |
| `SurveySubmitted` | `$survey`, `$response`, `$respondent`, `$context`, `$tracking` | Validated response is saved (`$context` includes `url`, `duration_ms`, and `locale`). |

```php
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use Illuminate\Support\Facades\Event;

Event::listen(SurveySubmitted::class, function (SurveySubmitted $event): void {
    // Access typed answers, respondent, context, and tracking snapshot
    $answers = $event->response->answers->mapWithKeys(
        fn ($answer) => [$answer->question->key => $answer->typedValue()]
    );
});
```

---

← Previous: [Localization & Translations](localization.md) | Next: [Webhooks & Notifications →](webhooks-and-notifications.md)
