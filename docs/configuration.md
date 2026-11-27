[← Back to README](../README.md)

# Configuration & Artisan Commands

Publish the package configuration file to customize table names, respondent key types, auth guards, routes, localization, tracking, webhooks, notifications, and admin access:

```bash
php artisan vendor:publish --tag="simple-surveys-config"
```

---

## Configuration Reference (`config/simple-surveys.php`)

### Core & Database

| Key | Default | Description |
| :--- | :--- | :--- |
| `tables.surveys` | `'simple_surveys'` | Table storing synced survey definitions and appearance overrides. |
| `tables.questions` | `'simple_survey_questions'` | Table storing synced survey questions and constraints. |
| `tables.responses` | `'simple_survey_responses'` | Table storing respondent submissions and metadata. |
| `tables.answers` | `'simple_survey_answers'` | Table storing normalized typed answers (`value`, `value_numeric`, `value_json`). |
| `tables.dismissals` | `'simple_survey_dismissals'` | Table storing survey dismissals, source, and `dismiss_count`. |
| `tables.opens` | `'simple_survey_opens'` | Table storing survey open counts and `first_opened_at` / `last_opened_at`. |
| `tables.impressions` | `'simple_survey_impressions'` | Table storing unique respondent impressions (`first_seen_at`). |
| `respondent_key_type` | `'id'` | Polymorphic `respondent_id` column type used during migration: `'id'`, `'uuid'`, or `'ulid'`. Set **before** running `php artisan migrate`. |
| `guard` | `null` | Auth guard for eligibility, submissions, and admin access (`null` uses your default guard; set `'sanctum'` / `'api'` for Bearer-token APIs). |

### Customer Routes & Widget

| Key | Default | Description |
| :--- | :--- | :--- |
| `routes.enabled` | `true` | Toggle registration of all customer endpoints (`eligible`, `widget`, `impression`, `open`, `responses`, `dismiss`). |
| `routes.prefix` | `'simple-surveys'` | URL prefix for customer endpoints (e.g. `'api/simple-surveys'` for headless APIs). |
| `routes.middleware` | `['web', 'auth']` | Middleware for `POST` endpoints (`GET /eligible` and `GET /widget` automatically omit `auth` / `auth:*` middleware so guests get an empty response instead of `401`). |
| `widget.icon` | `'chat'` | Global default banner icon: built-in preset (`chat`, `star`, `sparkles`, `megaphone`, `clipboard`, `heart`, `lightbulb`, `thumbs-up`, `bell`, `help`), `'none'`, or relative `public/` `.png`/`.svg` path. |

### Localization & Tracking

| Key | Default | Description |
| :--- | :--- | :--- |
| `translations.file` | `'simple-surveys'` | Filename under `lang/{locale}/` used for convention-based survey copy translations. |
| `translations.locales` | `[]` | Default target locale codes scaffolded by `php artisan simple-surveys:translations`. |
| `tracking.impressions` | `true` | Enable/disable DB impression tracking (`POST /{prefix}/{key}/impression`). |
| `tracking.cookies` | `[]` | Cookies auto-captured into tracking context (list `['click_id']` or map `['distinct_id' => 'ph_distinct_id']`). |

### Webhooks & Notifications

| Key | Default | Description |
| :--- | :--- | :--- |
| `webhooks.enabled` | `false` | Master switch for global lifecycle webhooks. |
| `webhooks.url` | `null` | Default webhook destination URL. |
| `webhooks.events` | `['seen', 'opened', 'dismissed', 'submitted']` | List of enabled events or associative `['event' => 'https://...']` map. |
| `webhooks.secret` | `null` | Shared secret used for HMAC-SHA256 payload signing. |
| `webhooks.signature_header` | `'X-Simple-Surveys-Signature'` | Header name carrying the HMAC digest. |
| `webhooks.headers` | `[]` | Extra HTTP headers sent on every webhook request. |
| `webhooks.queued` / `queue` | `true` / `null` | Dispatch webhooks via `DispatchSurveyWebhookJob` on the specified queue. |
| `webhooks.timeout` / `tries` / `backoff` | `5` / `3` / `[10, 60, 300]` | HTTP timeout (seconds), retry attempts, and retry backoff schedule. |
| `notifications.enabled` | `false` | Master switch for global submission notifications. |
| `notifications.notification` | `SurveySubmittedNotification::class` | Notification class dispatched on submission. |
| `notifications.channels` | `['mail']` | Global notification channels (`'mail'`, `'slack'`, `'database'`, or custom). |
| `notifications.routes` | `['mail' => null, 'slack' => null]` | On-demand destinations per channel. |
| `notifications.queued` / `queue` | `true` / `null` | Queue notifications on the specified queue. |
| `notifications.slack_timeout` | `5` | HTTP timeout (seconds) for the built-in Slack webhook channel. |

### Admin Results Dashboard

| Key | Default | Description |
| :--- | :--- | :--- |
| `admin.enabled` | `true` | Toggle registration of `/simple-surveys/admin` routes. |
| `admin.prefix` | `'simple-surveys/admin'` | URL prefix for the Admin Results Dashboard. |
| `admin.middleware` | `['web', 'auth']` | Middleware stack (the Gate middleware is appended automatically). |
| `admin.gate` | `'viewSimpleSurveys'` | Laravel Gate ability checked before granting Admin Dashboard access. |
| `admin.appearance_editor` | `true` | Enable/disable the visual per-survey Appearance tab. |

---

## Publishable Assets

| Tag | Destination | Contents |
| :--- | :--- | :--- |
| `simple-surveys-migrations` | `database/migrations/` | 1 migration creating the 7 normalized tables. |
| `simple-surveys-config` | `config/simple-surveys.php` | Package configuration file. |
| `simple-surveys-styles` | `resources/css/simple-surveys.css` | Starter CSS override file with all public `--lss-*` variables. |
| `simple-surveys-translations` | `lang/vendor/simple-surveys/` | Built-in widget UI strings (`widget.php`). |
| `simple-surveys-views` | `resources/views/vendor/simple-surveys/` | Customer widget and Admin Dashboard Blade views. |

---

## Artisan Commands

| Command | Purpose |
| :--- | :--- |
| `php artisan simple-surveys:sync` | Syncs all code-registered surveys and questions into the database upfront. |
| `php artisan simple-surveys:results {key?}` | Displays a funnel summary table for all surveys or detailed per-question breakdowns for a single survey key (supports `--json`). |
| `php artisan simple-surveys:translations` | Exports canonical survey copy and scaffolds/merges `lang/{locale}/simple-surveys.php` files (supports `--locales=`, `--source=`, `--survey=`, `--empty`, `--prune`). |

---

## Content Security Policy (CSP) Nonce

If your application enforces a strict `Content-Security-Policy` with nonces, all inline `<style>` and `<script>` tags across the customer widget, dynamic SPA wrapper, and Admin Results Dashboard emit `nonce="..."` automatically when `Vite::useCspNonce()` is active.

For custom CSP providers (such as `spatie/laravel-csp`), register a per-request resolver in `AppServiceProvider::boot()`:

```php
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;

SimpleSurveys::resolveCspNonce(fn (): ?string => csp_nonce());
```

Resolution precedence: explicit `<x-simple-surveys::widget nonce="..." />` attribute > `SimpleSurveys::resolveCspNonce(...)` callback > `Vite::cspNonce()`. Blank or `null` values never emit an empty `nonce=""` attribute.

---

## UUID / ULID User Models & Laravel Octane

- **UUID / ULID primary keys:** Before running `php artisan migrate`, publish `config/simple-surveys.php` and set `'respondent_key_type' => 'uuid'` (or `'ulid'`).
- **Laravel Octane:** All singletons (`SurveyRegistry`, `SurveyEligibilityResolver`, `SurveyTranslator`, `SurveyCspNonceResolver`, etc.) store only boot-time definitions/callbacks or stateless services—never per-request user, locale, or nonce state—making the package Octane-safe out of the box.

---

← Previous: [Webhooks & Notifications](webhooks-and-notifications.md) | Next: [Recipes & Walkthroughs →](recipes.md)
