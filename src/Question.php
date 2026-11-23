<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use DanielCristea\SimpleSurveys\Enums\QuestionType;
use InvalidArgumentException;

final class Question
{
    private ?string $helpText = null;

    private ?string $placeholder = null;

    private bool $required = false;

    /** @var array<string, string> */
    private array $options = [];

    private int|float|null $min = null;

    private int|float|null $max = null;

    private int|float|null $step = null;

    private ?int $maxLength = null;

    /** @var array<int, mixed> */
    private array $rules = [];

    private function __construct(
        private readonly string $key,
        private readonly QuestionType $type,
        private readonly string $label,
    ) {
        if (trim($key) === '') {
            throw new InvalidArgumentException('Question key cannot be empty.');
        }

        if ($type === QuestionType::Rating) {
            $this->min = 1;
            $this->max = 5;
        }
    }

    public static function text(string $key, string $label): self
    {
        return new self($key, QuestionType::Text, $label);
    }

    public static function textarea(string $key, string $label): self
    {
        return new self($key, QuestionType::Textarea, $label);
    }

    public static function number(string $key, string $label): self
    {
        return new self($key, QuestionType::Number, $label);
    }

    public static function select(string $key, string $label): self
    {
        return new self($key, QuestionType::Select, $label);
    }

    public static function radio(string $key, string $label): self
    {
        return new self($key, QuestionType::Radio, $label);
    }

    public static function checkbox(string $key, string $label): self
    {
        return new self($key, QuestionType::Checkbox, $label);
    }

    public static function rating(string $key, string $label): self
    {
        return new self($key, QuestionType::Rating, $label);
    }

    public static function boolean(string $key, string $label): self
    {
        return new self($key, QuestionType::Boolean, $label);
    }

    public function helpText(?string $helpText): self
    {
        $this->helpText = $helpText;

        return $this;
    }

    public function placeholder(?string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function optional(): self
    {
        $this->required = false;

        return $this;
    }

    /**
     * @param  array<int|string, string>  $options
     */
    public function options(array $options): self
    {
        $normalized = [];

        if (array_is_list($options)) {
            foreach ($options as $option) {
                $normalized[$option] = $option;
            }
        } else {
            foreach ($options as $value => $label) {
                $normalized[(string) $value] = $label;
            }
        }

        $this->options = $normalized;

        return $this;
    }

    public function min(int|float|null $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function max(int|float|null $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function step(int|float|null $step): self
    {
        $this->step = $step;

        return $this;
    }

    public function scale(int $min, int $max): self
    {
        if ($min >= $max) {
            throw new InvalidArgumentException('Rating scale minimum must be less than maximum.');
        }

        $this->min = $min;
        $this->max = $max;

        return $this;
    }

    public function maxLength(?int $maxLength): self
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function type(): QuestionType
    {
        return $this->type;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getMin(): int|float|null
    {
        return $this->min;
    }

    public function getMax(): int|float|null
    {
        return $this->max;
    }

    public function getStep(): int|float|null
    {
        return $this->step;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    /**
     * @return array<int, mixed>
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
