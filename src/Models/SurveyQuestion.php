<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Models;

use Carbon\CarbonInterface;
use DanielCristea\SimpleSurveys\Enums\QuestionType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $survey_id
 * @property string $key
 * @property QuestionType $type
 * @property string $label
 * @property string|null $help_text
 * @property string|null $placeholder
 * @property array<string, string>|null $options
 * @property array<string, mixed>|null $constraints
 * @property bool $is_required
 * @property int $sort_order
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read SurveyRecord $survey
 * @property-read Collection<int, SurveyAnswer> $answers
 */
class SurveyQuestion extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'survey_id',
        'key',
        'type',
        'label',
        'help_text',
        'placeholder',
        'options',
        'constraints',
        'is_required',
        'sort_order',
    ];

    public function getTable(): string
    {
        $configured = config('simple-surveys.tables.questions', 'simple_survey_questions');

        return is_string($configured) && $configured !== ''
            ? $configured
            : 'simple_survey_questions';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'options' => 'array',
            'constraints' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
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
     * @return HasMany<SurveyAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class, 'question_id');
    }
}
