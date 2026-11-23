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
