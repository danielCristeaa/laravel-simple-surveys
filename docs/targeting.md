[← Back to README](../README.md)

# Audience Targeting & Scheduling

Surveys only target **authenticated users** and automatically exclude respondents who have already submitted or actively dismissed the survey. Unauthenticated guests trigger **0 database queries**.

---

## Eloquent Query Targeting (`->target()`)

Filter eligible respondents using real Eloquent queries—including `where`, `whereHas`, and model scopes:

```php
use Illuminate\Database\Eloquent\Builder;

Survey::make('post-purchase-nps')
    ->title('How was your order?')
    ->target(fn (Builder $query) => $query
        ->where('created_at', '<=', now()->subDays(7))
        ->whereHas('orders', fn (Builder $q) => $q->where('status', 'paid'))
    )
    ->questions([...]);
```

Your callback is always safely nested inside `WHERE id = ? AND (...)`, so `orWhere(...)` clauses inside `->target()` can never leak across other users.

---

## Direct User Predicate (`->targetUser()`)

When targeting attributes already loaded on the authenticated `$user` model (roles, feature flags, plan columns), use `->targetUser()` to evaluate in memory with zero extra queries:

```php
Survey::make('beta-feedback')
    ->title('Beta Feature Feedback')
    ->targetUser(fn ($user): bool => $user->isBetaTester() && $user->plan === 'pro')
    ->questions([...]);
```

If both `->targetUser()` and `->target()` are defined on the same survey, `->targetUser()` runs first; if it returns `false`, the Eloquent query is skipped completely.

---

## Route & URL Path Targeting

Restrict which pages display a survey using route names, URL paths, or both (all methods accept a single string or an array of wildcard patterns via `Str::is`):

```php
Survey::make('billing-feedback')
    ->title('How is our new billing portal?')
    ->onRoutes(['billing.*', 'subscriptions.index'])
    ->exceptRoutes('billing.checkout.*')
    ->questions([...]);

Survey::make('app-nps')
    ->title('Quick Feedback')
    ->onPaths('/app/*')
    ->exceptPaths(['/app/checkout/*', '/app/settings/billing'])
    ->questions([...]);
```

| Method | Parameters | Description |
| :--- | :--- | :--- |
| `->onRoutes($routes)` | `string\|array<int, string>` | Show only when the current Laravel route name matches. |
| `->exceptRoutes($routes)` | `string\|array<int, string>` | Never show on matching Laravel route names. |
| `->onPaths($paths)` | `string\|array<int, string>` | Show only when the normalized URL path matches (leading/trailing slashes are normalized automatically). |
| `->exceptPaths($paths)` | `string\|array<int, string>` | Never show on matching URL paths. |

### Evaluation Rules

1. Exclusion rules (`exceptRoutes`, `exceptPaths`) always win over inclusion rules.
2. If neither `onRoutes` nor `onPaths` is specified, the survey is allowed on all non-excluded pages.
3. If both `onRoutes` and `onPaths` are specified, matching **either** allows the survey.
4. For headless SPAs (React Router / Vue Router), prefer `->onPaths()` / `->exceptPaths()` and pass `?url=/current-path` to `GET /{prefix}/eligible`.

---

## Scheduling & Status Flags

Control when a survey runs directly in code:

```php
Survey::make('q4-feedback')
    ->activeBetween('2026-10-01 00:00:00', '2026-12-31 23:59:59')
    ->questions([...]);
```

| Method | Effect |
| :--- | :--- |
| `->startsAt($date)` | Survey stays in `scheduled` status until `$date`. |
| `->endsAt($date)` / `->until($date)` | Survey transitions to `closed` after `$date`. |
| `->activeBetween($start, $end)` | Sets both `startsAt` and `endsAt`. |
| `->draft()` | Keeps the survey hidden from all respondents. |
| `->closed()` | Marks the survey closed in code. |

> **Admin Overrides:** Closing or re-opening a survey from the [Admin Results Dashboard](admin-dashboard.md) updates the `status` column on `simple_surveys`. Once synced, the database `status` takes precedence over code schedule windows so operators can end or re-open a live campaign without redeploying.

---

## Re-Prompting After Dismissal

By default, dismissing a survey permanently hides it for that respondent. To re-show a dismissed survey after a cooldown window, configure a re-prompt interval:

```php
use Carbon\CarbonInterval;

Survey::make('nps')
    ->title('Quick NPS Check')
    ->repromptAfterDays(14)
    ->questions([...]);
```

| Method | Parameters | Description |
| :--- | :--- | :--- |
| `->repromptAfterDays($days)` | `int` (`> 0`) | Re-show after `$days` days. |
| `->repromptAfterHours($hours)` | `int` (`> 0`) | Re-show after `$hours` hours. |
| `->repromptAfterMinutes($minutes)` | `int` (`> 0`) | Re-show after `$minutes` minutes. |
| `->repromptAfter($interval)` | `DateInterval\|int\|null` | Accepts any `DateInterval` / `CarbonInterval`, an integer (days), or `null` to disable. |

- When a re-prompt interval is configured, the card's dismiss link automatically switches from **"Don't show again"** to **"Remind me later"**.
- Completed responses **never** re-prompt.
- If a user dismisses multiple times across cooldowns, the `simple_survey_dismissals` row updates `dismissed_at`, `source`, and increments `dismiss_count`.

---

## Priority & Eligibility Resolution Order

When multiple surveys are registered, `SimpleSurveys::forUser($user)` resolves eligibility in this order:

1. Surveys are ordered by **`priority` descending** (higher numbers first), then registration order.
2. Draft surveys and surveys that fail page targeting (`matchesPage`) are filtered out.
3. Effectively active surveys are checked (DB `status === 'active'` when synced, otherwise `$survey->isActive()`).
4. Surveys the respondent already **completed** or has an **unexpired dismissal** for are excluded in a single batched query.
5. For each remaining candidate, `->targetUser()` and `->target()` run in order; the **first match** is returned.

### Checking Eligibility Programmatically

```php
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\PageContext;

// Highest-priority eligible survey for a user
$survey = SimpleSurveys::forUser($user, context: PageContext::fromRequest($request));

// All eligible surveys for a user
$surveys = SimpleSurveys::eligibleFor($user);

// Check a specific survey key
$canSee = SimpleSurveys::isEligible('onboarding-feedback', $user);
```

---

← Previous: [Defining Surveys](defining-surveys.md) | Next: [Customer Widget & SPA Mode →](widget.md)
