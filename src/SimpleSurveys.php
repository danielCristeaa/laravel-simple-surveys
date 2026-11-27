<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use Carbon\CarbonInterface;
use Closure;
use DanielCristea\SimpleSurveys\Contracts\SurveyDefinition;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Services\SurveyCsvExporter;
use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyLifecycleManager;
use DanielCristea\SimpleSurveys\Services\SurveyNotificationDispatcher;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveyResultsAggregator;
use DanielCristea\SimpleSurveys\Services\SurveySynchronizer;
use DanielCristea\SimpleSurveys\Services\SurveyTrackingContextResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SimpleSurveys
{
    public const VERSION = '0.1.0';

    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveyEligibilityResolver $eligibility,
        private readonly SurveySynchronizer $synchronizer,
        private readonly SurveyTrackingContextResolver $trackingContext,
        private readonly SurveyNotificationDispatcher $notificationDispatcher,
        private readonly SurveyResultsAggregator $resultsAggregator,
        private readonly SurveyLifecycleManager $lifecycle,
        private readonly SurveyCsvExporter $csvExporter,
    ) {}

    /**
     * @param  Survey|SurveyDefinition|class-string<SurveyDefinition>  $survey
     */
    public function register(Survey|SurveyDefinition|string $survey): void
    {
        $this->registry->register($survey);
    }

    /**
     * @return Collection<string, Survey>
     */
    public function all(): Collection
    {
        return $this->registry->all();
    }

    /**
     * @return Collection<string, Survey>
     */
    public function active(?CarbonInterface $now = null): Collection
    {
        return $this->registry->active($now);
    }

    public function find(string $key): ?Survey
    {
        return $this->registry->find($key);
    }

    public function has(string $key): bool
    {
        return $this->registry->has($key);
    }

    public function clear(): void
    {
        $this->registry->clear();
        $this->trackingContext->clear();
        $this->notificationDispatcher->clear();
    }

    public function resolveTrackingContext(?Closure $callback): void
    {
        $this->trackingContext->resolveUsing($callback);
    }

    public function resolveNotifiables(?Closure $callback): void
    {
        $this->notificationDispatcher->resolveNotifiablesUsing($callback);
    }

    public function forUser(?Authenticatable $user, ?CarbonInterface $now = null, ?PageContext $context = null): ?Survey
    {
        return $this->eligibility->nextFor($user, $now, $context);
    }

    /**
     * @return Collection<int, Survey>
     */
    public function eligibleFor(?Authenticatable $user, ?CarbonInterface $now = null, ?PageContext $context = null): Collection
    {
        return $this->eligibility->eligibleFor($user, $now, $context);
    }

    public function isEligible(Survey|string $survey, ?Authenticatable $user, ?CarbonInterface $now = null, ?PageContext $context = null): bool
    {
        $resolved = is_string($survey) ? $this->find($survey) : $survey;

        if ($resolved === null) {
            return false;
        }

        return $this->eligibility->isEligible($resolved, $user, $now, $context);
    }

    /**
     * @return Collection<int, SurveyRecord>
     */
    public function sync(): Collection
    {
        return $this->synchronizer->syncAll();
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     title: string,
     *     description: string|null,
     *     status: SurveyStatus,
     *     registered: bool,
     *     priority: int,
     *     starts_at: string|null,
     *     ends_at: string|null,
     *     questions_count: int,
     *     impressions_count: int,
     *     opens_count: int,
     *     abandoned_count: int,
     *     responses_count: int,
     *     dismissals_count: int,
     *     response_rate: float|null,
     *     completion_rate: float|null
     * }>
     */
    public function summaries(): Collection
    {
        return $this->resultsAggregator->summaries();
    }

    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     description: string|null,
     *     status: SurveyStatus,
     *     registered: bool,
     *     priority: int,
     *     starts_at: string|null,
     *     ends_at: string|null,
     *     dismissible: bool,
     *     impressions_count: int,
     *     opens_count: int,
     *     abandoned_count: int,
     *     responses_count: int,
     *     dismissals_count: int,
     *     total_responses_count: int,
     *     total_telemetry_count: int,
     *     response_rate: float|null,
     *     completion_rate: float|null,
     *     daily_responses: array<int, array{date: string, label: string, count: int}>,
     *     locales: array<int, array{locale: string, count: int, percentage: float}>,
     *     questions: array<int, array<string, mixed>>,
     *     responses: LengthAwarePaginator<int, SurveyResponse>|null
     * }|null
     */
    public function results(Survey|string $survey, int $perPage = 15): ?array
    {
        $surveyKey = $survey instanceof Survey ? $survey->key() : $survey;

        return $this->resultsAggregator->forSurvey($surveyKey, $perPage);
    }

    public function close(Survey|string $survey): ?SurveyRecord
    {
        return $this->lifecycle->close($survey);
    }

    public function reopen(Survey|string $survey): ?SurveyRecord
    {
        return $this->lifecycle->reopen($survey);
    }

    public function reset(Survey|string $survey): bool
    {
        return $this->lifecycle->reset($survey);
    }

    public function exportCsv(Survey|string $survey, ?string $filename = null): ?StreamedResponse
    {
        return $this->csvExporter->streamDownload($survey, $filename);
    }
}
