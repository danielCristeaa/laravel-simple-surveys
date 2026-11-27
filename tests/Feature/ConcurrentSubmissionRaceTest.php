<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Events\SurveyDismissed;
use DanielCristea\SimpleSurveys\Events\SurveyOpened;
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Models\SurveyOpen;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('returns 409 conflict without orphaned answers or events when concurrent submission hits unique constraint', function (): void {
    Event::fake([SurveySubmitted::class]);

    $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

    SimpleSurveys::register(
        Survey::make('race-submit')
            ->questions([
                Question::rating('score', 'Score')->scale(1, 5)->required(),
                Question::text('feedback', 'Feedback'),
            ])
    );

    SurveyResponse::creating(function (SurveyResponse $response): void {
        DB::table($response->getTable())->insert([
            'survey_id' => $response->survey_id,
            'respondent_type' => $response->respondent_type,
            'respondent_id' => $response->respondent_id,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('simple-surveys.responses.store', 'race-submit'), [
            'answers' => [
                'score' => 5,
                'feedback' => 'Great!',
            ],
        ])
        ->assertConflict()
        ->assertJson([
            'message' => __('simple-surveys::widget.errors.already_submitted'),
        ]);

    expect(SurveyAnswer::query()->count())->toBe(0)
        ->and(SurveyResponse::query()->count())->toBe(0);

    Event::assertNotDispatched(SurveySubmitted::class);
});

it('rolls back stale dismissal deletion when concurrent submission collides on unique constraint', function (): void {
    Event::fake([SurveySubmitted::class]);

    $user = User::create(['name' => 'Liam', 'email' => 'liam@example.com']);

    SimpleSurveys::register(
        Survey::make('race-reprompt-submit')
            ->repromptAfterDays(7)
            ->questions([
                Question::rating('score', 'Score')->scale(1, 5)->required(),
            ])
    );

    $this->actingAs($user)
        ->postJson(route('simple-surveys.dismiss', 'race-reprompt-submit'))
        ->assertOk();

    $this->travel(8)->days();

    SurveyResponse::creating(function (SurveyResponse $response): void {
        DB::table($response->getTable())->insert([
            'survey_id' => $response->survey_id,
            'respondent_type' => $response->respondent_type,
            'respondent_id' => $response->respondent_id,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('simple-surveys.responses.store', 'race-reprompt-submit'), [
            'answers' => ['score' => 4],
        ])
        ->assertConflict();

    expect(SurveyDismissal::query()->forSurvey('race-reprompt-submit')->forRespondent($user)->count())->toBe(1)
        ->and(SurveyAnswer::query()->count())->toBe(0);

    Event::assertNotDispatched(SurveySubmitted::class);
});

it('recovers when concurrent request syncs survey record mid-flight during submission', function (): void {
    Event::fake([SurveySubmitted::class]);

    $user = User::create(['name' => 'Maya', 'email' => 'maya@example.com']);

    SimpleSurveys::register(
        Survey::make('race-sync-submit')
            ->questions([
                Question::text('feedback', 'Feedback')->required(),
            ])
    );

    $simulated = false;

    SurveyRecord::creating(function (SurveyRecord $record) use (&$simulated): void {
        if ($simulated) {
            return;
        }

        $simulated = true;

        DB::table($record->getTable())->insert([
            'key' => $record->key,
            'title' => $record->title,
            'banner_text' => $record->banner_text,
            'description' => $record->description,
            'status' => $record->status->value,
            'starts_at' => $record->starts_at,
            'ends_at' => $record->ends_at,
            'dismissible' => $record->dismissible,
            'priority' => $record->priority,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('simple-surveys.responses.store', 'race-sync-submit'), [
            'answers' => ['feedback' => 'Works smoothly'],
        ])
        ->assertCreated();

    expect(SurveyRecord::query()->byKey('race-sync-submit')->count())->toBe(1)
        ->and(SurveyResponse::query()->forSurvey('race-sync-submit')->forRespondent($user)->count())->toBe(1)
        ->and(SurveyAnswer::query()->count())->toBe(1);

    Event::assertDispatchedTimes(SurveySubmitted::class, 1);
});

it('handles concurrent first-time dismissal race without throwing 500', function (): void {
    Event::fake([SurveyDismissed::class]);

    $user = User::create(['name' => 'Clara', 'email' => 'clara@example.com']);

    SimpleSurveys::register(Survey::make('race-dismiss'));

    $simulated = false;

    SurveyDismissal::creating(function (SurveyDismissal $dismissal) use (&$simulated): void {
        if ($simulated) {
            return;
        }

        $simulated = true;

        DB::table($dismissal->getTable())->insert([
            'survey_id' => $dismissal->survey_id,
            'respondent_type' => $dismissal->respondent_type,
            'respondent_id' => $dismissal->respondent_id,
            'dismissed_at' => now(),
            'source' => 'close_button',
            'dismiss_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('simple-surveys.dismiss', 'race-dismiss'))
        ->assertOk()
        ->assertJson([
            'status' => 'dismissed',
            'survey_key' => 'race-dismiss',
        ]);

    $dismissal = SurveyDismissal::query()
        ->forSurvey('race-dismiss')
        ->forRespondent($user)
        ->first();

    expect($dismissal)->not->toBeNull()
        ->and($dismissal?->dismiss_count)->toBe(1);

    Event::assertDispatchedTimes(SurveyDismissed::class, 1);
});

it('returns clean dismissed response even when unique constraint collides on every dismissal attempt', function (): void {
    Event::fake([SurveyDismissed::class]);

    $user = User::create(['name' => 'Noah', 'email' => 'noah@example.com']);

    SimpleSurveys::register(Survey::make('race-dismiss-repeat'));

    SurveyDismissal::creating(function (SurveyDismissal $dismissal): void {
        DB::table($dismissal->getTable())->insert([
            'survey_id' => $dismissal->survey_id,
            'respondent_type' => $dismissal->respondent_type,
            'respondent_id' => $dismissal->respondent_id,
            'dismissed_at' => now(),
            'source' => 'close_button',
            'dismiss_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('simple-surveys.dismiss', 'race-dismiss-repeat'))
        ->assertOk()
        ->assertJson([
            'status' => 'dismissed',
            'survey_key' => 'race-dismiss-repeat',
        ]);

    Event::assertNotDispatched(SurveyDismissed::class);
});

it('handles concurrent first-time open race without throwing 500', function (): void {
    Event::fake([SurveyOpened::class]);

    $user = User::create(['name' => 'Elena', 'email' => 'elena@example.com']);

    SimpleSurveys::register(
        Survey::make('race-open')
            ->questions([
                Question::text('feedback', 'Feedback'),
            ])
    );

    $simulated = false;

    SurveyOpen::creating(function (SurveyOpen $open) use (&$simulated): void {
        if ($simulated) {
            return;
        }

        $simulated = true;

        DB::table($open->getTable())->insert([
            'survey_id' => $open->survey_id,
            'respondent_type' => $open->respondent_type,
            'respondent_id' => $open->respondent_id,
            'open_count' => 1,
            'first_opened_at' => now(),
            'last_opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('simple-surveys.open', 'race-open'))
        ->assertOk()
        ->assertJson([
            'status' => 'opened',
            'survey_key' => 'race-open',
        ]);

    $open = SurveyOpen::query()
        ->forSurvey('race-open')
        ->forRespondent($user)
        ->first();

    expect($open)->not->toBeNull()
        ->and($open?->open_count)->toBe(1);

    Event::assertDispatchedTimes(SurveyOpened::class, 1);
});

it('returns clean opened response even when unique constraint collides on every open attempt', function (): void {
    Event::fake([SurveyOpened::class]);

    $user = User::create(['name' => 'Owen', 'email' => 'owen@example.com']);

    SimpleSurveys::register(
        Survey::make('race-open-repeat')
            ->questions([
                Question::text('feedback', 'Feedback'),
            ])
    );

    SurveyOpen::creating(function (SurveyOpen $open): void {
        DB::table($open->getTable())->insert([
            'survey_id' => $open->survey_id,
            'respondent_type' => $open->respondent_type,
            'respondent_id' => $open->respondent_id,
            'open_count' => 1,
            'first_opened_at' => now(),
            'last_opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('simple-surveys.open', 'race-open-repeat'))
        ->assertOk()
        ->assertJson([
            'status' => 'opened',
            'survey_key' => 'race-open-repeat',
        ]);

    Event::assertNotDispatched(SurveyOpened::class);
});
