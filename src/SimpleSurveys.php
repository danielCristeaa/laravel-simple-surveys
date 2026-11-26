<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use Carbon\CarbonInterface;
use Closure;
use DanielCristea\SimpleSurveys\Contracts\SurveyDefinition;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Services\SurveyEligibilityResolver;
use DanielCristea\SimpleSurveys\Services\SurveyNotificationDispatcher;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use DanielCristea\SimpleSurveys\Services\SurveySynchronizer;
use DanielCristea\SimpleSurveys\Services\SurveyTrackingContextResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

final class SimpleSurveys
{
    public const VERSION = '0.1.0';

    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveyEligibilityResolver $eligibility,
        private readonly SurveySynchronizer $synchronizer,
        private readonly SurveyTrackingContextResolver $trackingContext,
        private readonly SurveyNotificationDispatcher $notificationDispatcher,
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
}
