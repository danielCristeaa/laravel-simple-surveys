<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Enums\ResponseStatus;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Database\QueryException;

it('stores and retrieves completed survey responses with json answers', function (): void {
    $user = User::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $response = SurveyResponse::factory()
        ->forSurvey('onboarding-feedback')
        ->forRespondent($user)
        ->completed([
            'experience' => 5,
            'source' => 'Google',
            'feedback' => 'Super smooth!',
        ])
        ->create([
            'metadata' => ['url' => '/dashboard'],
        ]);

    $fresh = $response->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh?->survey_key)->toBe('onboarding-feedback')
        ->and($fresh?->status)->toBe(ResponseStatus::Completed)
        ->and($fresh?->isCompleted())->toBeTrue()
        ->and($fresh?->isDismissed())->toBeFalse()
        ->and($fresh?->answer('experience'))->toBe(5)
        ->and($fresh?->answer('missing', 'fallback'))->toBe('fallback')
        ->and($fresh?->metadata)->toBe(['url' => '/dashboard'])
        ->and($fresh?->respondent?->is($user))->toBeTrue();
});

it('supports dismissed responses and query scopes', function (): void {
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    SurveyResponse::factory()
        ->forSurvey('nps')
        ->forRespondent($alice)
        ->completed(['score' => 9])
        ->create();

    SurveyResponse::factory()
        ->forSurvey('nps')
        ->forRespondent($bob)
        ->dismissed()
        ->create();

    SurveyResponse::factory()
        ->forSurvey('other-survey')
        ->forRespondent($alice)
        ->completed(['ok' => true])
        ->create();

    expect(SurveyResponse::query()->forSurvey('nps')->count())->toBe(2)
        ->and(SurveyResponse::query()->forSurvey('nps')->completed()->count())->toBe(1)
        ->and(SurveyResponse::query()->forSurvey('nps')->dismissed()->count())->toBe(1)
        ->and(SurveyResponse::query()->forRespondent($alice)->count())->toBe(2);
});

it('enforces one response or dismissal per user per survey at the database level', function (): void {
    $user = User::create(['name' => 'Chris', 'email' => 'chris@example.com']);

    SurveyResponse::factory()
        ->forSurvey('unique-survey')
        ->forRespondent($user)
        ->completed(['rating' => 4])
        ->create();

    expect(fn () => SurveyResponse::factory()
        ->forSurvey('unique-survey')
        ->forRespondent($user)
        ->dismissed()
        ->create()
    )->toThrow(QueryException::class);
});
