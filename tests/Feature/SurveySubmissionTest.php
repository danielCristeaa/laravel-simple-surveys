<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Enums\ResponseStatus;
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;

it('submits a valid survey response, persists answers, and dispatches event', function (): void {
    Event::fake([SurveySubmitted::class]);

    $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'plan' => 'pro']);

    SimpleSurveys::register(
        Survey::make('onboarding')
            ->thankYouMessage('Thanks for helping us improve!')
            ->target(fn (Builder $q) => $q->where('plan', 'pro'))
            ->questions([
                Question::rating('score', 'Score')->scale(1, 5)->required(),
                Question::text('feedback', 'Feedback')->maxLength(255),
            ])
    );

    $response = $this->actingAs($user)
        ->postJson(route('simple-surveys.responses.store', 'onboarding'), [
            'answers' => [
                'score' => 5,
                'feedback' => 'Loving it!',
            ],
            'url' => '/dashboard',
        ]);

    $response->assertCreated()
        ->assertJson([
            'message' => 'Thanks for helping us improve!',
            'survey_key' => 'onboarding',
        ]);

    $stored = SurveyResponse::query()->forSurvey('onboarding')->forRespondent($user)->first();

    expect($stored)->not->toBeNull()
        ->and($stored?->status)->toBe(ResponseStatus::Completed)
        ->and($stored?->answers)->toBe([
            'score' => 5,
            'feedback' => 'Loving it!',
        ])
        ->and($stored?->metadata)->toBe(['url' => '/dashboard'])
        ->and($stored?->completed_at)->not->toBeNull();

    Event::assertDispatched(SurveySubmitted::class, function (SurveySubmitted $event) use ($user): bool {
        return $event->survey->key() === 'onboarding'
            && $event->respondent->is($user)
            && $event->response->isCompleted();
    });
});

it('returns 422 validation errors when answers are invalid', function (): void {
    $user = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    SimpleSurveys::register(
        Survey::make('quick-poll')->questions([
            Question::rating('score', 'Overall Score')->scale(1, 5)->required(),
        ])
    );

    $this->actingAs($user)
        ->postJson(route('simple-surveys.responses.store', 'quick-poll'), [
            'answers' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['answers.score']);
});

it('rejects unauthenticated, unknown, inactive, ineligible, and duplicate submissions', function (): void {
    $freeUser = User::create(['name' => 'Free', 'email' => 'free@example.com', 'plan' => 'free']);
    $proUser = User::create(['name' => 'Pro', 'email' => 'pro@example.com', 'plan' => 'pro']);

    SimpleSurveys::register(Survey::make('closed-one')->closed()->questions([Question::text('q', 'Q')]));
    SimpleSurveys::register(
        Survey::make('pro-only')
            ->target(fn (Builder $q) => $q->where('plan', 'pro'))
            ->questions([Question::text('q', 'Q')])
    );

    // Unauthenticated
    $this->postJson(route('simple-surveys.responses.store', 'pro-only'), ['answers' => ['q' => 'hi']])
        ->assertUnauthorized();

    // Unknown survey
    $this->actingAs($proUser)
        ->postJson(route('simple-surveys.responses.store', 'missing'), ['answers' => []])
        ->assertNotFound();

    // Closed survey
    $this->actingAs($proUser)
        ->postJson(route('simple-surveys.responses.store', 'closed-one'), ['answers' => ['q' => 'hi']])
        ->assertForbidden();

    // Ineligible user
    $this->actingAs($freeUser)
        ->postJson(route('simple-surveys.responses.store', 'pro-only'), ['answers' => ['q' => 'hi']])
        ->assertForbidden();

    // First valid submission
    $this->actingAs($proUser)
        ->postJson(route('simple-surveys.responses.store', 'pro-only'), ['answers' => ['q' => 'hi']])
        ->assertCreated();

    // Duplicate submission
    $this->actingAs($proUser)
        ->postJson(route('simple-surveys.responses.store', 'pro-only'), ['answers' => ['q' => 'again']])
        ->assertConflict();
});
