<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Enums\ResponseStatus;
use DanielCristea\SimpleSurveys\Events\SurveyDismissed;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Support\Facades\Event;

it('dismisses a survey idempotently and dispatches event once', function (): void {
    Event::fake([SurveyDismissed::class]);

    $user = User::create(['name' => 'Clara', 'email' => 'clara@example.com']);

    SimpleSurveys::register(Survey::make('banner-survey'));

    $this->actingAs($user)
        ->postJson(route('simple-surveys.dismiss', 'banner-survey'))
        ->assertOk()
        ->assertJson([
            'status' => 'dismissed',
            'survey_key' => 'banner-survey',
        ]);

    // Second dismiss is idempotent
    $this->actingAs($user)
        ->postJson(route('simple-surveys.dismiss', 'banner-survey'))
        ->assertOk();

    expect(SurveyResponse::query()->forSurvey('banner-survey')->forRespondent($user)->count())->toBe(1)
        ->and(SurveyResponse::query()->forSurvey('banner-survey')->forRespondent($user)->first()?->status)
        ->toBe(ResponseStatus::Dismissed);

    Event::assertDispatchedTimes(SurveyDismissed::class, 1);
});

it('forbids dismissing a non-dismissible survey or an already completed survey', function (): void {
    $user = User::create(['name' => 'Dan', 'email' => 'dan@example.com']);

    SimpleSurveys::register(Survey::make('mandatory')->dismissible(false));
    SimpleSurveys::register(Survey::make('already-done'));

    SurveyResponse::factory()
        ->forSurvey('already-done')
        ->forRespondent($user)
        ->completed(['a' => 'b'])
        ->create();

    $this->actingAs($user)
        ->postJson(route('simple-surveys.dismiss', 'mandatory'))
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson(route('simple-surveys.dismiss', 'already-done'))
        ->assertConflict();
});
