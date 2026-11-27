<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Models\SurveyQuestion;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Support\Collection;

final class SurveySynchronizer
{
    public function __construct(
        private readonly SurveyRegistry $registry,
        private readonly SurveyStatusResolver $statusResolver,
    ) {}

    public function sync(Survey $survey): SurveyRecord
    {
        $existing = SurveyRecord::query()->byKey($survey->key())->first();

        if ($existing === null) {
            $record = SurveyRecord::query()->create([
                'key' => $survey->key(),
                'title' => $survey->getTitle(),
                'banner_text' => $survey->getBannerText(),
                'description' => $survey->getDescription(),
                'status' => $survey->status(),
                'starts_at' => $survey->getStartsAt(),
                'ends_at' => $survey->getEndsAt(),
                'dismissible' => $survey->isDismissible(),
                'priority' => $survey->getPriority(),
            ]);
        } else {
            $attributes = [
                'title' => $survey->getTitle(),
                'banner_text' => $survey->getBannerText(),
                'description' => $survey->getDescription(),
                'dismissible' => $survey->isDismissible(),
                'priority' => $survey->getPriority(),
            ];

            if ($existing->status !== SurveyStatus::Closed) {
                $startsAt = $survey->getStartsAt();
                $codeEndsAt = $survey->getEndsAt();

                // Preserve null ends_at cleared by SurveyLifecycleManager::reopen() when code still holds a past ends_at.
                $preserveReopenedEndsAt = $existing->status === SurveyStatus::Active
                    && $existing->ends_at === null
                    && $codeEndsAt !== null
                    && $codeEndsAt->lessThanOrEqualTo(now());

                $endsAt = $preserveReopenedEndsAt ? null : $codeEndsAt;

                $attributes['status'] = match (true) {
                    $survey->isDraft() => SurveyStatus::Draft,
                    $survey->isClosed() => SurveyStatus::Closed,
                    default => $this->statusResolver->resolveForWindow($startsAt, $endsAt),
                };
                $attributes['starts_at'] = $startsAt;
                $attributes['ends_at'] = $endsAt;
            }

            $existing->update($attributes);

            $record = $existing;
        }

        foreach ($survey->getQuestions() as $index => $question) {
            $constraints = array_filter([
                'min' => $question->getMin(),
                'max' => $question->getMax(),
                'step' => $question->getStep(),
                'max_length' => $question->getMaxLength(),
            ], static fn (mixed $value): bool => $value !== null);

            SurveyQuestion::query()->updateOrCreate(
                [
                    'survey_id' => $record->getKey(),
                    'key' => $question->key(),
                ],
                [
                    'type' => $question->type(),
                    'label' => $question->label(),
                    'help_text' => $question->getHelpText(),
                    'placeholder' => $question->getPlaceholder(),
                    'options' => $question->getOptions() !== [] ? $question->getOptions() : null,
                    'constraints' => $constraints !== [] ? $constraints : null,
                    'is_required' => $question->isRequired(),
                    'sort_order' => $index,
                ],
            );
        }

        return $record->load('questions');
    }

    /**
     * @return Collection<int, SurveyRecord>
     */
    public function syncAll(): Collection
    {
        /** @var array<int, SurveyRecord> $records */
        $records = [];

        foreach ($this->registry->all() as $survey) {
            $records[] = $this->sync($survey);
        }

        return new Collection($records);
    }
}
