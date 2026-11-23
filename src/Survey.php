<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class Survey
{
    private string $title;

    private ?string $bannerText = null;

    private ?string $description = null;

    private string $submitLabel = 'Submit';

    private string $thankYouMessage = 'Thank you for your feedback!';

    private ?CarbonImmutable $startsAt = null;

    private ?CarbonImmutable $endsAt = null;

    private bool $isDraft = false;

    private bool $isClosed = false;

    private bool $dismissible = true;

    private int $priority = 0;

    /** @var Closure(Builder<Model>): void|null */
    private ?Closure $targetQueryCallback = null;

    /** @var Closure(Authenticatable): bool|null */
    private ?Closure $targetUserCallback = null;

    /** @var array<string, Question> */
    private array $questions = [];

    private function __construct(
        private readonly string $key,
    ) {
        if (trim($key) === '') {
            throw new InvalidArgumentException('Survey key cannot be empty.');
        }

        $this->title = Str::headline($key);
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function banner(string $bannerText): self
    {
        $this->bannerText = $bannerText;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function submitLabel(string $submitLabel): self
    {
        $this->submitLabel = $submitLabel;

        return $this;
    }

    public function thankYouMessage(string $thankYouMessage): self
    {
        $this->thankYouMessage = $thankYouMessage;

        return $this;
    }

    public function startsAt(CarbonInterface|string|null $startsAt): self
    {
        $this->startsAt = $this->parseDate($startsAt);

        return $this;
    }

    public function endsAt(CarbonInterface|string|null $endsAt): self
    {
        $this->endsAt = $this->parseDate($endsAt);

        return $this;
    }

    public function activeBetween(CarbonInterface|string $startsAt, CarbonInterface|string $endsAt): self
    {
        $this->startsAt = $this->parseDate($startsAt);
        $this->endsAt = $this->parseDate($endsAt);

        return $this;
    }

    public function until(CarbonInterface|string $endsAt): self
    {
        return $this->endsAt($endsAt);
    }

    public function draft(bool $draft = true): self
    {
        $this->isDraft = $draft;

        return $this;
    }

    public function closed(bool $closed = true): self
    {
        $this->isClosed = $closed;

        return $this;
    }

    public function dismissible(bool $dismissible = true): self
    {
        $this->dismissible = $dismissible;

        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Filter eligible users using an Eloquent query callback.
     *
     * @param  Closure(Builder<Model>): void  $callback
     */
    public function target(Closure $callback): self
    {
        $this->targetQueryCallback = $callback;

        return $this;
    }

    /**
     * Filter eligible users using a direct predicate callback on the authenticated user.
     *
     * @param  Closure(Authenticatable): bool  $callback
     */
    public function targetUser(Closure $callback): self
    {
        $this->targetUserCallback = $callback;

        return $this;
    }

    /**
     * @param  array<int, Question>  $questions
     */
    public function questions(array $questions): self
    {
        $this->questions = [];

        foreach ($questions as $question) {
            $this->addQuestion($question);
        }

        return $this;
    }

    public function addQuestion(Question $question): self
    {
        if (array_key_exists($question->key(), $this->questions)) {
            throw new InvalidArgumentException("Duplicate question key [{$question->key()}] in survey [{$this->key}].");
        }

        $this->questions[$question->key()] = $question;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBannerText(): string
    {
        return $this->bannerText ?? $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSubmitLabel(): string
    {
        return $this->submitLabel;
    }

    public function getThankYouMessage(): string
    {
        return $this->thankYouMessage;
    }

    public function getStartsAt(): ?CarbonImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): ?CarbonImmutable
    {
        return $this->endsAt;
    }

    public function isDismissible(): bool
    {
        return $this->dismissible;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @return Closure(Builder<Model>): void|null
     */
    public function getTargetQueryCallback(): ?Closure
    {
        return $this->targetQueryCallback;
    }

    /**
     * @return Closure(Authenticatable): bool|null
     */
    public function getTargetUserCallback(): ?Closure
    {
        return $this->targetUserCallback;
    }

    /**
     * @return array<int, Question>
     */
    public function getQuestions(): array
    {
        return array_values($this->questions);
    }

    public function question(string $key): ?Question
    {
        return $this->questions[$key] ?? null;
    }

    public function status(?CarbonInterface $now = null): SurveyStatus
    {
        if ($this->isDraft) {
            return SurveyStatus::Draft;
        }

        if ($this->isClosed) {
            return SurveyStatus::Closed;
        }

        $now = $now !== null ? CarbonImmutable::instance($now) : CarbonImmutable::now();

        if ($this->endsAt !== null && $now->greaterThan($this->endsAt)) {
            return SurveyStatus::Closed;
        }

        if ($this->startsAt !== null && $now->lessThan($this->startsAt)) {
            return SurveyStatus::Scheduled;
        }

        return SurveyStatus::Active;
    }

    public function isActive(?CarbonInterface $now = null): bool
    {
        return $this->status($now) === SurveyStatus::Active;
    }

    private function parseDate(CarbonInterface|string|null $date): ?CarbonImmutable
    {
        if ($date === null) {
            return null;
        }

        if ($date instanceof CarbonInterface) {
            return CarbonImmutable::instance($date);
        }

        return CarbonImmutable::parse($date);
    }
}
