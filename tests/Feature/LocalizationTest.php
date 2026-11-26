<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\SimpleSurveysServiceProvider;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

beforeEach(function (): void {
    $this->tempLangPath = $this->useIsolatedLangPath();
    app()->setLocale('en');
});

afterEach(function (): void {
    app()->setLocale('en');
});

it('registers publishable widget translations under simple-surveys-translations tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(SimpleSurveysServiceProvider::class, 'simple-surveys-translations');

    expect($paths)->toHaveCount(1)
        ->and(is_dir((string) array_key_first($paths)))->toBeTrue()
        ->and(is_file(array_key_first($paths).'/en/widget.php'))->toBeTrue();
});

it('renders translated widget copy, built-in widget chrome, and eligible json at request time', function (): void {
    File::ensureDirectoryExists($this->tempLangPath.'/vendor/simple-surveys/fr');
    File::ensureDirectoryExists($this->tempLangPath.'/fr');

    File::put($this->tempLangPath.'/vendor/simple-surveys/fr/widget.php', <<<'PHP'
<?php

return [
    'take_survey' => 'Répondre',
    'dismiss_aria_label' => 'Ignorer le sondage',
    'dismiss_title' => 'Ignorer',
    'close_aria_label' => 'Fermer le sondage',
    'minimize_title' => 'Réduire',
    'close_title' => 'Fermer',
    'select_placeholder' => 'Choisissez une option...',
    'yes' => 'Oui',
    'no' => 'Non',
    'remind_me_later' => 'Me le rappeler plus tard',
    'dont_show_again' => 'Ne plus afficher',
    'submit' => 'Envoyer',
    'submitting' => 'Envoi en cours...',
    'thank_you' => 'Merci pour votre retour !',
    'generic_error' => 'Une erreur est survenue.',
    'network_error' => 'Erreur réseau.',
];
PHP);

    File::put($this->tempLangPath.'/fr/simple-surveys.php', <<<'PHP'
<?php

return [
    'product-i18n' => [
        'title' => 'Votre avis compte',
        'banner' => 'Vous avez 30 secondes ?',
        'description' => 'Aidez-nous à nous améliorer.',
        'questions' => [
            'source' => [
                'label' => 'Comment nous avez-vous connus ?',
                'help_text' => 'Sélectionnez une réponse.',
                'options' => [
                    'google' => 'Moteur de recherche',
                    'friend' => 'Un ami',
                ],
            ],
            'recommend' => [
                'label' => 'Nous recommanderiez-vous ?',
            ],
        ],
    ],
];
PHP);

    app('translator')->setLoaded([]);

    $user = User::create(['name' => 'Claire', 'email' => 'claire@example.com']);

    // Registered in default English locale (simulating AppServiceProvider::boot)
    SimpleSurveys::register(
        Survey::make('product-i18n')
            ->title('Your Feedback Matters')
            ->banner('Got 30 seconds?')
            ->description('Help us improve.')
            ->repromptAfterDays(7)
            ->questions([
                Question::radio('source', 'How did you hear about us?')
                    ->helpText('Select one answer.')
                    ->options(['google' => 'Search engine', 'friend' => 'A friend'])
                    ->required(),
                Question::boolean('recommend', 'Would you recommend us?')
                    ->required(),
            ])
    );

    // Switch locale after registration (simulating SetLocale middleware)
    app()->setLocale('fr');
    $this->actingAs($user);

    $html = Blade::render('<x-simple-surveys::widget />');

    expect($html)
        ->toContain('Vous avez 30 secondes ?')
        ->toContain('Répondre')
        ->toContain('Votre avis compte')
        ->toContain('Aidez-nous à nous améliorer.')
        ->toContain('Comment nous avez-vous connus ?')
        ->toContain('Sélectionnez une réponse.')
        ->toContain('value="google"')
        ->toContain('Moteur de recherche')
        ->toContain('value="friend"')
        ->toContain('Un ami')
        ->toContain('Nous recommanderiez-vous ?')
        ->toContain('Oui')
        ->toContain('Non')
        ->toContain('Me le rappeler plus tard')
        ->toContain('Envoyer')
        ->toContain('Merci pour votre retour !')
        ->toContain('Envoi en cours...')
        ->not->toContain('Your Feedback Matters')
        ->not->toContain('Take Survey');

    // Regional locale fr_CA falls back to fr for BOTH survey strings and built-in widget chrome
    app()->setLocale('fr_CA');

    $regionalHtml = Blade::render('<x-simple-surveys::widget />');

    expect($regionalHtml)
        ->toContain('Vous avez 30 secondes ?')
        ->toContain('Répondre')
        ->toContain('Votre avis compte')
        ->toContain('Oui')
        ->toContain('Non')
        ->toContain('Me le rappeler plus tard')
        ->toContain('Envoyer')
        ->toContain('Merci pour votre retour !')
        ->not->toContain('Take Survey')
        ->not->toContain('Remind me later');

    app()->setLocale('fr');

    // Headless JSON API also resolves translations in the active locale while keeping option keys canonical
    $this->actingAs($user)
        ->getJson(route('simple-surveys.eligible'))
        ->assertOk()
        ->assertJson([
            'survey' => [
                'key' => 'product-i18n',
                'title' => 'Votre avis compte',
                'banner_text' => 'Vous avez 30 secondes ?',
                'description' => 'Aidez-nous à nous améliorer.',
                'submit_label' => 'Envoyer',
                'thank_you_message' => 'Merci pour votre retour !',
                'questions' => [
                    [
                        'key' => 'source',
                        'label' => 'Comment nous avez-vous connus ?',
                        'help_text' => 'Sélectionnez une réponse.',
                        'options' => [
                            'google' => 'Moteur de recherche',
                            'friend' => 'Un ami',
                        ],
                    ],
                    [
                        'key' => 'recommend',
                        'label' => 'Nous recommanderiez-vous ?',
                    ],
                ],
            ],
        ]);
});

it('uses translated validation labels, returns translated thank-you message, records respondent locale, and keeps canonical DB sync', function (): void {
    Gate::define('viewSimpleSurveys', static fn (User $user): bool => $user->is_admin);

    File::ensureDirectoryExists($this->tempLangPath.'/vendor/simple-surveys/fr');
    File::ensureDirectoryExists($this->tempLangPath.'/fr');

    File::put($this->tempLangPath.'/vendor/simple-surveys/fr/widget.php', <<<'PHP'
<?php

return [
    'thank_you' => 'Merci pour votre réponse !',
];
PHP);

    File::put($this->tempLangPath.'/fr/simple-surveys.php', <<<'PHP'
<?php

return [
    'global-nps' => [
        'title' => 'Sondage NPS',
        'questions' => [
            'source' => [
                'label' => 'Origine de découverte',
                'options' => [
                    'google' => 'Google FR',
                    'friend' => 'Ami',
                ],
            ],
        ],
    ],
];
PHP);

    app('translator')->setLoaded([]);

    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $frenchUser = User::create(['name' => 'Marie', 'email' => 'marie@example.com']);
    $englishUser = User::create(['name' => 'John', 'email' => 'john@example.com']);

    SimpleSurveys::register(
        Survey::make('global-nps')
            ->title('Global NPS')
            ->questions([
                Question::radio('source', 'Discovery Source')
                    ->options(['google' => 'Google', 'friend' => 'Friend'])
                    ->required(),
            ])
    );

    // 1. French validation error uses translated question label
    app()->setLocale('fr');

    $validationResponse = $this->actingAs($frenchUser)
        ->postJson(route('simple-surveys.responses.store', 'global-nps'), [
            'answers' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['answers.source']);

    expect((string) ($validationResponse->json('errors')['answers.source'][0] ?? ''))
        ->toContain('Origine de découverte');

    // 2. French submission returns translated thank-you message and records locale = 'fr'
    $this->actingAs($frenchUser)
        ->postJson(route('simple-surveys.responses.store', 'global-nps'), [
            'answers' => ['source' => 'google'],
            'url' => '/fr/pricing',
        ])
        ->assertCreated()
        ->assertJson([
            'message' => 'Merci pour votre réponse !',
            'survey_key' => 'global-nps',
        ]);

    // 3. English submission records locale = 'en'
    app()->setLocale('en');

    $this->actingAs($englishUser)
        ->postJson(route('simple-surveys.responses.store', 'global-nps'), [
            'answers' => ['source' => 'friend'],
            'url' => '/pricing',
        ])
        ->assertCreated()
        ->assertJson([
            'message' => 'Thank you for your feedback!',
        ]);

    // 4. Canonical DB records remain in PHP-defined English (never overwritten by French submission)
    $record = SurveyRecord::query()->byKey('global-nps')->with('questions')->firstOrFail();

    expect($record->title)->toBe('Global NPS')
        ->and($record->questions->first()?->label)->toBe('Discovery Source')
        ->and($record->questions->first()?->options)->toBe(['google' => 'Google', 'friend' => 'Friend']);

    $frenchResponse = SurveyResponse::query()->forSurvey($record)->forRespondent($frenchUser)->firstOrFail();
    $englishResponse = SurveyResponse::query()->forSurvey($record)->forRespondent($englishUser)->firstOrFail();

    expect($frenchResponse->metadata['locale'] ?? null)->toBe('fr')
        ->and($englishResponse->metadata['locale'] ?? null)->toBe('en');

    // 5. Admin Dashboard shows recorded locales in responses table (while staying English)
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'global-nps'))
        ->assertOk()
        ->assertSee('Global NPS')
        ->assertSee('Locale')
        ->assertSee('<code>fr</code>', false)
        ->assertSee('<code>en</code>', false);

    // 6. CSV export includes Locale column with both respondent locales
    $csv = $this->actingAs($admin)
        ->get(route('simple-surveys.admin.export', 'global-nps'))
        ->assertOk()
        ->streamedContent();

    expect($csv)
        ->toContain('"Response ID","Respondent Type","Respondent ID","Submitted At","Page URL","Discovery Source",Locale')
        ->toContain('/fr/pricing,google,fr')
        ->toContain('/pricing,friend,en');
});

it('scaffolds and non-destructively merges translation files via simple-surveys:translations command', function (): void {
    config()->set('simple-surveys.translations.locales', ['fr', 'de']);

    SimpleSurveys::register(
        Survey::make('onboarding')
            ->title('Onboarding Survey')
            ->banner('Got 30s?')
            ->description('Help us improve onboarding.')
            ->submitLabel('Send')
            ->thankYouMessage('Thanks!')
            ->questions([
                Question::radio('source', 'How did you hear about us?')
                    ->helpText('Choose one.')
                    ->options(['google' => 'Google', 'friend' => 'Friend']),
                Question::text('notes', 'Notes')
                    ->placeholder('Anything else?'),
            ])
    );

    // First run: generates en, fr, and de files from config locales
    $this->artisan('simple-surveys:translations')
        ->assertSuccessful();

    $enPath = $this->tempLangPath.'/en/simple-surveys.php';
    $frPath = $this->tempLangPath.'/fr/simple-surveys.php';
    $dePath = $this->tempLangPath.'/de/simple-surveys.php';

    expect(File::exists($enPath))->toBeTrue()
        ->and(File::exists($frPath))->toBeTrue()
        ->and(File::exists($dePath))->toBeTrue();

    $enData = require $enPath;
    $frData = require $frPath;

    expect($enData['onboarding']['title'])->toBe('Onboarding Survey')
        ->and($enData['onboarding']['questions']['source']['options'])->toBe(['google' => 'Google', 'friend' => 'Friend'])
        ->and($frData)->toBe($enData);

    // Translator edits French file
    File::put($frPath, <<<'PHP'
<?php

return [
    'onboarding' => [
        'title' => 'Sondage de bienvenue',
        'banner' => 'Vous avez 30s ?',
        'description' => 'Aidez-nous.',
        'submit_label' => 'Envoyer',
        'thank_you_message' => 'Merci !',
        'questions' => [
            'source' => [
                'label' => 'Comment nous avez-vous connus ?',
                'help_text' => 'Choisissez une option.',
                'options' => [
                    'google' => 'Moteur Google',
                    'friend' => 'Un ami',
                ],
            ],
            'notes' => [
                'label' => 'Remarques',
                'placeholder' => 'Autre chose ?',
            ],
        ],
    ],
];
PHP);

    // Developer updates survey in PHP: adds a new question & option, removes 'notes'
    SimpleSurveys::clear();
    SimpleSurveys::register(
        Survey::make('onboarding')
            ->title('Updated Onboarding Survey')
            ->banner('Got 30s?')
            ->description('Help us improve onboarding.')
            ->submitLabel('Send')
            ->thankYouMessage('Thanks!')
            ->questions([
                Question::radio('source', 'How did you hear about us?')
                    ->helpText('Choose one.')
                    ->options(['google' => 'Google', 'friend' => 'Friend', 'podcast' => 'Podcast']),
                Question::rating('score', 'Overall score'),
            ])
    );

    // Re-run with --empty: source file updates to new PHP copy; fr preserves existing translations and adds new keys empty
    $this->artisan('simple-surveys:translations', ['--empty' => true])
        ->assertSuccessful();

    $enUpdated = require $enPath;
    $frMerged = require $frPath;

    expect($enUpdated['onboarding']['title'])->toBe('Updated Onboarding Survey')
        ->and($enUpdated['onboarding']['questions'])->toHaveKey('score')
        ->and($frMerged['onboarding']['title'])->toBe('Sondage de bienvenue')
        ->and($frMerged['onboarding']['questions']['source']['options']['google'])->toBe('Moteur Google')
        ->and($frMerged['onboarding']['questions']['source']['options']['podcast'])->toBe('')
        ->and($frMerged['onboarding']['questions']['score']['label'])->toBe('')
        ->and($frMerged['onboarding']['questions'])->toHaveKey('notes');

    // Re-run with --prune: removes stale 'notes' question while preserving existing translations
    $this->artisan('simple-surveys:translations', ['--prune' => true])
        ->assertSuccessful();

    $frPruned = require $frPath;

    expect($frPruned['onboarding']['title'])->toBe('Sondage de bienvenue')
        ->and($frPruned['onboarding']['questions'])->not->toHaveKey('notes')
        ->and($frPruned['onboarding']['questions']['score']['label'])->toBe('Overall score');
});

it('validates command flags for simple-surveys:translations', function (): void {
    SimpleSurveys::register(Survey::make('only-one')->title('Only One'));

    // Unknown --survey fails
    $this->artisan('simple-surveys:translations', ['--survey' => 'missing'])
        ->assertFailed();

    // Unsafe locale code fails
    $this->artisan('simple-surveys:translations', ['--locales' => '../bad'])
        ->assertFailed();

    // Valid --locales and --survey override succeeds
    $this->artisan('simple-surveys:translations', [
        '--survey' => 'only-one',
        '--locales' => 'es,pt_BR',
    ])->assertSuccessful();

    expect(File::exists($this->tempLangPath.'/es/simple-surveys.php'))->toBeTrue()
        ->and(File::exists($this->tempLangPath.'/pt_BR/simple-surveys.php'))->toBeTrue();
});
