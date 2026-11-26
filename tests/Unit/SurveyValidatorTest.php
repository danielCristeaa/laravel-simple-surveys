<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Services\SurveyValidator;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Validation\ValidationException;

it('validates and normalizes all question types while stripping unknown keys', function (): void {
    $survey = Survey::make('full-survey')->questions([
        Question::text('name', 'Name')->maxLength(50)->required(),
        Question::textarea('notes', 'Notes')->maxLength(200),
        Question::number('seats', 'Seats')->min(1)->max(100)->required(),
        Question::select('source', 'Source')->options(['google' => 'Google', 'friend' => 'Friend'])->required(),
        Question::radio('role', 'Role')->options(['Dev', 'PM'])->required(),
        Question::checkbox('features', 'Features')->options(['api' => 'API', 'ui' => 'UI', 'cli' => 'CLI']),
        Question::rating('score', 'Score')->scale(1, 5)->required(),
        Question::boolean('recommend', 'Recommend')->required(),
    ]);

    $validator = app(SurveyValidator::class);

    $normalized = $validator->validate($survey, [
        'answers' => [
            'name' => 'Daniel',
            'notes' => '',
            'seats' => '15',
            'source' => 'google',
            'role' => 'Dev',
            'features' => ['api', 'cli'],
            'score' => '5',
            'recommend' => '1',
            'injected_unknown' => 'should-be-stripped',
        ],
    ]);

    expect($normalized)->toBe([
        'name' => 'Daniel',
        'notes' => null,
        'seats' => 15,
        'source' => 'google',
        'role' => 'Dev',
        'features' => ['api', 'cli'],
        'score' => 5,
        'recommend' => true,
    ]);
});

it('rejects invalid options, out-of-range ratings, and missing required questions', function (): void {
    $survey = Survey::make('strict-survey')->questions([
        Question::select('source', 'Source')->options(['Google', 'Friend'])->required(),
        Question::checkbox('features', 'Features')->options(['api' => 'API', 'ui' => 'UI']),
        Question::rating('score', 'Score')->scale(1, 5)->required(),
    ]);

    $validator = app(SurveyValidator::class);

    try {
        $validator->validate($survey, [
            'answers' => [
                'source' => 'InvalidSource',
                'features' => ['api', 'invalid_feature'],
                'score' => 10,
            ],
        ]);

        test()->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKeys([
            'answers.source',
            'answers.features.1',
            'answers.score',
        ]);
    }
});

it('enforces conditional required questions only when visible and strips hidden stale answers', function (): void {
    $survey = Survey::make('conditional-csat')->questions([
        Question::rating('score', 'Score')->scale(1, 5)->required(),
        Question::textarea('low_reason', 'What went wrong?')
            ->maxLength(20)
            ->when('score', '<=', 3)
            ->required(),
        Question::checkbox('features', 'Features')
            ->options(['api' => 'API', 'ui' => 'UI']),
        Question::text('api_feedback', 'API feedback')
            ->whenContains('features', 'api')
            ->when('score', '<=', 3)
            ->required(),
    ]);

    $validator = app(SurveyValidator::class);

    // Score 5 -> low_reason and api_feedback are hidden; stale/overlong answers are ignored and nulled
    $highScore = $validator->validate($survey, [
        'answers' => [
            'score' => 5,
            'low_reason' => str_repeat('x', 100),
            'features' => ['api'],
            'api_feedback' => 'stale api feedback',
        ],
    ]);

    expect($highScore)->toBe([
        'score' => 5,
        'low_reason' => null,
        'features' => ['api'],
        'api_feedback' => null,
    ]);

    // Score 2 without low_reason -> validation fails on low_reason only (api_feedback still hidden because features has no 'api')
    try {
        $validator->validate($survey, [
            'answers' => [
                'score' => 2,
                'features' => ['ui'],
            ],
        ]);

        test()->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('answers.low_reason')
            ->and($exception->errors())->not->toHaveKey('answers.api_feedback');
    }

    // Score 2 AND features contains 'api' -> both conditional required fields must be present
    try {
        $validator->validate($survey, [
            'answers' => [
                'score' => 2,
                'features' => ['api'],
            ],
        ]);

        test()->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKeys([
            'answers.low_reason',
            'answers.api_feedback',
        ]);
    }

    // Score 2 AND features contains 'api' with both filled -> passes cleanly
    $lowScore = $validator->validate($survey, [
        'answers' => [
            'score' => 2,
            'low_reason' => 'Too slow',
            'features' => ['api'],
            'api_feedback' => 'Rate limits too low',
        ],
    ]);

    expect($lowScore)->toBe([
        'score' => 2,
        'low_reason' => 'Too slow',
        'features' => ['api'],
        'api_feedback' => 'Rate limits too low',
    ]);
});

it('hides transitively dependent chained questions when an ancestor condition fails', function (): void {
    $survey = Survey::make('chained-conditions')->questions([
        Question::boolean('recommend', 'Would you recommend us?')->required(),
        Question::radio('reason', 'Primary reason')
            ->options(['price' => 'Price', 'bugs' => 'Bugs', 'other' => 'Other'])
            ->when('recommend', false)
            ->required(),
        Question::text('other_reason', 'Please specify')
            ->when('reason', 'other')
            ->required(),
    ]);

    $validator = app(SurveyValidator::class);

    // recommend = true -> reason is hidden -> other_reason is ALSO hidden even if stale reason='other' was sent
    $yesResult = $validator->validate($survey, [
        'answers' => [
            'recommend' => '1',
            'reason' => 'other',
            'other_reason' => 'Stale detail',
        ],
    ]);

    expect($yesResult)->toBe([
        'recommend' => true,
        'reason' => null,
        'other_reason' => null,
    ]);

    // recommend = false, reason = other, missing other_reason -> fails on other_reason
    try {
        $validator->validate($survey, [
            'answers' => [
                'recommend' => '0',
                'reason' => 'other',
            ],
        ]);

        test()->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('answers.other_reason');
    }

    // recommend = false, reason = other, other_reason filled -> passes
    $noResult = $validator->validate($survey, [
        'answers' => [
            'recommend' => false,
            'reason' => 'other',
            'other_reason' => 'Missing SSO',
        ],
    ]);

    expect($noResult)->toBe([
        'recommend' => false,
        'reason' => 'other',
        'other_reason' => 'Missing SSO',
    ]);
});
