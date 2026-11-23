<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Models;

use Carbon\CarbonInterface;
use DanielCristea\SimpleSurveys\Database\Factories\SurveyResponseFactory;
use DanielCristea\SimpleSurveys\Enums\ResponseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $survey_key
 * @property string $respondent_type
 * @property int|string $respondent_id
 * @property ResponseStatus $status
 * @property array<string, mixed>|null $answers
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $dismissed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Model|null $respondent
 */
class SurveyResponse extends Model
{
    /** @use HasFactory<SurveyResponseFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'survey_key',
        'respondent_type',
        'respondent_id',
        'status',
        'answers',
        'metadata',
        'completed_at',
        'dismissed_at',
    ];

    public function getTable(): string
    {
        $configured = config('simple-surveys.table_name', 'simple_survey_responses');

        return is_string($configured) && $configured !== ''
            ? $configured
            : 'simple_survey_responses';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ResponseStatus::class,
            'answers' => 'array',
            'metadata' => 'array',
            'completed_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function respondent(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForSurvey(Builder $query, string $surveyKey): Builder
    {
        return $query->where('survey_key', $surveyKey);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', ResponseStatus::Completed);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDismissed(Builder $query): Builder
    {
        return $query->where('status', ResponseStatus::Dismissed);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForRespondent(Builder $query, Model $respondent): Builder
    {
        return $query
            ->where('respondent_type', $respondent->getMorphClass())
            ->where('respondent_id', $respondent->getKey());
    }

    public function isCompleted(): bool
    {
        return $this->status === ResponseStatus::Completed;
    }

    public function isDismissed(): bool
    {
        return $this->status === ResponseStatus::Dismissed;
    }

    public function answer(string $questionKey, mixed $default = null): mixed
    {
        $answers = $this->answers ?? [];

        return array_key_exists($questionKey, $answers)
            ? $answers[$questionKey]
            : $default;
    }

    protected static function newFactory(): SurveyResponseFactory
    {
        return SurveyResponseFactory::new();
    }
}
