[← Back to README](../README.md)

# Localization & Translatable Surveys

Single-language applications require zero translation configuration. For multi-language apps, Simple Surveys resolves translations lazily at request time using `app()->getLocale()` across two clean layers.

---

## Layer 1: Built-In Widget UI Strings

Publish the package's built-in widget language file (`Take Survey`, `Yes`, `No`, `Remind me later`, `Don't show again`, default `Submit`, default thank-you message, and validation/error labels) into `lang/vendor/simple-surveys/`:

```bash
php artisan vendor:publish --tag="simple-surveys-translations"
```

Create additional locale folders such as `lang/vendor/simple-surveys/fr/widget.php` or `lang/vendor/simple-surveys/de/widget.php` to translate the built-in widget chrome.

---

## Layer 2: Translating Survey Copy (`lang/{locale}/simple-surveys.php`)

Keep defining your surveys in clean PHP. To translate titles, banners, descriptions, submit labels, thank-you messages, question labels, help text, placeholders, and answer option labels, configure your target locales in `config/simple-surveys.php`:

```php
'translations' => [
    'file' => 'simple-surveys',
    'locales' => ['fr', 'de', 'es'],
],
```

Then scaffold and sync your Laravel language files from all registered surveys:

```bash
php artisan simple-surveys:translations
```

### Artisan Command Options

| Option | Default | Description |
| :--- | :--- | :--- |
| `--locales=` | `config('simple-surveys.translations.locales')` | Comma-separated target locale codes to scaffold/merge (e.g. `--locales=fr,de,es`). |
| `--source=` | `config('app.fallback_locale', 'en')` | Source locale code for canonical strings. |
| `--survey=` | All registered surveys | Export/update only a single registered survey key (e.g. `--survey=onboarding-feedback`). |
| `--empty` | `false` | Leave newly scaffolded target strings empty (`''`) instead of pre-filling source strings. |
| `--prune` | `false` | Remove survey or question keys no longer registered in code (ignored when `--survey=` is passed). |

Re-running `php artisan simple-surveys:translations` updates your source file (`lang/en/simple-surveys.php`) and merges new keys into target locale files **without overwriting existing translations**. Because it produces standard Laravel PHP translation arrays, it works out of the box with Git-connected translation platforms like **Crowdin**, **Lokalise**, **Phrase**, and **Weblate**.

### Translation File Structure

```php
// lang/fr/simple-surveys.php
return [
    'onboarding-feedback' => [
        'title' => 'Sondage de bienvenue',
        'banner' => 'Vous avez 30 secondes ?',
        'description' => 'Vos retours nous aident à améliorer le produit.',
        'submit_label' => 'Envoyer',
        'thank_you_message' => 'Merci pour votre retour !',
        'questions' => [
            'experience' => [
                'label' => 'Comment évaluez-vous votre expérience ?',
            ],
            'source' => [
                'label' => 'Comment nous avez-vous connus ?',
                'placeholder' => 'Choisissez une option...',
                'options' => [
                    'google' => 'Moteur de recherche',
                    'friend' => 'Un ami',
                    'other' => 'Autre',
                ],
            ],
        ],
    ],
];
```

---

## Analytics & Storage Guarantees

- **Option keys stay canonical:** Only option *labels* are translated—stored answers, `->when(...)` conditions, and Admin Dashboard breakdowns always aggregate cleanly across every language.
- **Canonical database sync:** `simple_surveys` and `simple_survey_questions` always store the canonical PHP copy rather than whichever locale the first respondent happened to use.
- **Automatic respondent `locale` recording:** Every submission automatically records `app()->getLocale()` in `SurveyResponse::$metadata['locale']`, the `SurveySubmitted` event `$context`, webhook payloads, the Admin Dashboard responses table, and CSV exports (`Locale` column).

---

← Previous: [Admin Results Dashboard](admin-dashboard.md) | Next: [Analytics, Events & Tracking Context →](analytics-and-tracking.md)
