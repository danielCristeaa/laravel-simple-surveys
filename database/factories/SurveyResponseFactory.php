<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Database\Factories;

use DanielCristea\SimpleSurveys\Enums\ResponseStatus;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<SurveyResponse>
 */
final class SurveyResponseFactory extends Factory
{
    protected $model = SurveyResponse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'survey_key' => 'sample-survey',
            'respondent_type' => 'App\\Models\\User',
            'respondent_id' => $this->faker->unique()->numberBetween(1, 100_000),
            'status' => ResponseStatus::Completed,
            'answers' => ['feedback' => $this->faker->sentence()],
            'metadata' => null,
            'completed_at' => now(),
            'dismissed_at' => null,
        ];
    }

    public function forSurvey(string $surveyKey): self
    {
        return $this->state(fn (): array => [
            'survey_key' => $surveyKey,
        ]);
    }

    public function forRespondent(Model $respondent): self
    {
        return $this->state(fn (): array => [
            'respondent_type' => $respondent->getMorphClass(),
            'respondent_id' => $respondent->getKey(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    public function completed(array $answers = []): self
    {
        return $this->state(fn (): array => [
            'status' => ResponseStatus::Completed,
            'answers' => $answers,
            'completed_at' => now(),
            'dismissed_at' => null,
        ]);
    }

    public function dismissed(): self
    {
        return $this->state(fn (): array => [
            'status' => ResponseStatus::Dismissed,
            'answers' => null,
            'completed_at' => null,
            'dismissed_at' => now(),
        ]);
    }
}
