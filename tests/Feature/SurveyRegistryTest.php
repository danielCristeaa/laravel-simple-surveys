<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\SampleSurveyDefinition;

it('registers surveys and sorts them by priority descending', function (): void {
    SimpleSurveys::register(Survey::make('low')->priority(1));
    SimpleSurveys::register(Survey::make('high')->priority(20));
    SimpleSurveys::register(SampleSurveyDefinition::class);
    SimpleSurveys::register(Survey::make('tie-second')->priority(1));

    expect(SimpleSurveys::all()->keys()->all())->toBe([
        'high',
        'class-defined-survey',
        'low',
        'tie-second',
    ]);

    expect(SimpleSurveys::has('high'))->toBeTrue()
        ->and(SimpleSurveys::has('unknown'))->toBeFalse()
        ->and(SimpleSurveys::find('class-defined-survey')?->getTitle())->toBe('Class Defined Survey');
});

it('filters active surveys cleanly', function (): void {
    SimpleSurveys::register(Survey::make('active-one'));
    SimpleSurveys::register(Survey::make('draft-one')->draft());
    SimpleSurveys::register(Survey::make('closed-one')->closed());

    expect(SimpleSurveys::active()->keys()->all())->toBe(['active-one']);
});

it('rejects classes that do not implement SurveyDefinition', function (): void {
    /** @phpstan-ignore-next-line */
    expect(fn () => SimpleSurveys::register(stdClass::class))
        ->toThrow(InvalidArgumentException::class);
});
