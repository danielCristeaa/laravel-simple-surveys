<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use Closure;
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Notifications\SurveySubmittedNotification;
use DanielCristea\SimpleSurveys\Survey;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

final class SurveyNotificationDispatcher
{
    /** @var Closure(SurveySubmitted): mixed|null */
    private ?Closure $notifiablesResolver = null;

    /**
     * @param  Closure(SurveySubmitted): mixed|null  $resolver
     */
    public function resolveNotifiablesUsing(?Closure $resolver): void
    {
        $this->notifiablesResolver = $resolver;
    }

    public function clear(): void
    {
        $this->notifiablesResolver = null;
    }

    public function handle(SurveySubmitted $event): void
    {
        if ($event->survey->hasNotificationsDisabled()) {
            return;
        }

        $globalEnabled = (bool) config('simple-surveys.notifications.enabled', false);
        $globalChannels = $this->normalizeChannels(config('simple-surveys.notifications.channels', ['mail']));
        $surveyRoutes = $event->survey->getNotificationRoutes();
        $surveyUserResolvers = $event->survey->getNotificationUserResolvers();

        if (! $globalEnabled && $surveyRoutes === [] && $surveyUserResolvers === []) {
            return;
        }

        $notificationClass = $this->resolveNotificationClass($event->survey);
        $queued = (bool) config('simple-surveys.notifications.queued', true);
        $queueRaw = config('simple-surveys.notifications.queue');
        $resolvedQueue = is_string($queueRaw) && trim($queueRaw) !== '' ? trim($queueRaw) : null;

        if ($globalEnabled) {
            $this->dispatchGlobalOnDemandRoutes($event, $notificationClass, $globalChannels, $queued, $resolvedQueue);
            $this->dispatchGlobalNotifiables($event, $notificationClass, $globalChannels, $queued, $resolvedQueue);
        }

        foreach ($surveyRoutes as $entry) {
            $notifiable = (new AnonymousNotifiable)->route($entry['channel'], $entry['route']);

            $this->send(
                notifiables: $notifiable,
                notificationClass: $notificationClass,
                event: $event,
                channels: [$entry['channel']],
                queued: $queued,
                queue: $resolvedQueue,
            );
        }

        foreach ($surveyUserResolvers as $entry) {
            $channels = $entry['channels'] !== null
                ? $this->normalizeChannels($entry['channels'])
                : ($globalChannels !== [] ? $globalChannels : ['mail']);

            if ($channels === []) {
                continue;
            }

            $notifiables = $this->normalizeNotifiables(($entry['resolver'])($event));

            if ($notifiables === []) {
                continue;
            }

            $this->send(
                notifiables: $notifiables,
                notificationClass: $notificationClass,
                event: $event,
                channels: $channels,
                queued: $queued,
                queue: $resolvedQueue,
            );
        }
    }

    /**
     * @param  class-string<BaseNotification>  $notificationClass
     * @param  array<int, string>  $globalChannels
     */
    private function dispatchGlobalOnDemandRoutes(
        SurveySubmitted $event,
        string $notificationClass,
        array $globalChannels,
        bool $queued,
        ?string $queue,
    ): void {
        if ($globalChannels === []) {
            return;
        }

        $rawRoutes = config('simple-surveys.notifications.routes', []);

        if (! is_array($rawRoutes)) {
            return;
        }

        $notifiable = new AnonymousNotifiable;
        /** @var array<int, string> $activeChannels */
        $activeChannels = [];

        foreach ($globalChannels as $channel) {
            if (! array_key_exists($channel, $rawRoutes)) {
                continue;
            }

            $route = $rawRoutes[$channel];

            if (! $this->isNonEmptyRoute($route)) {
                continue;
            }

            $notifiable->route($channel, $route);
            $activeChannels[] = $channel;
        }

        if ($activeChannels === []) {
            return;
        }

        $this->send(
            notifiables: $notifiable,
            notificationClass: $notificationClass,
            event: $event,
            channels: $activeChannels,
            queued: $queued,
            queue: $queue,
        );
    }

    /**
     * @param  class-string<BaseNotification>  $notificationClass
     * @param  array<int, string>  $globalChannels
     */
    private function dispatchGlobalNotifiables(
        SurveySubmitted $event,
        string $notificationClass,
        array $globalChannels,
        bool $queued,
        ?string $queue,
    ): void {
        if ($this->notifiablesResolver === null || $globalChannels === []) {
            return;
        }

        $notifiables = $this->normalizeNotifiables(($this->notifiablesResolver)($event));

        if ($notifiables === []) {
            return;
        }

        $this->send(
            notifiables: $notifiables,
            notificationClass: $notificationClass,
            event: $event,
            channels: $globalChannels,
            queued: $queued,
            queue: $queue,
        );
    }

    /**
     * @param  AnonymousNotifiable|array<int, object>  $notifiables
     * @param  class-string<BaseNotification>  $notificationClass
     * @param  array<int, string>  $channels
     */
    private function send(
        AnonymousNotifiable|array $notifiables,
        string $notificationClass,
        SurveySubmitted $event,
        array $channels,
        bool $queued,
        ?string $queue,
    ): void {
        $notification = $this->buildNotification($notificationClass, $event, $channels);

        if ($queued) {
            if ($queue !== null && method_exists($notification, 'onQueue')) {
                $notification->onQueue($queue);
            }

            Notification::send($notifiables, $notification);

            return;
        }

        try {
            Notification::sendNow($notifiables, $notification);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  class-string<BaseNotification>  $notificationClass
     * @param  array<int, string>  $channels
     */
    private function buildNotification(
        string $notificationClass,
        SurveySubmitted $event,
        array $channels,
    ): BaseNotification {
        if (is_a($notificationClass, SurveySubmittedNotification::class, true)) {
            return new $notificationClass(
                response: $event->response,
                surveyKey: $event->survey->key(),
                surveyTitle: $event->survey->getTitle(),
                context: $event->context,
                tracking: $event->tracking,
                channels: $channels,
            );
        }

        $reflection = new ReflectionClass($notificationClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $reflection->newInstance();
        }

        return $reflection->newInstance($event);
    }

    /**
     * @return class-string<BaseNotification>
     */
    private function resolveNotificationClass(Survey $survey): string
    {
        $configured = $survey->getNotificationClass()
            ?? config('simple-surveys.notifications.notification', SurveySubmittedNotification::class);

        if (! is_string($configured) || trim($configured) === '') {
            return SurveySubmittedNotification::class;
        }

        $class = trim($configured);

        if (! class_exists($class) || ! is_a($class, BaseNotification::class, true)) {
            throw new InvalidArgumentException(
                "Configured survey notification [{$class}] must extend ".BaseNotification::class.'.',
            );
        }

        return $class;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeChannels(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = [$raw];
        }

        if (! is_array($raw)) {
            return [];
        }

        /** @var array<int, string> $channels */
        $channels = [];

        foreach ($raw as $channel) {
            if (is_string($channel) && trim($channel) !== '') {
                $channels[] = trim($channel);
            }
        }

        return array_values(array_unique($channels));
    }

    private function isNonEmptyRoute(mixed $route): bool
    {
        if ($route === null) {
            return false;
        }

        if (is_string($route)) {
            return trim($route) !== '';
        }

        if (is_array($route)) {
            return $route !== [];
        }

        return true;
    }

    /**
     * @return array<int, object>
     */
    private function normalizeNotifiables(mixed $resolved): array
    {
        if ($resolved === null) {
            return [];
        }

        if ($resolved instanceof Collection) {
            $resolved = $resolved->all();
        }

        if (is_object($resolved)) {
            return [$resolved];
        }

        if (! is_array($resolved)) {
            return [];
        }

        /** @var array<int, object> $notifiables */
        $notifiables = [];

        foreach ($resolved as $item) {
            if (is_object($item)) {
                $notifiables[] = $item;
            }
        }

        return $notifiables;
    }
}
