[← Back to README](../README.md)

# Webhooks & Submission Notifications

Forward survey lifecycle events to external pipelines (Zapier, n8n, data warehouses, internal microservices) or alert your team immediately via Mail, Slack, or custom Laravel notification channels.

Both features are **disabled by default** and support queued delivery so customer survey interactions stay fast.

---

## Lifecycle Webhooks

### Global Configuration

Enable global webhooks in `config/simple-surveys.php`:

```php
'webhooks' => [
    'enabled' => true,
    'url' => env('SIMPLE_SURVEYS_WEBHOOK_URL'),
    'events' => ['seen', 'opened', 'dismissed', 'submitted'],
    'secret' => env('SIMPLE_SURVEYS_WEBHOOK_SECRET'),
    'signature_header' => 'X-Simple-Surveys-Signature',
    'headers' => [
        'Authorization' => 'Bearer '.env('SIMPLE_SURVEYS_WEBHOOK_TOKEN'),
    ],
    'queued' => true,
    'queue' => 'webhooks',
    'timeout' => 5,
    'tries' => 3,
    'backoff' => [10, 60, 300],
],
```

You can also route different events to different endpoints by passing an associative map for `events`:

```php
'events' => [
    'submitted' => 'https://hooks.example.com/survey-responses',
    'dismissed' => 'https://hooks.example.com/survey-dismissals',
],
```

### Per-Survey Webhooks

Attach webhooks directly to individual surveys—works even when global `webhooks.enabled` is `false`—or opt a survey out of all webhooks with `->withoutWebhooks()`:

```php
Survey::make('onboarding-feedback')
    ->webhook(
        url: 'https://data.example.com/hooks/surveys',
        events: ['seen', 'opened', 'dismissed', 'submitted'],
        secret: env('SURVEY_WEBHOOK_SECRET'),
        headers: ['X-Team' => 'growth'],
    );

Survey::make('internal-test')
    ->withoutWebhooks();
```

### Payload Format & HMAC Verification

Every webhook request sends JSON with `X-Simple-Surveys-Event` (`survey.seen`, `survey.opened`, `survey.dismissed`, or `survey.submitted`):

```json
{
  "event": "survey.submitted",
  "occurred_at": "2026-11-26T18:00:00+00:00",
  "survey": {
    "key": "onboarding-feedback",
    "title": "Quick Onboarding Survey"
  },
  "respondent": {
    "type": "App\\Models\\User",
    "id": 42
  },
  "tracking": {
    "plan": "pro",
    "distinct_id": "ph_123"
  },
  "context": {
    "url": "/dashboard",
    "duration_ms": 14200,
    "locale": "en"
  },
  "data": {
    "response_id": 108,
    "answers": {
      "experience": 5,
      "source": "google"
    }
  }
}
```

When a `secret` is configured, the signature header (`X-Simple-Surveys-Signature` by default) contains the hex HMAC-SHA256 digest of the raw JSON request body:

```php
$expected = hash_hmac('sha256', $request->getContent(), config('services.surveys.webhook_secret'));

abort_unless(
    hash_equals($expected, (string) $request->header('X-Simple-Surveys-Signature')),
    401,
);
```

---

## Submission Notifications (Mail, Slack & Custom Channels)

Dispatch Laravel notifications whenever a survey response is submitted. Slack uses an included zero-dependency incoming webhook channel (`SlackWebhookChannel`).

### Global Configuration

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
    'slack_timeout' => 5,
],
```

### Resolving Global `Notifiable` Models

Notify database models (such as admin users) dynamically in `AppServiceProvider::boot()`:

```php
use App\Models\User;
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;

SimpleSurveys::resolveNotifiables(
    fn (SurveySubmitted $event) => User::where('is_admin', true)->get()
);
```

### Per-Survey Notifications

Per-survey notification methods work even when global `notifications.enabled` is `false`. Suppress all notifications for a survey using `->withoutNotifications()`:

```php
Survey::make('checkout-feedback')
    ->notifyMail('checkout-team@example.com')
    ->notifySlack(env('CHECKOUT_SLACK_WEBHOOK_URL'))
    ->notifyUsers(
        fn (SurveySubmitted $event) => User::where('is_admin', true)->get(),
        channels: ['mail', 'database'],
    )
    ->notify('discord', env('DISCORD_WEBHOOK_URL'));
```

| Method | Parameters | Description |
| :--- | :--- | :--- |
| `->notifyMail($recipients)` | `string\|array<int\|string, string>` | Sends email notification on submission. |
| `->notifySlack($webhookUrl)` | `string` | Posts a formatted Block Kit summary to a Slack incoming webhook. |
| `->notifyUsers($resolver, $channels = null)` | `Closure(SurveySubmitted): mixed, ?array` | Sends to resolved `Notifiable` model(s) over the given channels (falls back to global `notifications.channels`). |
| `->notify($channel, $route)` | `string, mixed` | Sends an on-demand notification over any custom Laravel channel. |
| `->notification($class)` | `?class-string<Notification>` | Overrides the notification class for this survey. |
| `->withoutNotifications($disabled = true)` | `bool` | Suppresses all global and per-survey submission notifications. |

### Custom Notification Classes

Either extend `DanielCristea\SimpleSurveys\Notifications\SurveySubmittedNotification` to tweak `toMail()`, `toSlackWebhook()`, or `toArray()`, or pass any `Illuminate\Notifications\Notification` subclass accepting `SurveySubmitted $event` in its constructor:

```php
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use Illuminate\Notifications\Notification;

final class HighPrioritySurveyAlert extends Notification
{
    public function __construct(
        public readonly SurveySubmitted $event,
    ) {}

    // ...
}
```

Register globally via `simple-surveys.notifications.notification` or per survey via `->notification(HighPrioritySurveyAlert::class)`.

---

← Previous: [Analytics, Events & Tracking Context](analytics-and-tracking.md) | Next: [Configuration & Artisan Commands →](configuration.md)
