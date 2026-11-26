[← Back to README](../README.md)

# Recipes & Step-by-Step Walkthroughs

Real-world examples combining multiple features of Laravel Simple Surveys from registration through production telemetry.

---

## Recipe 1: Post-Onboarding CSAT with Conditional Follow-Up & Slack Alerts

**Goal:** Show a 5-point satisfaction survey to paying users 7 days after signup, ask for details *only* when they score `<= 3`, re-prompt after 7 days if dismissed, and notify `#product-feedback` on Slack immediately upon submission.

### Step 1: Create a `SurveyDefinition` Class

```php
// app/Surveys/OnboardingCsatSurvey.php
namespace App\Surveys;

use DanielCristea\SimpleSurveys\Contracts\SurveyDefinition;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Database\Eloquent\Builder;

final class OnboardingCsatSurvey implements SurveyDefinition
{
    public function definition(): Survey
    {
        return Survey::make('onboarding-csat')
            ->title('Quick Onboarding Check-In')
            ->banner('How has your first week been?')
            ->icon('sparkles')
            ->priority(20)
            ->repromptAfterDays(7)
            ->onRoutes(['dashboard', 'projects.*'])
            ->target(fn (Builder $query) => $query
                ->where('created_at', '<=', now()->subDays(7))
                ->whereHas('orders', fn (Builder $q) => $q->where('status', 'paid'))
            )
            ->notifySlack((string) config('services.slack.product_feedback_webhook'))
            ->questions([
                Question::rating('score', 'How smooth was your setup experience?')
                    ->scale(1, 5)
                    ->required(),

                Question::textarea('blocker', 'What slowed you down the most?')
                    ->placeholder('Tell us what felt confusing or missing...')
                    ->maxLength(1000)
                    ->when('score', '<=', 3)
                    ->required(),

                Question::boolean('recommend', 'Would you recommend us to a teammate?')
                    ->required(),
            ]);
    }
}
```

### Step 2: Register It & Authorize Admins

```php
// app/Providers/AppServiceProvider.php
use App\Surveys\OnboardingCsatSurvey;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    SimpleSurveys::register(OnboardingCsatSurvey::class);

    Gate::define('viewSimpleSurveys', fn ($user): bool => (bool) $user->is_admin);
}
```

### Step 3: Mount the Widget in Your Layout

```blade
<x-simple-surveys::widget position="bottom-right" theme="auto" />
```

When a user dismisses the survey, it disappears for 7 days (`Remind me later`). If they submit, Slack receives a formatted Block Kit card with their answers and a direct link to `/simple-surveys/admin/onboarding-csat`.

---

## Recipe 2: Multi-Language SaaS Survey with Cohort Tracking & CSV Export

**Goal:** Serve a single survey in English, French, and German, attach the user's billing plan and PostHog `distinct_id` to every submission, and export clean segmented CSVs.

### Step 1: Configure Locales & Cookies

```php
// config/simple-surveys.php
'translations' => [
    'file' => 'simple-surveys',
    'locales' => ['fr', 'de'],
],

'tracking' => [
    'impressions' => true,
    'cookies' => [
        'distinct_id' => 'ph_distinct_id',
    ],
],
```

Exclude the frontend analytics cookie from Laravel encryption in `bootstrap/app.php`:

```php
$middleware->encryptCookies(except: ['ph_distinct_id']);
```

### Step 2: Snapshot User Traits & Forward Browser Events

```php
// app/Providers/AppServiceProvider.php
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use Illuminate\Http\Request;

SimpleSurveys::resolveTrackingContext(fn (Request $request, $user, $survey): array => [
    'plan' => $user->plan,
    'team_size' => $user->team?->members()->count(),
]);
```

```js
// resources/js/app.js
window.addEventListener('simple-surveys:submitted', ({ detail }) => {
    window.posthog?.capture('survey_submitted', detail);
});
```

### Step 3: Scaffold & Translate Language Files

```bash
php artisan vendor:publish --tag="simple-surveys-translations"
php artisan simple-surveys:translations
```

Translate `lang/fr/simple-surveys.php`, `lang/de/simple-surveys.php`, and `lang/vendor/simple-surveys/{fr,de}/widget.php`. When you click **Export CSV** in `/simple-surveys/admin`, the exported file automatically includes `Locale`, `Duration (ms)`, `distinct_id`, `plan`, and `team_size` columns alongside canonical answer values.

---

## Recipe 3: Inertia.js / Livewire SPA with Path-Targeted Surveys

**Goal:** Show a billing survey *only* when a user navigates to `/billing/*` inside an Inertia or Livewire `wire:navigate` app—without full page reloads.

### Step 1: Target by Path

```php
Survey::make('billing-ux')
    ->title('Billing Portal Feedback')
    ->banner('Quick question about our new billing page')
    ->onPaths('/billing/*')
    ->exceptPaths('/billing/checkout/*')
    ->questions([
        Question::rating('clarity', 'How clear are your invoices and usage?')
            ->scale(1, 5)
            ->required(),
    ]);
```

### Step 2: Mount in `dynamic` Mode

```blade
{{-- resources/views/app.blade.php (Inertia) --}}
<x-simple-surveys::widget dynamic position="bottom-right" theme="auto" />

{{-- Livewire wire:navigate layout --}}
@persist('simple-surveys')
    <x-simple-surveys::widget dynamic position="bottom-right" theme="auto" />
@endpersist
```

Every `inertia:navigate` or `livewire:navigated` event refreshes eligibility for the new `window.location.pathname`. To re-check immediately after an in-page mutation (for instance, right after upgrading a plan), dispatch:

```js
window.dispatchEvent(new CustomEvent('simple-surveys:refresh'));
```

---

## Recipe 4: Styling Two Surveys Differently Without Touching Blade

**Goal:** Keep a clean brand default globally while giving a high-priority product launch survey a centered modal layout and custom accent color.

```bash
php artisan vendor:publish --tag="simple-surveys-styles"
```

```css
/* resources/css/simple-surveys.css */
.lss-root {
    --lss-accent: #10b981;
    --lss-accent-hover: #059669;
    --lss-radius: 14px;
}

[data-lss-survey="v2-launch-survey"] {
    --lss-accent: #6366f1;
    --lss-accent-hover: #4f46e5;
    --lss-card-width: 460px;
}
```

Or adjust `v2-launch-survey` visually in `/simple-surveys/admin/v2-launch-survey/appearance` (setting **Widget Placement** to `Center (Modal)` and picking the `Sparkles` banner icon) with zero deploy required.

---

← Previous: [Configuration & Artisan Commands](configuration.md) | [Back to README →](../README.md)
