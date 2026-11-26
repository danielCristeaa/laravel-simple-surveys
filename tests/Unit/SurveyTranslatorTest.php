<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Services\SurveyTranslator;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempLangPath = $this->useIsolatedLangPath();
});

it('resolves translations from lang/{locale}/simple-surveys.php with regional and fallback resolution', function (): void {
    File::ensureDirectoryExists($this->tempLangPath.'/en');
    File::ensureDirectoryExists($this->tempLangPath.'/fr');

    File::put($this->tempLangPath.'/en/simple-surveys.php', <<<'PHP'
<?php

return [
    'nps.v2' => [
        'title' => 'English File Title',
        'banner' => 'English File Banner',
        'description' => 'English File Description',
        'submit_label' => 'Send Now',
        'thank_you_message' => 'Thanks from EN file!',
        'questions' => [
            'source' => [
                'label' => 'Where did you hear about us?',
                'help_text' => 'Pick one option.',
                'placeholder' => 'Choose...',
                'options' => [
                    'other.blog' => 'Other (e.g. blog)',
                    'friend' => 'Friend',
                ],
            ],
        ],
    ],
];
PHP);

    File::put($this->tempLangPath.'/fr/simple-surveys.php', <<<'PHP'
<?php

return [
    'nps.v2' => [
        'title' => 'Titre en français',
        'banner' => '',
        'description' => 'Description en français',
        'submit_label' => 'Envoyer maintenant',
        'thank_you_message' => 'Merci beaucoup !',
        'questions' => [
            'source' => [
                'label' => 'Comment nous avez-vous connus ?',
                'help_text' => 'Choisissez une option.',
                'placeholder' => 'Sélectionnez...',
                'options' => [
                    'other.blog' => 'Autre (ex. blog)',
                ],
            ],
        ],
    ],
];
PHP);

    app('translator')->setLoaded([]);

    $survey = Survey::make('nps.v2')
        ->title('PHP Default Title')
        ->banner('PHP Default Banner')
        ->description('PHP Default Description')
        ->submitLabel('PHP Submit')
        ->thankYouMessage('PHP Thanks')
        ->questions([
            Question::select('source', 'PHP Source')
                ->helpText('PHP Help')
                ->placeholder('PHP Placeholder')
                ->options([
                    'other.blog' => 'PHP Other',
                    'friend' => 'PHP Friend',
                ]),
        ]);

    $question = $survey->getQuestions()[0];
    $translator = app(SurveyTranslator::class);

    // French: translated strings win; empty 'banner' and missing 'friend' fall back to EN file; dotted keys work
    expect($translator->title($survey, 'fr'))->toBe('Titre en français')
        ->and($translator->bannerText($survey, 'fr'))->toBe('English File Banner')
        ->and($translator->description($survey, 'fr'))->toBe('Description en français')
        ->and($translator->submitLabel($survey, 'fr'))->toBe('Envoyer maintenant')
        ->and($translator->thankYouMessage($survey, 'fr'))->toBe('Merci beaucoup !')
        ->and($translator->questionLabel($survey, $question, 'fr'))->toBe('Comment nous avez-vous connus ?')
        ->and($translator->questionHelpText($survey, $question, 'fr'))->toBe('Choisissez une option.')
        ->and($translator->questionPlaceholder($survey, $question, 'fr'))->toBe('Sélectionnez...')
        ->and($translator->questionOptions($survey, $question, 'fr'))->toBe([
            'other.blog' => 'Autre (ex. blog)',
            'friend' => 'Friend',
        ]);

    // Regional locale fr_CA falls back to fr before en
    expect($translator->title($survey, 'fr_CA'))->toBe('Titre en français')
        ->and($translator->questionLabel($survey, $question, 'fr_CA'))->toBe('Comment nous avez-vous connus ?');

    // Untranslated locale de falls back to fallback locale en
    expect($translator->title($survey, 'de'))->toBe('English File Title');
});

it('uses widget UI defaults for non-customized submit and thank-you strings and translated title for default banner', function (): void {
    File::ensureDirectoryExists($this->tempLangPath.'/vendor/simple-surveys/fr');
    File::ensureDirectoryExists($this->tempLangPath.'/vendor/simple-surveys/fr_CA');
    File::ensureDirectoryExists($this->tempLangPath.'/fr');

    File::put($this->tempLangPath.'/vendor/simple-surveys/fr/widget.php', <<<'PHP'
<?php

return [
    'take_survey' => 'Répondre',
    'yes' => 'Oui',
    'no' => 'Non',
    'submit' => 'Envoyer',
    'thank_you' => 'Merci pour votre retour !',
    'errors' => [
        'not_found' => 'Sondage introuvable.',
    ],
];
PHP);

    File::put($this->tempLangPath.'/vendor/simple-surveys/fr_CA/widget.php', <<<'PHP'
<?php

return [
    'take_survey' => 'Participer',
];
PHP);

    File::put($this->tempLangPath.'/fr/simple-surveys.php', <<<'PHP'
<?php

return [
    'minimal' => [
        'title' => 'Sondage rapide',
    ],
];
PHP);

    app('translator')->setLoaded([]);

    $survey = Survey::make('minimal')->title('Quick Survey');
    $translator = app(SurveyTranslator::class);

    expect($survey->hasCustomBannerText())->toBeFalse()
        ->and($survey->hasCustomSubmitLabel())->toBeFalse()
        ->and($survey->hasCustomThankYouMessage())->toBeFalse()
        ->and($translator->title($survey, 'fr'))->toBe('Sondage rapide')
        ->and($translator->bannerText($survey, 'fr'))->toBe('Sondage rapide')
        ->and($translator->submitLabel($survey, 'fr'))->toBe('Envoyer')
        ->and($translator->thankYouMessage($survey, 'fr'))->toBe('Merci pour votre retour !')
        ->and($translator->description($survey, 'fr'))->toBeNull()
        // Regional locale fr_CA uses specific fr_CA override when present, falls back to fr, then en
        ->and($translator->ui('take_survey', 'fr_CA'))->toBe('Participer')
        ->and($translator->ui('yes', 'fr_CA'))->toBe('Oui')
        ->and($translator->ui('no', 'fr_CA'))->toBe('Non')
        ->and($translator->ui('errors.not_found', 'fr_CA'))->toBe('Sondage introuvable.')
        ->and($translator->ui('minimize_title', 'fr_CA'))->toBe('Minimize')
        ->and($translator->submitLabel($survey, 'fr_CA'))->toBe('Envoyer')
        ->and($translator->thankYouMessage($survey, 'fr_CA'))->toBe('Merci pour votre retour !');
});

it('extracts canonical translatable fields cleanly and supports custom translation file names', function (): void {
    config()->set('simple-surveys.translations.file', 'custom-surveys');

    $survey = Survey::make('checkout')
        ->title('Checkout Survey')
        ->banner('Quick question')
        ->description('Tell us about checkout.')
        ->submitLabel('Finish')
        ->thankYouMessage('Done!')
        ->questions([
            Question::radio('speed', 'How fast was checkout?')
                ->helpText('Be honest.')
                ->options(['fast' => 'Fast', 'slow' => 'Slow']),
            Question::text('notes', 'Notes')
                ->placeholder('Optional...'),
        ]);

    $translator = app(SurveyTranslator::class);

    expect($translator->translationFile())->toBe('custom-surveys')
        ->and($translator->extract($survey))->toBe([
            'title' => 'Checkout Survey',
            'banner' => 'Quick question',
            'description' => 'Tell us about checkout.',
            'submit_label' => 'Finish',
            'thank_you_message' => 'Done!',
            'questions' => [
                'speed' => [
                    'label' => 'How fast was checkout?',
                    'help_text' => 'Be honest.',
                    'options' => [
                        'fast' => 'Fast',
                        'slow' => 'Slow',
                    ],
                ],
                'notes' => [
                    'label' => 'Notes',
                    'placeholder' => 'Optional...',
                ],
            ],
        ]);
});
