<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Models;

use Carbon\CarbonInterface;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $title
 * @property string $banner_text
 * @property string|null $description
 * @property SurveyStatus $status
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 * @property bool $dismissible
 * @property int $priority
 * @property array<string, mixed>|null $appearance
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, SurveyQuestion> $questions
 * @property-read Collection<int, SurveyResponse> $responses
 * @property-read Collection<int, SurveyDismissal> $dismissals
 * @property-read Collection<int, SurveyOpen> $opens
 * @property-read Collection<int, SurveyImpression> $impressions
 */
class SurveyRecord extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'key',
        'title',
        'banner_text',
        'description',
        'status',
        'starts_at',
        'ends_at',
        'dismissible',
        'priority',
        'appearance',
    ];

    public function getTable(): string
    {
        $configured = config('simple-surveys.tables.surveys', 'simple_surveys');

        return is_string($configured) && $configured !== ''
            ? $configured
            : 'simple_surveys';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SurveyStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'dismissible' => 'boolean',
            'priority' => 'integer',
            'appearance' => 'array',
        ];
    }

    /**
     * @return HasMany<SurveyQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class, 'survey_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<SurveyResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class, 'survey_id');
    }

    /**
     * @return HasMany<SurveyDismissal, $this>
     */
    public function dismissals(): HasMany
    {
        return $this->hasMany(SurveyDismissal::class, 'survey_id');
    }

    /**
     * @return HasMany<SurveyOpen, $this>
     */
    public function opens(): HasMany
    {
        return $this->hasMany(SurveyOpen::class, 'survey_id');
    }

    /**
     * @return HasMany<SurveyImpression, $this>
     */
    public function impressions(): HasMany
    {
        return $this->hasMany(SurveyImpression::class, 'survey_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }
}
