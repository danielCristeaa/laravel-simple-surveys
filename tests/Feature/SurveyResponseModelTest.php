<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Enums\QuestionType;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Models\SurveyQuestion;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Database\QueryException;

it('syncs code-registered surveys and questions into the database idempotently', function (): void {
    SimpleSurveys::register(
        Survey::make('onboarding-feedback')
            ->title('Onboarding Feedback')
            ->banner('Quick onboarding survey!')
            ->description('Tell us how it went.')
            ->questions([
                Question::rating('score', 'Overall score')->scale(1, 5)->required(),
                Question::select('source', 'Source')->options(['google' => 'Google', 'friend' => 'Friend']),
            ])
    );

    $this->artisan('simple-surveys:sync')
        ->expectsOutputToContain('Synced 1 survey to the database.')
        ->assertSuccessful();

    // Update label and re-sync to verify idempotent upsert
    SimpleSurveys::register(
        Survey::make('onboarding-feedback')
            ->title('Updated Onboarding Feedback')
            ->questions([
                Question::rating('score', 'Updated Score')->scale(1, 5)->required(),
                Question::select('source', 'Source')->options(['google' => 'Google', 'friend' => 'Friend']),
            ])
    );

    SimpleSurveys::sync();

    expect(SurveyRecord::query()->count())->toBe(1)
        ->and(SurveyQuestion::query()->count())->toBe(2);

    $record = SurveyRecord::query()->byKey('onboarding-feedback')->with('questions')->firstOrFail();

    expect($record->title)->toBe('Updated Onboarding Feedback')
        ->and($record->status)->toBe(SurveyStatus::Active)
        ->and($record->questions)->toHaveCount(2)
        ->and($record->questions[0]->key)->toBe('score')
        ->and($record->questions[0]->type)->toBe(QuestionType::Rating)
        ->and($record->questions[0]->label)->toBe('Updated Score')
        ->and($record->questions[1]->options)->toBe(['google' => 'Google', 'friend' => 'Friend']);
});

it('stores relational responses, typed answers, and dismissals with scopes', function (): void {
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    SimpleSurveys::register(
        Survey::make('nps')->questions([
            Question::rating('score', 'Score')->scale(0, 10)->required(),
            Question::checkbox('tags', 'Tags')->options(['fast' => 'Fast', 'clean' => 'Clean']),
            Question::boolean('recommend', 'Recommend'),
        ])
    );

    $record = SimpleSurveys::sync()->firstOrFail();

    $response = SurveyResponse::query()->create([
        'survey_id' => $record->id,
        'respondent_type' => $alice->getMorphClass(),
        'respondent_id' => $alice->id,
        'metadata' => ['url' => '/dashboard'],
        'completed_at' => now(),
    ]);

    $questions = $record->questions->keyBy('key');

    SurveyAnswer::query()->create([
        'response_id' => $response->id,
        'question_id' => $questions->get('score')?->id,
        'value' => '9',
        'value_numeric' => 9.0,
    ]);

    SurveyAnswer::query()->create([
        'response_id' => $response->id,
        'question_id' => $questions->get('tags')?->id,
        'value_json' => ['fast', 'clean'],
    ]);

    SurveyAnswer::query()->create([
        'response_id' => $response->id,
        'question_id' => $questions->get('recommend')?->id,
        'value' => '1',
        'value_numeric' => 1.0,
    ]);

    SurveyDismissal::query()->create([
        'survey_id' => $record->id,
        'respondent_type' => $bob->getMorphClass(),
        'respondent_id' => $bob->id,
        'dismissed_at' => now(),
    ]);

    $fresh = $response->fresh(['answers.question', 'respondent', 'survey']);

    expect($fresh)->not->toBeNull()
        ->and($fresh?->survey->key)->toBe('nps')
        ->and($fresh?->respondent?->is($alice))->toBeTrue()
        ->and($fresh?->answerFor('score'))->toBe(9)
        ->and($fresh?->answerFor('tags'))->toBe(['fast', 'clean'])
        ->and($fresh?->answerFor('recommend'))->toBeTrue()
        ->and($fresh?->answerFor('missing', 'fallback'))->toBe('fallback')
        ->and(SurveyResponse::query()->forSurvey('nps')->forRespondent($alice)->count())->toBe(1)
        ->and(SurveyDismissal::query()->forSurvey('nps')->forRespondent($bob)->count())->toBe(1);
});

it('enforces unique respondent constraint per survey on responses and dismissals', function (): void {
    $user = User::create(['name' => 'Chris', 'email' => 'chris@example.com']);

    SimpleSurveys::register(Survey::make('unique-survey'));
    $record = SimpleSurveys::sync()->firstOrFail();

    SurveyResponse::query()->create([
        'survey_id' => $record->id,
        'respondent_type' => $user->getMorphClass(),
        'respondent_id' => $user->id,
        'completed_at' => now(),
    ]);

    expect(fn () => SurveyResponse::query()->create([
        'survey_id' => $record->id,
        'respondent_type' => $user->getMorphClass(),
        'respondent_id' => $user->id,
        'completed_at' => now(),
    ]))->toThrow(QueryException::class);

    SurveyDismissal::query()->create([
        'survey_id' => $record->id,
        'respondent_type' => $user->getMorphClass(),
        'respondent_id' => $user->id,
        'dismissed_at' => now(),
    ]);

    expect(fn () => SurveyDismissal::query()->create([
        'survey_id' => $record->id,
        'respondent_type' => $user->getMorphClass(),
        'respondent_id' => $user->id,
        'dismissed_at' => now(),
    ]))->toThrow(QueryException::class);
});
