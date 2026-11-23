<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Facades;

use Carbon\CarbonInterface;
use DanielCristea\SimpleSurveys\Contracts\SurveyDefinition;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(Survey|SurveyDefinition|class-string<SurveyDefinition> $survey)
 * @method static Collection<string, Survey> all()
 * @method static Collection<string, Survey> active(?CarbonInterface $now = null)
 * @method static Survey|null find(string $key)
 * @method static bool has(string $key)
 * @method static void clear()
 *
 * @see \DanielCristea\SimpleSurveys\SimpleSurveys
 */
final class SimpleSurveys extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \DanielCristea\SimpleSurveys\SimpleSurveys::class;
    }
}
