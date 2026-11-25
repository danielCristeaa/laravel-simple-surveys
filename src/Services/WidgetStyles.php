<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

final class WidgetStyles
{
    private static ?string $cachedCss = null;

    public static function css(): string
    {
        if (self::$cachedCss !== null) {
            return self::$cachedCss;
        }

        $path = __DIR__.'/../../resources/css/widget.css';
        $contents = is_file($path) ? file_get_contents($path) : false;

        self::$cachedCss = is_string($contents) ? $contents : '';

        return self::$cachedCss;
    }

    public static function flushCache(): void
    {
        self::$cachedCss = null;
    }
}
