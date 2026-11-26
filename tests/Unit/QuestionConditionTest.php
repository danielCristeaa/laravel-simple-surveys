<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Enums\QuestionConditionOperator;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\QuestionCondition;

it('builds conditional rules fluently on questions', function (): void {
    $question = Question::textarea('reason', 'What went wrong?')
        ->when('score', '<=', 3)
        ->when('recommend', false)
        ->whenIn('source', ['other', 'referral'])
        ->whenNotIn('tier', ['enterprise'])
        ->whenContains('features', 'api')
        ->whenNotContains('features', 'cli')
        ->required();

    expect($question->isConditional())->toBeTrue()
        ->and($question->getConditions())->toHaveCount(6)
        ->and(array_map(static fn (QuestionCondition $c): array => $c->toArray(), $question->getConditions()))->toBe([
            ['question' => 'score', 'operator' => '<=', 'value' => 3],
            ['question' => 'recommend', 'operator' => '=', 'value' => false],
            ['question' => 'source', 'operator' => 'in', 'value' => ['other', 'referral']],
            ['question' => 'tier', 'operator' => 'not_in', 'value' => ['enterprise']],
            ['question' => 'features', 'operator' => 'contains', 'value' => 'api'],
            ['question' => 'features', 'operator' => 'not_contains', 'value' => 'cli'],
        ]);

    expect(Question::text('plain', 'Plain')->isConditional())->toBeFalse();
});

it('supports operator aliases and enum instances', function (): void {
    $eq = new QuestionCondition('source', '==', 'other');
    $neq = new QuestionCondition('source', '<>', 'google');
    $enum = new QuestionCondition('score', QuestionConditionOperator::GreaterThanOrEqual, '4');

    expect($eq->operator)->toBe(QuestionConditionOperator::Equals)
        ->and($neq->operator)->toBe(QuestionConditionOperator::NotEquals)
        ->and($enum->operator)->toBe(QuestionConditionOperator::GreaterThanOrEqual)
        ->and($enum->value)->toBe(4);
});

it('treats unanswered values as non-matching for every operator', function (): void {
    $conditions = [
        new QuestionCondition('q', '=', 'other'),
        new QuestionCondition('q', '=', false),
        new QuestionCondition('q', '!=', 'google'),
        new QuestionCondition('q', '<=', 3),
        new QuestionCondition('q', '>=', 0),
        new QuestionCondition('q', 'in', ['a', 'b']),
        new QuestionCondition('q', 'not_in', ['a']),
        new QuestionCondition('q', 'contains', 'api'),
        new QuestionCondition('q', 'not_contains', 'api'),
    ];

    foreach ($conditions as $condition) {
        expect($condition->matches(null))->toBeFalse()
            ->and($condition->matches(''))->toBeFalse()
            ->and($condition->matches([]))->toBeFalse();
    }
});

it('evaluates equality, numeric, set, boolean, and contains operators accurately', function (): void {
    // Equality & inequality (including 0 and false as answered values)
    expect((new QuestionCondition('score', '=', 0))->matches(0))->toBeTrue()
        ->and((new QuestionCondition('score', '=', 3))->matches('3'))->toBeTrue()
        ->and((new QuestionCondition('code', '=', '01'))->matches('1'))->toBeFalse()
        ->and((new QuestionCondition('recommend', '=', false))->matches(false))->toBeTrue()
        ->and((new QuestionCondition('recommend', '=', false))->matches('0'))->toBeTrue()
        ->and((new QuestionCondition('recommend', '=', true))->matches(false))->toBeFalse()
        ->and((new QuestionCondition('source', '!=', 'google'))->matches('friend'))->toBeTrue()
        ->and((new QuestionCondition('source', '!=', 'google'))->matches('google'))->toBeFalse();

    // Numeric comparisons
    expect((new QuestionCondition('score', '>', 3))->matches(4))->toBeTrue()
        ->and((new QuestionCondition('score', '>', 3))->matches(3))->toBeFalse()
        ->and((new QuestionCondition('score', '>=', 3))->matches(3))->toBeTrue()
        ->and((new QuestionCondition('score', '<', 3))->matches(2))->toBeTrue()
        ->and((new QuestionCondition('score', '<=', 3))->matches(3))->toBeTrue()
        ->and((new QuestionCondition('score', '<=', 3))->matches(0))->toBeTrue()
        ->and((new QuestionCondition('score', '<=', 3))->matches('not-a-number'))->toBeFalse()
        ->and((new QuestionCondition('score', '<=', 3))->matches(false))->toBeFalse();

    // Set membership
    expect((new QuestionCondition('source', 'in', ['other', 'referral']))->matches('other'))->toBeTrue()
        ->and((new QuestionCondition('source', 'in', ['other', 'referral']))->matches('google'))->toBeFalse()
        ->and((new QuestionCondition('source', 'not_in', ['google', 'ads']))->matches('friend'))->toBeTrue()
        ->and((new QuestionCondition('source', 'not_in', ['google', 'ads']))->matches('google'))->toBeFalse()
        ->and((new QuestionCondition('features', 'in', ['api', 'cli']))->matches(['ui', 'api']))->toBeTrue()
        ->and((new QuestionCondition('features', 'not_in', ['cli']))->matches(['ui', 'api']))->toBeTrue();

    // Contains / NotContains (checkbox arrays and strings)
    expect((new QuestionCondition('features', 'contains', 'api'))->matches(['api', 'ui']))->toBeTrue()
        ->and((new QuestionCondition('features', 'contains', 'cli'))->matches(['api', 'ui']))->toBeFalse()
        ->and((new QuestionCondition('features', 'not_contains', 'cli'))->matches(['api', 'ui']))->toBeTrue()
        ->and((new QuestionCondition('features', 'not_contains', 'api'))->matches(['api', 'ui']))->toBeFalse()
        ->and((new QuestionCondition('notes', 'contains', 'slow'))->matches('App is slow on mobile'))->toBeTrue()
        ->and((new QuestionCondition('notes', 'not_contains', 'bug'))->matches('App is slow'))->toBeTrue();
});

it('rejects invalid condition definitions and self-dependencies', function (): void {
    expect(fn () => new QuestionCondition('   ', '=', 'x'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new QuestionCondition('score', 'like', 'x'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new QuestionCondition('score', '<=', 'not-numeric'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new QuestionCondition('source', 'in', 'not-an-array'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new QuestionCondition('source', 'in', [['nested']]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new QuestionCondition('source', '=', null))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => Question::text('notes', 'Notes')->when('notes', 'foo'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => Question::text('notes', 'Notes')->when('score', 123, 'val'))
        ->toThrow(InvalidArgumentException::class);
});
