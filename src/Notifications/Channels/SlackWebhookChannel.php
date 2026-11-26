<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

final class SlackWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return;
        }

        $url = $notifiable->routeNotificationFor('slack', $notification);

        if (! is_string($url) || trim($url) === '') {
            return;
        }

        if (! method_exists($notification, 'toSlackWebhook')) {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = $notification->toSlackWebhook($notifiable);

        $timeoutRaw = config('simple-surveys.notifications.slack_timeout', 5);
        $timeout = is_numeric($timeoutRaw) ? max(1, (int) $timeoutRaw) : 5;

        Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post(trim($url), $payload)
            ->throw();
    }
}
