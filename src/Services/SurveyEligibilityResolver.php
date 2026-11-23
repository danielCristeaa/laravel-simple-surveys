<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use Carbon\CarbonInterface;
use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class SurveyEligibilityResolver
{
    public function __construct(
        private readonly SurveyRegistry $registry,
    ) {}

    public function nextFor(?Authenticatable $user, ?CarbonInterface $now = null): ?Survey
    {
        if (! $user instanceof Model) {
            return null;
        }

        $activeSurveys = $this->registry->active($now);

        if ($activeSurveys->isEmpty()) {
            return null;
        }

        $excludedKeys = $this->excludedSurveyKeys($user, $activeSurveys->keys()->all());

        foreach ($activeSurveys as $survey) {
            if (in_array($survey->key(), $excludedKeys, true)) {
                continue;
            }

            if ($this->matchesTarget($survey, $user)) {
                return $survey;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Survey>
     */
    public function eligibleFor(?Authenticatable $user, ?CarbonInterface $now = null): Collection
    {
        if (! $user instanceof Model) {
            return new Collection;
        }

        $activeSurveys = $this->registry->active($now);

        if ($activeSurveys->isEmpty()) {
            return new Collection;
        }

        $excludedKeys = $this->excludedSurveyKeys($user, $activeSurveys->keys()->all());

        /** @var array<int, Survey> $eligible */
        $eligible = [];

        foreach ($activeSurveys as $survey) {
            if (in_array($survey->key(), $excludedKeys, true)) {
                continue;
            }

            if ($this->matchesTarget($survey, $user)) {
                $eligible[] = $survey;
            }
        }

        return new Collection($eligible);
    }

    public function isEligible(Survey $survey, ?Authenticatable $user, ?CarbonInterface $now = null): bool
    {
        if (! $user instanceof Model) {
            return false;
        }

        if (! $survey->isActive($now)) {
            return false;
        }

        $excludedKeys = $this->excludedSurveyKeys($user, [$survey->key()]);

        if (in_array($survey->key(), $excludedKeys, true)) {
            return false;
        }

        return $this->matchesTarget($survey, $user);
    }

    /**
     * @param  array<int, string>  $surveyKeys
     * @return array<int, string>
     */
    private function excludedSurveyKeys(Model $user, array $surveyKeys): array
    {
        /** @var array<int, string> $recordMap */
        $recordMap = SurveyRecord::query()
            ->whereIn('key', $surveyKeys)
            ->pluck('key', 'id')
            ->all();

        if ($recordMap === []) {
            return [];
        }

        $recordIds = array_keys($recordMap);

        /** @var array<int, int> $respondedIds */
        $respondedIds = SurveyResponse::query()
            ->forRespondent($user)
            ->whereIn('survey_id', $recordIds)
            ->pluck('survey_id')
            ->all();

        /** @var array<int, int> $dismissedIds */
        $dismissedIds = SurveyDismissal::query()
            ->forRespondent($user)
            ->whereIn('survey_id', $recordIds)
            ->pluck('survey_id')
            ->all();

        $excludedIds = array_unique([...$respondedIds, ...$dismissedIds]);

        $excludedKeys = [];

        foreach ($excludedIds as $id) {
            if (isset($recordMap[$id])) {
                $excludedKeys[] = $recordMap[$id];
            }
        }

        return $excludedKeys;
    }

    private function matchesTarget(Survey $survey, Model&Authenticatable $user): bool
    {
        $userCallback = $survey->getTargetUserCallback();

        if ($userCallback !== null && ! $userCallback($user)) {
            return false;
        }

        $queryCallback = $survey->getTargetQueryCallback();

        if ($queryCallback === null) {
            return true;
        }

        /** @var Builder<Model> $query */
        $query = $user->newQuery()->whereKey($user->getKey());

        $query->where(static function (Builder $nested) use ($queryCallback): void {
            $queryCallback($nested);
        });

        return $query->exists();
    }
}
