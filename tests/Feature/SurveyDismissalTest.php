<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Events\SurveyDismissed;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
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

    expect(SurveyDismissal::query()->forSurvey('banner-survey')->forRespondent($user)->count())->toBe(1);

    Event::assertDispatchedTimes(SurveyDismissed::class, 1);
});

it('forbids dismissing a non-dismissible survey or an already completed survey', function (): void {
    $user = User::create(['name' => 'Dan', 'email' => 'dan@example.com']);

    SimpleSurveys::register(Survey::make('mandatory')->dismissible(false));
    SimpleSurveys::register(Survey::make('already-done'));

    SimpleSurveys::sync();
    $record = SurveyRecord::query()->byKey('already-done')->firstOrFail();

    SurveyResponse::query()->create([
        'survey_id' => $record->id,
        'respondent_type' => $user->getMorphClass(),
        'respondent_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->postJson(route('simple-surveys.dismiss', 'mandatory'))
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson(route('simple-surveys.dismiss', 'already-done'))
        ->assertConflict();
});

it('updates dismissed_at and dispatches event again when dismissed after cooldown expires', function (): void {
    Event::fake([SurveyDismissed::class]);

    $user = User::create(['name' => 'Eva', 'email' => 'eva@example.com']);

    SimpleSurveys::register(
        Survey::make('cooldown-banner')->repromptAfterDays(14)
    );

    $this->actingAs($user)
        ->postJson(route('simple-surveys.dismiss', 'cooldown-banner'))
        ->assertOk();

    $firstDismissedAt = SurveyDismissal::query()
        ->forSurvey('cooldown-banner')
        ->forRespondent($user)
        ->firstOrFail()
        ->dismissed_at;

    // Within cooldown -> idempotent, no second event
    $this->travel(10)->days();

    $this->actingAs($user)
        ->postJson(route('simple-surveys.dismiss', 'cooldown-banner'))
        ->assertOk();

    Event::assertDispatchedTimes(SurveyDismissed::class, 1);

    // Past cooldown -> updates dismissed_at on the same row and dispatches event again
    $this->travel(5)->days();

    $this->actingAs($user)
        ->postJson(route('simple-surveys.dismiss', 'cooldown-banner'))
        ->assertOk();

    $dismissals = SurveyDismissal::query()
        ->forSurvey('cooldown-banner')
        ->forRespondent($user)
        ->get();

    expect($dismissals)->toHaveCount(1)
        ->and($dismissals->first()?->dismissed_at->greaterThan($firstDismissedAt))->toBeTrue();

    Event::assertDispatchedTimes(SurveyDismissed::class, 2);
});
