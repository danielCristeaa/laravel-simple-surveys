<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \DanielCristea\SimpleSurveys\SimpleSurveys
 */
final class SimpleSurveys extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \DanielCristea\SimpleSurveys\SimpleSurveys::class;
    }
}
