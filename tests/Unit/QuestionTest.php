<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Enums\QuestionType;
use DanielCristea\SimpleSurveys\Question;

it('builds every supported question type', function (): void {
    expect(Question::text('q1', 'Short text')->type())->toBe(QuestionType::Text)
        ->and(Question::textarea('q2', 'Long text')->type())->toBe(QuestionType::Textarea)
        ->and(Question::number('q3', 'Age')->type())->toBe(QuestionType::Number)
        ->and(Question::select('q4', 'Country')->type())->toBe(QuestionType::Select)
        ->and(Question::radio('q5', 'Role')->type())->toBe(QuestionType::Radio)
        ->and(Question::checkbox('q6', 'Features')->type())->toBe(QuestionType::Checkbox)
        ->and(Question::rating('q7', 'Score')->type())->toBe(QuestionType::Rating)
        ->and(Question::boolean('q8', 'Recommend?')->type())->toBe(QuestionType::Boolean);
});

it('normalizes list and associative options', function (): void {
    $listQuestion = Question::select('source', 'Source')
        ->options(['Google', 'Friend']);

    $assocQuestion = Question::radio('tier', 'Tier')
        ->options(['free' => 'Free Tier', 'pro' => 'Pro Tier']);

    expect($listQuestion->getOptions())->toBe([
        'Google' => 'Google',
        'Friend' => 'Friend',
    ])->and($assocQuestion->getOptions())->toBe([
        'free' => 'Free Tier',
        'pro' => 'Pro Tier',
    ]);
});

it('configures rating scale and numeric constraints', function (): void {
    $defaultRating = Question::rating('nps', 'NPS');
    expect($defaultRating->getMin())->toBe(1)
        ->and($defaultRating->getMax())->toBe(5);

    $nps = Question::rating('nps', 'How likely are you to recommend us?')
        ->scale(0, 10)
        ->required()
        ->helpText('0 = Not likely, 10 = Very likely');

    expect($nps->getMin())->toBe(0)
        ->and($nps->getMax())->toBe(10)
        ->and($nps->isRequired())->toBeTrue()
        ->and($nps->getHelpText())->toBe('0 = Not likely, 10 = Very likely');

    $number = Question::number('seats', 'Seats')
        ->min(1)
        ->max(500)
        ->step(1)
        ->placeholder('e.g. 10')
        ->optional()
        ->rules(['integer']);

    expect($number->getMin())->toBe(1)
        ->and($number->getMax())->toBe(500)
        ->and($number->getStep())->toBe(1)
        ->and($number->getPlaceholder())->toBe('e.g. 10')
        ->and($number->isRequired())->toBeFalse()
        ->and($number->getRules())->toBe(['integer']);
});

it('throws when question key is empty or rating scale is invalid', function (): void {
    expect(fn () => Question::text('   ', 'Empty key'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => Question::rating('score', 'Score')->scale(5, 5))
        ->toThrow(InvalidArgumentException::class);
});

it('reports question type metadata accurately', function (): void {
    expect(QuestionType::Select->hasOptions())->toBeTrue()
        ->and(QuestionType::Radio->hasOptions())->toBeTrue()
        ->and(QuestionType::Checkbox->hasOptions())->toBeTrue()
        ->and(QuestionType::Text->hasOptions())->toBeFalse()
        ->and(QuestionType::Number->isNumeric())->toBeTrue()
        ->and(QuestionType::Rating->isNumeric())->toBeTrue()
        ->and(QuestionType::Boolean->isNumeric())->toBeFalse();
});
