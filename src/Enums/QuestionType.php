<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Enums;

enum QuestionType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Rating = 'rating';
    case Boolean = 'boolean';

    public function hasOptions(): bool
    {
        return match ($this) {
            self::Select, self::Radio, self::Checkbox => true,
            default => false,
        };
    }

    public function isNumeric(): bool
    {
        return match ($this) {
            self::Number, self::Rating => true,
            default => false,
        };
    }
}
