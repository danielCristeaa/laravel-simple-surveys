<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;

it('auto-syncs, persists relational answers, and dispatches event on submission', function (): void {
    Event::fake([SurveySubmitted::class]);

    $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'plan' => 'pro']);

    SimpleSurveys::register(
        Survey::make('onboarding')
            ->thankYouMessage('Thanks for helping us improve!')
            ->target(fn (Builder $q) => $q->where('plan', 'pro'))
            ->questions([
                Question::rating('score', 'Score')->scale(1, 5)->required(),
                Question::checkbox('features', 'Features')->options(['api' => 'API', 'ui' => 'UI']),
                Question::boolean('recommend', 'Recommend')->required(),
                Question::text('feedback', 'Feedback')->maxLength(255),
            ])
    );

    $response = $this->actingAs($user)
        ->postJson(route('simple-surveys.responses.store', 'onboarding'), [
            'answers' => [
                'score' => 5,
                'features' => ['api', 'ui'],
                'recommend' => true,
                'feedback' => 'Loving it!',
            ],
            'url' => '/dashboard',
        ]);

    $response->assertCreated()
        ->assertJson([
            'message' => 'Thanks for helping us improve!',
            'survey_key' => 'onboarding',
        ]);

    $stored = SurveyResponse::query()
        ->forSurvey('onboarding')
        ->forRespondent($user)
        ->with('answers.question')
        ->first();

    expect($stored)->not->toBeNull()
        ->and($stored?->metadata)->toBe(['url' => '/dashboard'])
        ->and($stored?->created_at)->not->toBeNull()
        ->and(SurveyAnswer::query()->count())->toBe(4)
        ->and($stored?->answerFor('score'))->toBe(5)
        ->and($stored?->answerFor('features'))->toBe(['api', 'ui'])
        ->and($stored?->answerFor('recommend'))->toBeTrue()
        ->and($stored?->answerFor('feedback'))->toBe('Loving it!');

    Event::assertDispatched(SurveySubmitted::class, function (SurveySubmitted $event) use ($user): bool {
        return $event->survey->key() === 'onboarding'
            && $event->respondent->is($user)
            && $event->response->answers->count() === 4;
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
