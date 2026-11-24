<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $survey_id
 * @property string $respondent_type
 * @property int|string $respondent_id
 * @property int $open_count
 * @property CarbonInterface $first_opened_at
 * @property CarbonInterface $last_opened_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read SurveyRecord $survey
 * @property-read Model|null $respondent
 */
class SurveyOpen extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'survey_id',
        'respondent_type',
        'respondent_id',
        'open_count',
        'first_opened_at',
        'last_opened_at',
    ];

    public function getTable(): string
    {
        $configured = config('simple-surveys.tables.opens', 'simple_survey_opens');

        return is_string($configured) && $configured !== ''
            ? $configured
            : 'simple_survey_opens';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'open_count' => 'integer',
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SurveyRecord, $this>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(SurveyRecord::class, 'survey_id');
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
    public function scopeForSurvey(Builder $query, SurveyRecord|string $survey): Builder
    {
        if ($survey instanceof SurveyRecord) {
            return $query->where('survey_id', $survey->getKey());
        }

        return $query->whereHas('survey', static function (Builder $surveyQuery) use ($survey): void {
            $surveyQuery->where('key', $survey);
        });
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
}
