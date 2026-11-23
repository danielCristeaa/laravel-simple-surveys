<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Models;

use Carbon\CarbonInterface;
use DanielCristea\SimpleSurveys\Enums\QuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $response_id
 * @property int $question_id
 * @property string|null $value
 * @property float|null $value_numeric
 * @property array<int, string>|null $value_json
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read SurveyResponse $response
 * @property-read SurveyQuestion $question
 */
class SurveyAnswer extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'response_id',
        'question_id',
        'value',
        'value_numeric',
        'value_json',
    ];

    public function getTable(): string
    {
        $configured = config('simple-surveys.tables.answers', 'simple_survey_answers');

        return is_string($configured) && $configured !== ''
            ? $configured
            : 'simple_survey_answers';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_numeric' => 'float',
            'value_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SurveyResponse, $this>
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'response_id');
    }

    /**
     * @return BelongsTo<SurveyQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'question_id');
    }

    public function typedValue(): mixed
    {
        if ($this->value_json !== null) {
            return $this->value_json;
        }

        if ($this->value === null && $this->value_numeric === null) {
            return null;
        }

        $type = $this->question->type;

        return match ($type) {
            QuestionType::Rating => $this->value_numeric !== null ? (int) $this->value_numeric : null,
            QuestionType::Number => $this->value_numeric !== null
                ? (floor($this->value_numeric) === $this->value_numeric ? (int) $this->value_numeric : $this->value_numeric)
                : null,
            QuestionType::Boolean => $this->value === '1' || $this->value_numeric === 1.0,
            default => $this->value,
        };
    }
}
