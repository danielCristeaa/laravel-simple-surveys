[← Back to README](../README.md)

# Defining Surveys

Laravel Simple Surveys is code-first: surveys, questions, validation rules, and conditional follow-ups live in version-controlled PHP rather than a database-only form builder.

---

## Registering Surveys

Register surveys in `AppServiceProvider::boot()` (or a dedicated service provider) using the `SimpleSurveys` facade:

```php
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;

SimpleSurveys::register(
    Survey::make('onboarding-feedback')
        ->title('Quick Onboarding Survey')
        ->banner('Got 30 seconds? Help us improve onboarding!')
        ->description('Your feedback directly shapes what we build next.')
        ->submitLabel('Send Feedback')
        ->thankYouMessage('Thanks! Your answers were saved.')
        ->priority(10)
        ->questions([
            Question::rating('experience', 'How would you rate your experience?')
                ->scale(1, 5)
                ->required(),
            Question::select('source', 'How did you hear about us?')
                ->options(['Google', 'Friend', 'Social Media', 'Other'])
                ->required(),
            Question::textarea('notes', 'Anything we can improve?')
                ->maxLength(1000),
        ])
);
```

### Class-Based Survey Definitions

For larger applications, move each survey into its own class implementing `DanielCristea\SimpleSurveys\Contracts\SurveyDefinition`:

```php
namespace App\Surveys;

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
                Question::rating('score', 'Overall experience')
                    ->scale(1, 5)
                    ->required(),
            ]);
    }
}
```

Register the class (resolved via Laravel's service container, so constructor DI works out of the box):

```php
SimpleSurveys::register(OnboardingFeedbackSurvey::class);
```

---

## `Survey` Fluent API Reference

| Method | Parameters | Description |
| :--- | :--- | :--- |
| `Survey::make($key)` | `string $key` | Creates a survey with a unique key (default title is `Str::headline($key)`). |
| `->title($title)` | `string` | Heading shown in the survey card and Admin Dashboard. |
| `->banner($text)` | `string` | Prompt shown in the collapsed widget banner (defaults to `title`). |
| `->description($text)` | `?string` | Optional subtitle shown under the card heading. |
| `->icon($icon)` | `WidgetIcon\|WidgetIconPreset\|string\|null` | Sets a per-survey banner icon preset, `'none'`, or relative `public/` `.png`/`.svg` path. |
| `->withoutIcon()` | — | Hides the banner icon for this survey. |
| `->submitLabel($label)` | `string` | Custom submit button label (defaults to translated `'Submit'`). |
| `->thankYouMessage($msg)` | `string` | Message shown after submission (defaults to translated `'Thank you for your feedback!'`). |
| `->startsAt($date)` | `CarbonInterface\|string\|null` | Schedule when the survey becomes active. |
| `->endsAt($date)` / `->until($date)` | `CarbonInterface\|string\|null` | Schedule when the survey closes automatically. |
| `->activeBetween($start, $end)` | `CarbonInterface\|string` | Shorthand for setting both `startsAt` and `endsAt`. |
| `->draft($draft = true)` | `bool` | Marks the survey as a draft (never eligible for respondents). |
| `->closed($closed = true)` | `bool` | Marks the survey as closed. |
| `->dismissible($dismissible = true)` | `bool` | Controls whether respondents can dismiss the survey (default `true`). |
| `->priority($priority)` | `int` | Higher priority surveys win when multiple surveys are eligible (default `0`). |
| `->questions($questions)` | `array<int, Question>` | Replaces all questions on the survey and validates condition references. |
| `->addQuestion($question)` | `Question` | Appends a single question (rejects duplicate keys and circular conditions). |

> See [Audience Targeting & Scheduling](targeting.md) for `->target()`, `->targetUser()`, `->onRoutes()`, `->onPaths()`, and `->repromptAfter*()` methods, and [Webhooks & Notifications](webhooks-and-notifications.md) for `->webhook()` and `->notify*()`.

---

## Question Types

All 8 built-in input types store typed values in the normalized `simple_survey_answers` table (`value`, `value_numeric`, `value_json`) so analytics aggregate natively in SQL.

| Factory Method | Input Type | Stored Column(s) | Typical Modifiers |
| :--- | :--- | :--- | :--- |
| `Question::text($key, $label)` | Single-line text | `value` | `->placeholder()`, `->maxLength()`, `->rules()` |
| `Question::textarea($key, $label)` | Multi-line text | `value` | `->placeholder()`, `->maxLength()`, `->rules()` |
| `Question::number($key, $label)` | Numeric input | `value`, `value_numeric` | `->min()`, `->max()`, `->step()`, `->placeholder()` |
| `Question::select($key, $label)` | Dropdown `<select>` | `value` | `->options()`, `->placeholder()` |
| `Question::radio($key, $label)` | Radio button group | `value` | `->options()` |
| `Question::checkbox($key, $label)` | Multi-select checkboxes | `value_json` | `->options()` |
| `Question::rating($key, $label)` | Numeric button scale (default `1–5`) | `value`, `value_numeric` | `->scale($min, $max)` |
| `Question::boolean($key, $label)` | Yes / No toggle | `value` (`'1'`/`'0'`), `value_numeric` | `->required()` |

### `Question` Modifier Reference

| Method | Parameters | Description |
| :--- | :--- | :--- |
| `->required($required = true)` | `bool` | Marks the question required during server-side validation. |
| `->optional()` | — | Explicitly marks the question optional (default). |
| `->helpText($text)` | `?string` | Helper copy displayed beneath the question label. |
| `->placeholder($text)` | `?string` | Placeholder text for `text`, `textarea`, `number`, and `select` inputs. |
| `->options($options)` | `array<int\|string, string>` | Accepts either a plain list (`['Google', 'Friend']`) or an associative key → label map (`['google' => 'Google']`). |
| `->min($min)` / `->max($max)` | `int\|float\|null` | Minimum / maximum numeric bounds. |
| `->step($step)` | `int\|float\|null` | Numeric step increment for `number` inputs. |
| `->scale($min, $max)` | `int, int` | Sets rating scale bounds (e.g. `->scale(1, 5)` or `->scale(0, 10)` for NPS). |
| `->maxLength($length)` | `?int` | Maximum character length for `text` / `textarea` questions. |
| `->rules($rules)` | `array<int, mixed>` | Extra Laravel validation rules merged into the question's ruleset. |

> **Tip (Associative Options):** Use stable string keys (`['google' => 'Google', 'friend' => 'Friend']`) when you plan to localize labels later—stored answers, `->when(...)` conditions, and analytics always reference the canonical option key.

---

## Conditional Follow-Up Questions

Show or hide follow-up questions dynamically based on earlier answers using `->when(...)` and its convenience helpers:

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

        Question::checkbox('features', 'Which features do you use?')
            ->options(['dashboard' => 'Dashboard', 'reports' => 'Reports', 'api' => 'API']),

        // Array membership check for checkbox questions
        Question::text('api_feedback', 'How can we improve our API?')
            ->whenContains('features', 'api'),
    ]);
```

### Condition Helpers & Operators

| Method / Operator | Description |
| :--- | :--- |
| `->when($key, $value)` | Shorthand for equality (`=`). |
| `->when($key, $operator, $value)` | Compares `$key` using one of: `=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not_in`, `contains`, `not_contains`. |
| `->whenIn($key, array $values)` | Visible when `$key`'s answer is in `$values`. |
| `->whenNotIn($key, array $values)` | Visible when `$key`'s answer is not in `$values`. |
| `->whenContains($key, $value)` | Visible when a checkbox answer array contains `$value` (or string contains substring). |
| `->whenNotContains($key, $value)` | Negation of `whenContains`. |

### Behavior Guarantees

- **Logical AND:** Chaining multiple `->when(...)` calls on a single question requires **all** conditions to match.
- **Cascading chains:** Multi-hop dependencies (`A → B → C`) cascade automatically—if `A` no longer matches, both `B` and `C` hide.
- **Validation safety:** Server-side validation skips hidden questions (even if marked `->required()`) and discards stale answers if a respondent changes an earlier answer before submitting.
- **Registration checks:** Self-referencing, missing-key, and circular conditions throw an `InvalidArgumentException` immediately at registration time.
- **Headless API:** `GET /simple-surveys/eligible` serializes each question's `conditions` array so custom SPAs can mirror the exact same visibility logic.

---

## Syncing Surveys to the Database

Surveys automatically sync their metadata and questions to the database on the **first impression, first open, first submission, first dismissal, or admin status/appearance change**.

If you want all code-registered surveys written to the database upfront (for example, in a deployment script), run:

```bash
php artisan simple-surveys:sync
```

Or call `SimpleSurveys::sync()` programmatically.

---

Next: [Audience Targeting & Scheduling →](targeting.md)
