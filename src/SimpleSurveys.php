<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use Carbon\CarbonInterface;
use DanielCristea\SimpleSurveys\Contracts\SurveyDefinition;
use DanielCristea\SimpleSurveys\Services\SurveyRegistry;
use Illuminate\Support\Collection;

final class SimpleSurveys
{
    public const VERSION = '0.1.0';

    public function __construct(
        private readonly SurveyRegistry $registry,
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
    }
}
