<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Enums;

use InvalidArgumentException;

enum QuestionConditionOperator: string
{
    case Equals = '=';
    case NotEquals = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case In = 'in';
    case NotIn = 'not_in';
    case Contains = 'contains';
    case NotContains = 'not_contains';

    public static function fromInput(self|string $operator): self
    {
        if ($operator instanceof self) {
            return $operator;
        }

        $normalized = strtolower(trim($operator));

        return match ($normalized) {
            '=', '==' => self::Equals,
            '!=', '<>' => self::NotEquals,
            '>' => self::GreaterThan,
            '>=' => self::GreaterThanOrEqual,
            '<' => self::LessThan,
            '<=' => self::LessThanOrEqual,
            'in' => self::In,
            'not_in' => self::NotIn,
            'contains' => self::Contains,
            'not_contains' => self::NotContains,
            default => throw new InvalidArgumentException("Unsupported question condition operator [{$operator}]."),
        };
    }

    public function expectsNumericValue(): bool
    {
        return match ($this) {
            self::GreaterThan,
            self::GreaterThanOrEqual,
            self::LessThan,
            self::LessThanOrEqual => true,
            default => false,
        };
    }

    public function expectsArrayValue(): bool
    {
        return $this === self::In || $this === self::NotIn;
    }
}
