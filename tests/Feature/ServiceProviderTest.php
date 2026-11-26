<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Facades\SimpleSurveys as SimpleSurveysFacade;
use DanielCristea\SimpleSurveys\SimpleSurveys;
use DanielCristea\SimpleSurveys\SimpleSurveysServiceProvider;
use Illuminate\Support\ServiceProvider;

it('loads default package configuration', function (): void {
    expect(config('simple-surveys.tables'))->toBe([
        'surveys' => 'simple_surveys',
        'questions' => 'simple_survey_questions',
        'responses' => 'simple_survey_responses',
        'answers' => 'simple_survey_answers',
        'dismissals' => 'simple_survey_dismissals',
        'opens' => 'simple_survey_opens',
        'impressions' => 'simple_survey_impressions',
    ])
        ->and(config('simple-surveys.respondent_key_type'))->toBe('id')
        ->and(config('simple-surveys.guard'))->toBeNull()
        ->and(config('simple-surveys.routes.enabled'))->toBeTrue()
        ->and(config('simple-surveys.tracking.impressions'))->toBeTrue()
        ->and(config('simple-surveys.tracking.cookies'))->toBe([])
        ->and(config('simple-surveys.webhooks.enabled'))->toBeFalse()
        ->and(config('simple-surveys.webhooks.queued'))->toBeTrue()
        ->and(config('simple-surveys.notifications.enabled'))->toBeFalse()
        ->and(config('simple-surveys.notifications.channels'))->toBe(['mail'])
        ->and(config('simple-surveys.notifications.queued'))->toBeTrue()
        ->and(config('simple-surveys.admin.enabled'))->toBeTrue()
        ->and(config('simple-surveys.admin.gate'))->toBe('viewSimpleSurveys')
        ->and(config('simple-surveys.admin.appearance_editor'))->toBeTrue();
});

it('resolves the simple surveys singleton and facade', function (): void {
    expect(app(SimpleSurveys::class))->toBeInstanceOf(SimpleSurveys::class)
        ->and(SimpleSurveysFacade::getFacadeRoot())->toBe(app(SimpleSurveys::class));
});

it('registers the publishable starter stylesheet under simple-surveys-styles tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(SimpleSurveysServiceProvider::class, 'simple-surveys-styles');

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths)[0])->toBe(resource_path('css/simple-surveys.css'))
        ->and(is_file(array_key_first($paths)))->toBeTrue();
});
