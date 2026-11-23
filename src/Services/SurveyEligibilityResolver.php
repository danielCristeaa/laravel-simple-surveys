<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use Carbon\CarbonInterface;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
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

        $candidates = $this->registry->all()->filter(
            static fn (Survey $survey): bool => $survey->status($now) !== SurveyStatus::Draft
        );

        if ($candidates->isEmpty()) {
            return null;
        }

        $recordsByKey = $this->fetchRecordsByKey($candidates->keys()->all());
        $activeSurveys = $this->filterActive($candidates, $recordsByKey, $now);

        if ($activeSurveys->isEmpty()) {
            return null;
        }

        $excludedKeys = $this->excludedSurveyKeys($user, $activeSurveys->keys()->all(), $recordsByKey);

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

        $candidates = $this->registry->all()->filter(
            static fn (Survey $survey): bool => $survey->status($now) !== SurveyStatus::Draft
        );

        if ($candidates->isEmpty()) {
            return new Collection;
        }

        $recordsByKey = $this->fetchRecordsByKey($candidates->keys()->all());
        $activeSurveys = $this->filterActive($candidates, $recordsByKey, $now);

        if ($activeSurveys->isEmpty()) {
            return new Collection;
        }

        $excludedKeys = $this->excludedSurveyKeys($user, $activeSurveys->keys()->all(), $recordsByKey);

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

        $recordsByKey = $this->fetchRecordsByKey([$survey->key()]);

        if (! $this->isSurveyEffectivelyActive($survey, $recordsByKey[$survey->key()] ?? null, $now)) {
            return false;
        }

        $excludedKeys = $this->excludedSurveyKeys($user, [$survey->key()], $recordsByKey);

        if (in_array($survey->key(), $excludedKeys, true)) {
            return false;
        }

        return $this->matchesTarget($survey, $user);
    }

    /**
     * @param  array<int, string>  $surveyKeys
     * @return array<string, SurveyRecord>
     */
    private function fetchRecordsByKey(array $surveyKeys): array
    {
        if ($surveyKeys === []) {
            return [];
        }

        /** @var array<string, SurveyRecord> $records */
        $records = SurveyRecord::query()
            ->whereIn('key', $surveyKeys)
            ->get(['id', 'key', 'status'])
            ->keyBy('key')
            ->all();

        return $records;
    }

    /**
     * @param  Collection<string, Survey>  $candidates
     * @param  array<string, SurveyRecord>  $recordsByKey
     * @return Collection<string, Survey>
     */
    private function filterActive(Collection $candidates, array $recordsByKey, ?CarbonInterface $now): Collection
    {
        return $candidates->filter(
            fn (Survey $survey): bool => $this->isSurveyEffectivelyActive(
                $survey,
                $recordsByKey[$survey->key()] ?? null,
                $now,
            )
        );
    }

    private function isSurveyEffectivelyActive(Survey $survey, ?SurveyRecord $record, ?CarbonInterface $now): bool
    {
        if ($record !== null) {
            return $record->status === SurveyStatus::Active;
        }

        return $survey->isActive($now);
    }

    /**
     * @param  array<int, string>  $surveyKeys
     * @param  array<string, SurveyRecord>  $recordsByKey
     * @return array<int, string>
     */
    private function excludedSurveyKeys(Model $user, array $surveyKeys, array $recordsByKey): array
    {
        /** @var array<int, string> $recordMap */
        $recordMap = [];

        foreach ($surveyKeys as $key) {
            if (isset($recordsByKey[$key])) {
                $recordMap[(int) $recordsByKey[$key]->getKey()] = $key;
            }
        }

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
