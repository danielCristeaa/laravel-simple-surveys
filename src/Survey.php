<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Closure;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Enums\WidgetIconPreset;
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DateInterval;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class Survey
{
    private string $title;

    private ?string $bannerText = null;

    private ?WidgetIcon $icon = null;

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

    /** @var array<int, array{url: string, events: array<int, string>, secret: string|null, headers: array<string, string>}> */
    private array $webhooks = [];

    private bool $webhooksDisabled = false;

    /** @var array<int, array{channel: string, route: mixed}> */
    private array $notificationRoutes = [];

    /** @var array<int, array{resolver: Closure(SurveySubmitted): mixed, channels: array<int, string>|null}> */
    private array $notificationUserResolvers = [];

    /** @var class-string<BaseNotification>|null */
    private ?string $notificationClass = null;

    private bool $notificationsDisabled = false;

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

    /**
     * Customize the widget banner icon for this survey.
     *
     * Accepts a built-in preset name (or WidgetIconPreset enum), "none" to hide
     * the icon, a relative public/ .png/.svg path, or null to inherit defaults.
     */
    public function icon(WidgetIcon|WidgetIconPreset|string|null $icon): self
    {
        if ($icon === null || (is_string($icon) && trim($icon) === '')) {
            $this->icon = null;

            return $this;
        }

        $this->icon = WidgetIcon::from($icon);

        return $this;
    }

    /**
     * Hide the widget banner icon for this survey.
     */
    public function withoutIcon(): self
    {
        $this->icon = WidgetIcon::none();

        return $this;
    }

    public function getIcon(): ?WidgetIcon
    {
        return $this->icon;
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

        $this->validateQuestionConditions();

        return $this;
    }

    public function addQuestion(Question $question): self
    {
        if (array_key_exists($question->key(), $this->questions)) {
            throw new InvalidArgumentException("Duplicate question key [{$question->key()}] in survey [{$this->key}].");
        }

        $this->questions[$question->key()] = $question;
        $this->assertNoCircularConditions();

        return $this;
    }

    /**
     * Register a webhook endpoint for lifecycle events on this survey.
     *
     * @param  array<int, string>  $events
     * @param  array<string, string>  $headers
     */
    public function webhook(
        string $url,
        array $events = ['seen', 'opened', 'dismissed', 'submitted'],
        ?string $secret = null,
        array $headers = [],
    ): self {
        $normalizedUrl = trim($url);

        if ($normalizedUrl === '') {
            throw new InvalidArgumentException('Webhook URL cannot be empty.');
        }

        $normalizedEvents = array_values(array_unique(array_filter(
            array_map(static fn (string $event): string => strtolower(trim($event)), $events),
            static fn (string $event): bool => in_array($event, ['seen', 'opened', 'dismissed', 'submitted'], true),
        )));

        $this->webhooks[] = [
            'url' => $normalizedUrl,
            'events' => $normalizedEvents,
            'secret' => $secret !== null && trim($secret) !== '' ? $secret : null,
            'headers' => $headers,
        ];

        return $this;
    }

    /**
     * @return array<int, array{url: string, events: array<int, string>, secret: string|null, headers: array<string, string>}>
     */
    public function getWebhooks(): array
    {
        return $this->webhooks;
    }

    /**
     * Suppress all global and per-survey webhooks for this survey.
     */
    public function withoutWebhooks(bool $disabled = true): self
    {
        $this->webhooksDisabled = $disabled;

        return $this;
    }

    public function hasWebhooksDisabled(): bool
    {
        return $this->webhooksDisabled;
    }

    /**
     * Register an on-demand notification route for survey submissions.
     */
    public function notify(string $channel, mixed $route): self
    {
        $normalizedChannel = trim($channel);

        if ($normalizedChannel === '') {
            throw new InvalidArgumentException('Notification channel cannot be empty.');
        }

        if ($route === null || (is_string($route) && trim($route) === '') || (is_array($route) && $route === [])) {
            throw new InvalidArgumentException('Notification route cannot be empty.');
        }

        $this->notificationRoutes[] = [
            'channel' => $normalizedChannel,
            'route' => is_string($route) ? trim($route) : $route,
        ];

        return $this;
    }

    /**
     * Send an email notification on survey submission to one or more recipients.
     *
     * @param  string|array<int|string, string>  $recipients
     */
    public function notifyMail(string|array $recipients): self
    {
        return $this->notify('mail', $recipients);
    }

    /**
     * Send a Slack incoming webhook notification on survey submission.
     */
    public function notifySlack(string $webhookUrl): self
    {
        return $this->notify('slack', $webhookUrl);
    }

    /**
     * Resolve notifiable models (e.g. admin User instances) to notify on survey submission.
     *
     * @param  Closure(SurveySubmitted): mixed  $resolver
     * @param  array<int, string>|null  $channels
     */
    public function notifyUsers(Closure $resolver, ?array $channels = null): self
    {
        $this->notificationUserResolvers[] = [
            'resolver' => $resolver,
            'channels' => $channels,
        ];

        return $this;
    }

    /**
     * Override the Laravel Notification class used for this survey's submissions.
     */
    public function notification(?string $notificationClass): self
    {
        if ($notificationClass !== null) {
            $normalized = trim($notificationClass);

            if ($normalized === '' || ! class_exists($normalized) || ! is_a($normalized, BaseNotification::class, true)) {
                throw new InvalidArgumentException(
                    "Survey notification [{$notificationClass}] must extend ".BaseNotification::class.'.',
                );
            }

            $this->notificationClass = $normalized;

            return $this;
        }

        $this->notificationClass = null;

        return $this;
    }

    /**
     * Suppress all global and per-survey submission notifications for this survey.
     */
    public function withoutNotifications(bool $disabled = true): self
    {
        $this->notificationsDisabled = $disabled;

        return $this;
    }

    /**
     * @return array<int, array{channel: string, route: mixed}>
     */
    public function getNotificationRoutes(): array
    {
        return $this->notificationRoutes;
    }

    /**
     * @return array<int, array{resolver: Closure(SurveySubmitted): mixed, channels: array<int, string>|null}>
     */
    public function getNotificationUserResolvers(): array
    {
        return $this->notificationUserResolvers;
    }

    /**
     * @return class-string<BaseNotification>|null
     */
    public function getNotificationClass(): ?string
    {
        return $this->notificationClass;
    }

    public function hasNotificationsDisabled(): bool
    {
        return $this->notificationsDisabled;
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

    private function validateQuestionConditions(): void
    {
        foreach ($this->questions as $question) {
            foreach ($question->getConditions() as $condition) {
                if (! array_key_exists($condition->questionKey, $this->questions)) {
                    throw new InvalidArgumentException(
                        "Question [{$question->key()}] references unknown condition question [{$condition->questionKey}] in survey [{$this->key}].",
                    );
                }
            }
        }

        $this->assertNoCircularConditions();
    }

    private function assertNoCircularConditions(): void
    {
        /** @var array<string, int> $state 0 = unvisited, 1 = visiting, 2 = visited */
        $state = [];

        foreach ($this->questions as $key => $question) {
            if (($state[$key] ?? 0) === 0) {
                $this->visitConditionGraph($key, $state);
            }
        }
    }

    /**
     * @param  array<string, int>  $state
     */
    private function visitConditionGraph(string $key, array &$state): void
    {
        $state[$key] = 1;

        $question = $this->questions[$key] ?? null;

        if ($question !== null) {
            foreach ($question->getConditions() as $condition) {
                $targetKey = $condition->questionKey;

                if (! array_key_exists($targetKey, $this->questions)) {
                    continue;
                }

                $targetState = $state[$targetKey] ?? 0;

                if ($targetState === 1) {
                    throw new InvalidArgumentException(
                        "Circular question condition detected involving [{$targetKey}] in survey [{$this->key}].",
                    );
                }

                if ($targetState === 0) {
                    $this->visitConditionGraph($targetKey, $state);
                }
            }
        }

        $state[$key] = 2;
    }
}
