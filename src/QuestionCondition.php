<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use DanielCristea\SimpleSurveys\Enums\QuestionConditionOperator;
use InvalidArgumentException;

final readonly class QuestionCondition
{
    public string $questionKey;

    public QuestionConditionOperator $operator;

    /** @var string|int|float|bool|array<int, string|int|float|bool> */
    public string|int|float|bool|array $value;

    public function __construct(
        string $questionKey,
        QuestionConditionOperator|string $operator,
        mixed $value,
    ) {
        $normalizedKey = trim($questionKey);

        if ($normalizedKey === '') {
            throw new InvalidArgumentException('Condition question key cannot be empty.');
        }

        $resolvedOperator = QuestionConditionOperator::fromInput($operator);

        $this->questionKey = $normalizedKey;
        $this->operator = $resolvedOperator;
        $this->value = self::normalizeExpectedValue($resolvedOperator, $value);
    }

    public function matches(mixed $answer): bool
    {
        if (self::isUnanswered($answer)) {
            return false;
        }

        return match ($this->operator) {
            QuestionConditionOperator::Equals => is_scalar($answer) && ! is_array($this->value) && self::valuesMatch($answer, $this->value),
            QuestionConditionOperator::NotEquals => is_scalar($answer) && ! is_array($this->value) && ! self::valuesMatch($answer, $this->value),
            QuestionConditionOperator::GreaterThan => self::compareNumeric($answer, $this->value, static fn (float $a, float $b): bool => $a > $b),
            QuestionConditionOperator::GreaterThanOrEqual => self::compareNumeric($answer, $this->value, static fn (float $a, float $b): bool => $a >= $b),
            QuestionConditionOperator::LessThan => self::compareNumeric($answer, $this->value, static fn (float $a, float $b): bool => $a < $b),
            QuestionConditionOperator::LessThanOrEqual => self::compareNumeric($answer, $this->value, static fn (float $a, float $b): bool => $a <= $b),
            QuestionConditionOperator::In => is_array($this->value) && self::matchesAnyInList($answer, $this->value),
            QuestionConditionOperator::NotIn => is_array($this->value) && ! self::matchesAnyInList($answer, $this->value),
            QuestionConditionOperator::Contains => ! is_array($this->value) && self::answerContains($answer, $this->value),
            QuestionConditionOperator::NotContains => ! is_array($this->value) && ! self::answerContains($answer, $this->value),
        };
    }

    /**
     * @return array{question: string, operator: string, value: string|int|float|bool|array<int, string|int|float|bool>}
     */
    public function toArray(): array
    {
        return [
            'question' => $this->questionKey,
            'operator' => $this->operator->value,
            'value' => $this->value,
        ];
    }

    /**
     * @return string|int|float|bool|array<int, string|int|float|bool>
     */
    private static function normalizeExpectedValue(QuestionConditionOperator $operator, mixed $value): string|int|float|bool|array
    {
        if ($operator->expectsArrayValue()) {
            if (! is_array($value)) {
                throw new InvalidArgumentException("Condition operator [{$operator->value}] requires an array of scalar values.");
            }

            /** @var array<int, string|int|float|bool> $normalized */
            $normalized = [];

            foreach ($value as $item) {
                if (! is_scalar($item)) {
                    throw new InvalidArgumentException("Condition operator [{$operator->value}] only supports scalar array items.");
                }

                $normalized[] = $item;
            }

            return $normalized;
        }

        if ($operator->expectsNumericValue()) {
            if (is_int($value) || is_float($value)) {
                return $value;
            }

            if (is_string($value) && is_numeric($value)) {
                $float = (float) $value;

                return floor($float) === $float ? (int) $float : $float;
            }

            throw new InvalidArgumentException("Condition operator [{$operator->value}] requires a numeric value.");
        }

        if (! is_scalar($value)) {
            throw new InvalidArgumentException("Condition operator [{$operator->value}] requires a scalar value.");
        }

        return $value;
    }

    private static function isUnanswered(mixed $answer): bool
    {
        return $answer === null || $answer === '' || $answer === [];
    }

    /**
     * @param  callable(float, float): bool  $comparator
     */
    private static function compareNumeric(mixed $actual, mixed $expected, callable $comparator): bool
    {
        if (is_bool($actual) || ! is_numeric($actual) || is_bool($expected) || ! is_numeric($expected)) {
            return false;
        }

        return $comparator((float) $actual, (float) $expected);
    }

    /**
     * @param  array<int, string|int|float|bool>  $expectedList
     */
    private static function matchesAnyInList(mixed $actual, array $expectedList): bool
    {
        if (is_array($actual)) {
            foreach ($actual as $actualItem) {
                if (! is_scalar($actualItem)) {
                    continue;
                }

                foreach ($expectedList as $expectedItem) {
                    if (self::valuesMatch($actualItem, $expectedItem)) {
                        return true;
                    }
                }
            }

            return false;
        }

        if (! is_scalar($actual)) {
            return false;
        }

        foreach ($expectedList as $expectedItem) {
            if (self::valuesMatch($actual, $expectedItem)) {
                return true;
            }
        }

        return false;
    }

    private static function answerContains(mixed $actual, string|int|float|bool $expected): bool
    {
        if (is_array($actual)) {
            foreach ($actual as $item) {
                if (is_scalar($item) && self::valuesMatch($item, $expected)) {
                    return true;
                }
            }

            return false;
        }

        if (is_string($actual) && is_string($expected)) {
            return $expected !== '' && str_contains($actual, $expected);
        }

        return false;
    }

    private static function valuesMatch(string|int|float|bool $actual, string|int|float|bool $expected): bool
    {
        if (is_bool($actual) || is_bool($expected)) {
            $actualBool = self::toBoolean($actual);
            $expectedBool = self::toBoolean($expected);

            return $actualBool !== null && $expectedBool !== null && $actualBool === $expectedBool;
        }

        if ((is_int($actual) || is_float($actual) || is_int($expected) || is_float($expected))
            && is_numeric($actual)
            && is_numeric($expected)
        ) {
            return (float) $actual === (float) $expected;
        }

        return (string) $actual === (string) $expected;
    }

    private static function toBoolean(string|int|float|bool $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            if ((float) $value === 1.0) {
                return true;
            }

            if ((float) $value === 0.0) {
                return false;
            }

            return null;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => null,
        };
    }
}
