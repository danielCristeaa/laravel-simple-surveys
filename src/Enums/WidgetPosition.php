<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Enums;

enum WidgetPosition: string
{
    case BottomRight = 'bottom-right';
    case BottomLeft = 'bottom-left';
    case TopRight = 'top-right';
    case TopLeft = 'top-left';
    case Center = 'center';

    public function label(): string
    {
        return match ($this) {
            self::BottomRight => 'Bottom Right',
            self::BottomLeft => 'Bottom Left',
            self::TopRight => 'Top Right',
            self::TopLeft => 'Top Left',
            self::Center => 'Centered Modal',
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
