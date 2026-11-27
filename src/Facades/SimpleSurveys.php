<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Facades;

use Carbon\CarbonInterface;
use Closure;
use DanielCristea\SimpleSurveys\Contracts\SurveyDefinition;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\PageContext;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @method static void register(Survey|SurveyDefinition|class-string<SurveyDefinition> $survey)
 * @method static Collection<string, Survey> all()
 * @method static Collection<string, Survey> active(?CarbonInterface $now = null)
 * @method static Survey|null find(string $key)
 * @method static bool has(string $key)
 * @method static void clear()
 * @method static void resolveTrackingContext(?Closure $callback)
 * @method static void resolveNotifiables(?Closure $callback)
 * @method static void resolveCspNonce(?Closure $callback)
 * @method static string|null cspNonce(?string $explicit = null)
 * @method static Survey|null forUser(?Authenticatable $user, ?CarbonInterface $now = null, ?PageContext $context = null)
 * @method static Collection<int, Survey> eligibleFor(?Authenticatable $user, ?CarbonInterface $now = null, ?PageContext $context = null)
 * @method static bool isEligible(Survey|string $survey, ?Authenticatable $user, ?CarbonInterface $now = null, ?PageContext $context = null)
 * @method static Collection<int, SurveyRecord> sync()
 * @method static Collection<int, array{key: string, title: string, description: string|null, status: SurveyStatus, registered: bool, questions_count: int, impressions_count: int, opens_count: int, abandoned_count: int, responses_count: int, dismissals_count: int, response_rate: float|null, completion_rate: float|null}> summaries()
 * @method static array{key: string, title: string, description: string|null, status: SurveyStatus, registered: bool, impressions_count: int, opens_count: int, abandoned_count: int, responses_count: int, dismissals_count: int, response_rate: float|null, completion_rate: float|null, questions: array<int, array<string, mixed>>, responses: LengthAwarePaginator<int, SurveyResponse>|null}|null results(Survey|string $survey, int $perPage = 15)
 * @method static SurveyRecord|null close(Survey|string $survey)
 * @method static SurveyRecord|null reopen(Survey|string $survey)
 * @method static bool reset(Survey|string $survey)
 * @method static StreamedResponse|null exportCsv(Survey|string $survey, ?string $filename = null)
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
