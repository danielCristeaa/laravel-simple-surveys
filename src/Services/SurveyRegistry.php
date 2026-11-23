<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use Carbon\CarbonInterface;
use DanielCristea\SimpleSurveys\Contracts\SurveyDefinition;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class SurveyRegistry
{
    /** @var array<string, Survey> */
    private array $surveys = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param  Survey|SurveyDefinition|class-string<SurveyDefinition>  $survey
     */
    public function register(Survey|SurveyDefinition|string $survey): void
    {
        $resolved = $this->resolveSurvey($survey);

        $this->surveys[$resolved->key()] = $resolved;
    }

    /**
     * @return Collection<string, Survey>
     */
    public function all(): Collection
    {
        $indexed = [];
        $position = 0;

        foreach ($this->surveys as $key => $survey) {
            $indexed[] = [
                'key' => $key,
                'survey' => $survey,
                'priority' => $survey->getPriority(),
                'position' => $position++,
            ];
        }

        usort($indexed, static function (array $a, array $b): int {
            return $b['priority'] <=> $a['priority']
                ?: $a['position'] <=> $b['position'];
        });

        /** @var array<string, Survey> $sorted */
        $sorted = [];

        foreach ($indexed as $item) {
            $sorted[$item['key']] = $item['survey'];
        }

        return new Collection($sorted);
    }

    /**
     * @return Collection<string, Survey>
     */
    public function active(?CarbonInterface $now = null): Collection
    {
        return $this->all()->filter(
            static fn (Survey $survey): bool => $survey->isActive($now)
        );
    }

    public function find(string $key): ?Survey
    {
        return $this->surveys[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->surveys);
    }

    public function clear(): void
    {
        $this->surveys = [];
    }

    /**
     * @param  Survey|SurveyDefinition|class-string<SurveyDefinition>  $survey
     */
    private function resolveSurvey(Survey|SurveyDefinition|string $survey): Survey
    {
        if ($survey instanceof Survey) {
            return $survey;
        }

        if ($survey instanceof SurveyDefinition) {
            return $survey->definition();
        }

        if (! class_exists($survey) || ! is_subclass_of($survey, SurveyDefinition::class)) {
            throw new InvalidArgumentException(
                sprintf('Class [%s] must implement %s.', $survey, SurveyDefinition::class)
            );
        }

        /** @var SurveyDefinition $definition */
        $definition = $this->container->make($survey);

        return $definition->definition();
    }
}
