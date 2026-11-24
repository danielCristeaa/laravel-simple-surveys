<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Closure;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DateInterval;
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

    private ?CarbonInterval $repromptAfter = null;

    private int $priority = 0;

    /** @var Closure(Builder<Model>): void|null */
    private ?Closure $targetQueryCallback = null;

    /** @var Closure(Authenticatable): bool|null */
    private ?Closure $targetUserCallback = null;

    /** @var array<int, string> */
    private array $onRoutes = [];

    /** @var array<int, string> */
    private array $exceptRoutes = [];

    /** @var array<int, string> */
    private array $onPaths = [];

    /** @var array<int, string> */
    private array $exceptPaths = [];

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

    public function repromptAfter(DateInterval|int|null $interval): self
    {
        if ($interval === null) {
            $this->repromptAfter = null;

            return $this;
        }

        if (is_int($interval)) {
            return $this->repromptAfterDays($interval);
        }

        $converted = CarbonInterval::instance($interval);
        $now = CarbonImmutable::now();

        if (! $now->add($converted)->greaterThan($now)) {
            throw new InvalidArgumentException('Re-prompt interval must be positive.');
        }

        $this->repromptAfter = $converted;

        return $this;
    }

    public function repromptAfterDays(int $days): self
    {
        if ($days <= 0) {
            throw new InvalidArgumentException('Re-prompt days must be greater than zero.');
        }

        $this->repromptAfter = CarbonInterval::days($days);

        return $this;
    }

    public function repromptAfterHours(int $hours): self
    {
        if ($hours <= 0) {
            throw new InvalidArgumentException('Re-prompt hours must be greater than zero.');
        }

        $this->repromptAfter = CarbonInterval::hours($hours);

        return $this;
    }

    public function repromptAfterMinutes(int $minutes): self
    {
        if ($minutes <= 0) {
            throw new InvalidArgumentException('Re-prompt minutes must be greater than zero.');
        }

        $this->repromptAfter = CarbonInterval::minutes($minutes);

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
     * Restrict the survey to specific route names (supports wildcards, e.g. "billing.*").
     *
     * @param  string|array<int, string>  $routes
     */
    public function onRoutes(string|array $routes): self
    {
        $this->onRoutes = array_values(is_array($routes) ? $routes : [$routes]);

        return $this;
    }

    /**
     * Exclude the survey from specific route names (supports wildcards, e.g. "admin.*").
     *
     * @param  string|array<int, string>  $routes
     */
    public function exceptRoutes(string|array $routes): self
    {
        $this->exceptRoutes = array_values(is_array($routes) ? $routes : [$routes]);

        return $this;
    }

    /**
     * Restrict the survey to specific URL paths (supports wildcards, e.g. "billing/*").
     *
     * @param  string|array<int, string>  $paths
     */
    public function onPaths(string|array $paths): self
    {
        $items = is_array($paths) ? $paths : [$paths];

        $this->onPaths = array_values(array_map(
            static fn (string $path): string => PageContext::normalizePath($path),
            $items,
        ));

        return $this;
    }

    /**
     * Exclude the survey from specific URL paths (supports wildcards, e.g. "checkout/*").
     *
     * @param  string|array<int, string>  $paths
     */
    public function exceptPaths(string|array $paths): self
    {
        $items = is_array($paths) ? $paths : [$paths];

        $this->exceptPaths = array_values(array_map(
            static fn (string $path): string => PageContext::normalizePath($path),
            $items,
        ));

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

    public function getRepromptAfter(): ?CarbonInterval
    {
        return $this->repromptAfter;
    }

    public function isDismissalExpired(CarbonInterface $dismissedAt, ?CarbonInterface $now = null): bool
    {
        if ($this->repromptAfter === null) {
            return false;
        }

        $reference = $now !== null ? CarbonImmutable::instance($now) : CarbonImmutable::now();

        return CarbonImmutable::instance($dismissedAt)
            ->add($this->repromptAfter)
            ->lessThanOrEqualTo($reference);
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
     * @return array<int, string>
     */
    public function getOnRoutes(): array
    {
        return $this->onRoutes;
    }

    /**
     * @return array<int, string>
     */
    public function getExceptRoutes(): array
    {
        return $this->exceptRoutes;
    }

    /**
     * @return array<int, string>
     */
    public function getOnPaths(): array
    {
        return $this->onPaths;
    }

    /**
     * @return array<int, string>
     */
    public function getExceptPaths(): array
    {
        return $this->exceptPaths;
    }

    public function hasPageTargeting(): bool
    {
        return $this->onRoutes !== []
            || $this->exceptRoutes !== []
            || $this->onPaths !== []
            || $this->exceptPaths !== [];
    }

    public function matchesPage(?PageContext $context): bool
    {
        if (! $this->hasPageTargeting() || $context === null) {
            return true;
        }

        $path = $context->path !== null ? PageContext::normalizePath($context->path) : null;

        if ($this->exceptRoutes !== [] && $context->routeName !== null && Str::is($this->exceptRoutes, $context->routeName)) {
            return false;
        }

        if ($this->exceptPaths !== [] && $path !== null && Str::is($this->exceptPaths, $path)) {
            return false;
        }

        if ($this->onRoutes === [] && $this->onPaths === []) {
            return true;
        }

        $matchesRoute = $this->onRoutes !== []
            && $context->routeName !== null
            && Str::is($this->onRoutes, $context->routeName);

        $matchesPath = $this->onPaths !== []
            && $path !== null
            && Str::is($this->onPaths, $path);

        return $matchesRoute || $matchesPath;
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
