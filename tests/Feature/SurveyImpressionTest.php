<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Events\SurveySeen;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyImpression;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;

it('tracks survey impressions once per unique user and keeps user eligible', function (): void {
    Event::fake([SurveySeen::class]);

    $user = User::create(['name' => 'Elena', 'email' => 'elena@example.com']);

    SimpleSurveys::register(
        Survey::make('seen-tracking')
            ->questions([
                Question::text('feedback', 'Feedback'),
            ])
    );

    $this->actingAs($user)
        ->postJson(route('simple-surveys.impression', 'seen-tracking'))
        ->assertOk()
        ->assertJson([
            'status' => 'seen',
            'survey_key' => 'seen-tracking',
        ]);

    $firstImpression = SurveyImpression::query()
        ->forSurvey('seen-tracking')
        ->forRespondent($user)
        ->first();

    expect($firstImpression)->not->toBeNull()
        ->and($firstImpression?->first_seen_at)->not->toBeNull();

    $firstSeenAt = $firstImpression?->first_seen_at?->toIso8601String();

    // Repeat impression is a no-op: no duplicate row, no duplicate event
    $this->actingAs($user)
        ->postJson(route('simple-surveys.impression', 'seen-tracking'))
        ->assertOk()
        ->assertJson([
            'status' => 'seen',
            'survey_key' => 'seen-tracking',
        ]);

    $impressions = SurveyImpression::query()
        ->forSurvey('seen-tracking')
        ->forRespondent($user)
        ->get();

    expect($impressions)->toHaveCount(1)
        ->and($impressions->first()?->first_seen_at?->toIso8601String())->toBe($firstSeenAt)
        ->and(SimpleSurveys::isEligible('seen-tracking', $user))->toBeTrue();

    Event::assertDispatchedTimes(SurveySeen::class, 1);
    Event::assertDispatched(SurveySeen::class, static function (SurveySeen $event) use ($user): bool {
        return $event->survey->key() === 'seen-tracking'
            && $event->respondent->is($user)
            && $event->impression->respondent_id === $user->id;
    });
});

it('does not record impressions or dispatch events when impression tracking is disabled', function (): void {
    Event::fake([SurveySeen::class]);
    config()->set('simple-surveys.tracking.impressions', false);

    $user = User::create(['name' => 'Marco', 'email' => 'marco@example.com']);

    SimpleSurveys::register(
        Survey::make('no-tracking')
            ->questions([
                Question::text('feedback', 'Feedback'),
            ])
    );

    $this->actingAs($user)
        ->postJson(route('simple-surveys.impression', 'no-tracking'))
        ->assertOk()
        ->assertJson([
            'status' => 'disabled',
            'survey_key' => 'no-tracking',
        ]);

    expect(SurveyImpression::query()->count())->toBe(0);
    Event::assertNotDispatched(SurveySeen::class);
});

it('rejects impressions from guests, unknown surveys, closed surveys, ineligible users, dismissed users, and completed respondents', function (): void {
    $freeUser = User::create(['name' => 'Free', 'email' => 'free@example.com', 'plan' => 'free']);
    $proUser = User::create(['name' => 'Pro', 'email' => 'pro@example.com', 'plan' => 'pro']);
    $dismissedUser = User::create(['name' => 'Dismissed', 'email' => 'dismissed@example.com', 'plan' => 'pro']);

    SimpleSurveys::register(Survey::make('closed-survey')->closed()->questions([Question::text('q', 'Q')]));
    SimpleSurveys::register(
        Survey::make('pro-survey')
            ->target(fn (Builder $q) => $q->where('plan', 'pro'))
            ->questions([Question::text('q', 'Q')])
    );

    // Unauthenticated
    $this->postJson(route('simple-surveys.impression', 'pro-survey'))
        ->assertUnauthorized();

    // Unknown survey
    $this->actingAs($proUser)
        ->postJson(route('simple-surveys.impression', 'missing'))
        ->assertNotFound();

    // Closed survey
    $this->actingAs($proUser)
        ->postJson(route('simple-surveys.impression', 'closed-survey'))
        ->assertForbidden();

    // Ineligible user
    $this->actingAs($freeUser)
        ->postJson(route('simple-surveys.impression', 'pro-survey'))
        ->assertForbidden();

    // Dismissed user cannot track new impressions
    $this->actingAs($dismissedUser)
        ->postJson(route('simple-surveys.dismiss', 'pro-survey'))
        ->assertOk();

    $this->actingAs($dismissedUser)
        ->postJson(route('simple-surveys.impression', 'pro-survey'))
        ->assertForbidden();

    // Completed user cannot track new impressions
    $this->actingAs($proUser)
        ->postJson(route('simple-surveys.responses.store', 'pro-survey'), ['answers' => ['q' => 'done']])
        ->assertCreated();

    $this->actingAs($proUser)
        ->postJson(route('simple-surveys.impression', 'pro-survey'))
        ->assertForbidden();
});
