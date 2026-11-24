<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Database\Eloquent\Builder;

it('builds a survey with sensible defaults', function (): void {
    $survey = Survey::make('onboarding-feedback');

    expect($survey->key())->toBe('onboarding-feedback')
        ->and($survey->getTitle())->toBe('Onboarding Feedback')
        ->and($survey->getBannerText())->toBe('Onboarding Feedback')
        ->and($survey->getDescription())->toBeNull()
        ->and($survey->getSubmitLabel())->toBe('Submit')
        ->and($survey->getThankYouMessage())->toBe('Thank you for your feedback!')
        ->and($survey->isDismissible())->toBeTrue()
        ->and($survey->getPriority())->toBe(0)
        ->and($survey->status())->toBe(SurveyStatus::Active)
        ->and($survey->isActive())->toBeTrue();
});

it('computes survey status from flags and date windows', function (): void {
    $now = CarbonImmutable::parse('2026-11-15 12:00:00');

    $draft = Survey::make('draft-survey')->draft();
    $closed = Survey::make('closed-survey')->closed();
    $scheduled = Survey::make('future-survey')->startsAt('2026-12-01 00:00:00');
    $expired = Survey::make('past-survey')->until('2026-11-01 00:00:00');
    $running = Survey::make('running-survey')->activeBetween('2026-11-01 00:00:00', '2026-11-30 23:59:59');

    expect($draft->status($now))->toBe(SurveyStatus::Draft)
        ->and($closed->status($now))->toBe(SurveyStatus::Closed)
        ->and($scheduled->status($now))->toBe(SurveyStatus::Scheduled)
        ->and($expired->status($now))->toBe(SurveyStatus::Closed)
        ->and($running->status($now))->toBe(SurveyStatus::Active)
        ->and($running->isActive($now))->toBeTrue()
        ->and($expired->isActive($now))->toBeFalse();
});

it('registers questions and guards against duplicate keys', function (): void {
    $survey = Survey::make('product-pulse')
        ->title('Product Pulse')
        ->banner('Quick 2-question survey!')
        ->target(function (Builder $query): void {
            $query->where('plan', 'pro');
        })
        ->questions([
            Question::rating('score', 'Score')->required(),
            Question::textarea('notes', 'Notes')->maxLength(500),
        ]);

    expect($survey->getQuestions())->toHaveCount(2)
        ->and($survey->question('score')?->label())->toBe('Score')
        ->and($survey->question('missing'))->toBeNull()
        ->and($survey->getTargetQueryCallback())->not->toBeNull();

    expect(fn () => $survey->addQuestion(Question::text('score', 'Duplicate')))
        ->toThrow(InvalidArgumentException::class);
});

it('throws when survey key is empty', function (): void {
    expect(fn () => Survey::make(''))
        ->toThrow(InvalidArgumentException::class);
});

it('configures reprompt cooldowns and evaluates dismissal expiration accurately', function (): void {
    $dismissedAt = CarbonImmutable::parse('2026-11-01 12:00:00');

    $neverReprompt = Survey::make('once');
    expect($neverReprompt->getRepromptAfter())->toBeNull()
        ->and($neverReprompt->isDismissalExpired($dismissedAt, $dismissedAt->addYear()))->toBeFalse();

    $byDays = Survey::make('days')->repromptAfterDays(14);
    expect($byDays->getRepromptAfter()?->totalDays)->toEqual(14)
        ->and($byDays->isDismissalExpired($dismissedAt, $dismissedAt->addDays(13)->addHours(23)))->toBeFalse()
        ->and($byDays->isDismissalExpired($dismissedAt, $dismissedAt->addDays(14)))->toBeTrue()
        ->and($byDays->isDismissalExpired($dismissedAt, $dismissedAt->addDays(20)))->toBeTrue();

    $byHours = Survey::make('hours')->repromptAfterHours(6);
    expect($byHours->isDismissalExpired($dismissedAt, $dismissedAt->addHours(5)))->toBeFalse()
        ->and($byHours->isDismissalExpired($dismissedAt, $dismissedAt->addHours(6)))->toBeTrue();

    $byMinutes = Survey::make('minutes')->repromptAfterMinutes(30);
    expect($byMinutes->isDismissalExpired($dismissedAt, $dismissedAt->addMinutes(29)))->toBeFalse()
        ->and($byMinutes->isDismissalExpired($dismissedAt, $dismissedAt->addMinutes(30)))->toBeTrue();

    $byInterval = Survey::make('interval')->repromptAfter(CarbonInterval::weeks(1));
    expect($byInterval->isDismissalExpired($dismissedAt, $dismissedAt->addDays(6)))->toBeFalse()
        ->and($byInterval->isDismissalExpired($dismissedAt, $dismissedAt->addDays(7)))->toBeTrue();

    $cleared = Survey::make('cleared')->repromptAfter(5)->repromptAfter(null);
    expect($cleared->getRepromptAfter())->toBeNull();

    expect(fn () => Survey::make('bad-days')->repromptAfterDays(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Survey::make('bad-hours')->repromptAfterHours(-1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Survey::make('bad-minutes')->repromptAfterMinutes(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Survey::make('bad-interval')->repromptAfter(CarbonInterval::seconds(0)))->toThrow(InvalidArgumentException::class);
});
