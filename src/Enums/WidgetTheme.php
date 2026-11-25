<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Enums;

enum WidgetTheme: string
{
    case Light = 'light';
    case Dark = 'dark';
    case Auto = 'auto';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'Light',
            self::Dark => 'Dark',
            self::Auto => 'Auto (System)',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }
}
