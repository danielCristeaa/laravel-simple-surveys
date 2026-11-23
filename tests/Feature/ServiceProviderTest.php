<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Facades\SimpleSurveys as SimpleSurveysFacade;
use DanielCristea\SimpleSurveys\SimpleSurveys;

it('loads default package configuration', function (): void {
    expect(config('simple-surveys.tables'))->toBe([
        'surveys' => 'simple_surveys',
        'questions' => 'simple_survey_questions',
        'responses' => 'simple_survey_responses',
        'answers' => 'simple_survey_answers',
        'dismissals' => 'simple_survey_dismissals',
    ])
        ->and(config('simple-surveys.respondent_key_type'))->toBe('id')
        ->and(config('simple-surveys.guard'))->toBeNull()
        ->and(config('simple-surveys.routes.enabled'))->toBeTrue()
        ->and(config('simple-surveys.admin.enabled'))->toBeTrue()
        ->and(config('simple-surveys.admin.gate'))->toBe('viewSimpleSurveys');
});

it('resolves the simple surveys singleton and facade', function (): void {
    expect(app(SimpleSurveys::class))->toBeInstanceOf(SimpleSurveys::class)
        ->and(SimpleSurveysFacade::getFacadeRoot())->toBe(app(SimpleSurveys::class));
});
