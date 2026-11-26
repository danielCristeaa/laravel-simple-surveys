<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use DanielCristea\SimpleSurveys\Events\SurveyDismissed;
use DanielCristea\SimpleSurveys\Events\SurveyOpened;
use DanielCristea\SimpleSurveys\Events\SurveySeen;
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Jobs\DispatchSurveyWebhookJob;
use DanielCristea\SimpleSurveys\Survey;
use Throwable;

final class SurveyWebhookDispatcher
{
    public function __construct(
        private readonly SurveyWebhookPayloadBuilder $payloadBuilder,
    ) {}

    public function handle(SurveySeen|SurveyOpened|SurveyDismissed|SurveySubmitted $event): void
    {
        if ($event->survey->hasWebhooksDisabled()) {
            return;
        }

        $shortEvent = match (true) {
            $event instanceof SurveySeen => 'seen',
            $event instanceof SurveyOpened => 'opened',
            $event instanceof SurveyDismissed => 'dismissed',
            $event instanceof SurveySubmitted => 'submitted',
        };

        $targets = $this->resolveTargets($event->survey, $shortEvent);

        if ($targets === []) {
            return;
        }

        $payload = match (true) {
            $event instanceof SurveySeen => $this->payloadBuilder->forSeen($event),
            $event instanceof SurveyOpened => $this->payloadBuilder->forOpened($event),
            $event instanceof SurveyDismissed => $this->payloadBuilder->forDismissed($event),
            $event instanceof SurveySubmitted => $this->payloadBuilder->forSubmitted($event),
        };

        $eventName = 'survey.'.$shortEvent;
        $signatureHeader = $this->resolveSignatureHeader();
        $timeout = $this->resolveIntConfig('simple-surveys.webhooks.timeout', 5);
        $tries = $this->resolveIntConfig('simple-surveys.webhooks.tries', 3);
        $backoff = $this->resolveBackoffConfig();
        $queued = (bool) config('simple-surveys.webhooks.queued', true);
        $queue = config('simple-surveys.webhooks.queue');
        $resolvedQueue = is_string($queue) && trim($queue) !== '' ? trim($queue) : null;

        foreach ($targets as $target) {
            if ($queued) {
                $pending = DispatchSurveyWebhookJob::dispatch(
                    url: $target['url'],
                    eventName: $eventName,
                    payload: $payload,
                    secret: $target['secret'],
                    signatureHeader: $signatureHeader,
                    headers: $target['headers'],
                    timeout: $timeout,
                    tries: $tries,
                    backoff: $backoff,
                );

                if ($resolvedQueue !== null) {
                    $pending->onQueue($resolvedQueue);
                }
            } else {
                try {
                    DispatchSurveyWebhookJob::dispatchSync(
                        url: $target['url'],
                        eventName: $eventName,
                        payload: $payload,
                        secret: $target['secret'],
                        signatureHeader: $signatureHeader,
                        headers: $target['headers'],
                        timeout: $timeout,
                        tries: $tries,
                        backoff: $backoff,
                    );
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }
    }

    /**
     * @return array<int, array{url: string, secret: string|null, headers: array<string, string>}>
     */
    private function resolveTargets(Survey $survey, string $shortEvent): array
    {
        /** @var array<int, array{url: string, secret: string|null, headers: array<string, string>}> $targets */
        $targets = [];

        $globalSecretRaw = config('simple-surveys.webhooks.secret');
        $globalSecret = is_string($globalSecretRaw) && trim($globalSecretRaw) !== '' ? $globalSecretRaw : null;
        $globalHeaders = $this->normalizeHeaders(config('simple-surveys.webhooks.headers', []));

        if ((bool) config('simple-surveys.webhooks.enabled', false)) {
            $globalUrlRaw = config('simple-surveys.webhooks.url');
            $globalUrl = is_string($globalUrlRaw) && trim($globalUrlRaw) !== '' ? trim($globalUrlRaw) : null;
            $configuredEvents = config('simple-surveys.webhooks.events', ['seen', 'opened', 'dismissed', 'submitted']);

            if (is_array($configuredEvents)) {
                foreach ($configuredEvents as $key => $value) {
                    if (is_int($key) && is_string($value)) {
                        if (strtolower(trim($value)) === $shortEvent && $globalUrl !== null) {
                            $targets[] = [
                                'url' => $globalUrl,
                                'secret' => $globalSecret,
                                'headers' => $globalHeaders,
                            ];
                        }
                    } elseif (is_string($key) && strtolower(trim($key)) === $shortEvent) {
                        if (is_string($value) && trim($value) !== '') {
                            $targets[] = [
                                'url' => trim($value),
                                'secret' => $globalSecret,
                                'headers' => $globalHeaders,
                            ];
                        } elseif ($value === true && $globalUrl !== null) {
                            $targets[] = [
                                'url' => $globalUrl,
                                'secret' => $globalSecret,
                                'headers' => $globalHeaders,
                            ];
                        }
                    }
                }
            }
        }

        foreach ($survey->getWebhooks() as $webhook) {
            if (! in_array($shortEvent, $webhook['events'], true)) {
                continue;
            }

            $targets[] = [
                'url' => $webhook['url'],
                'secret' => $webhook['secret'] ?? $globalSecret,
                'headers' => array_merge($globalHeaders, $this->normalizeHeaders($webhook['headers'])),
            ];
        }

        return $targets;
    }

    private function resolveSignatureHeader(): string
    {
        $configured = config('simple-surveys.webhooks.signature_header', 'X-Simple-Surveys-Signature');

        return is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : 'X-Simple-Surveys-Signature';
    }

    private function resolveIntConfig(string $key, int $default): int
    {
        $raw = config($key, $default);

        return is_numeric($raw) ? max(1, (int) $raw) : $default;
    }

    /**
     * @return array<int, int>
     */
    private function resolveBackoffConfig(): array
    {
        $raw = config('simple-surveys.webhooks.backoff', [10, 60, 300]);

        if (! is_array($raw)) {
            return [10, 60, 300];
        }

        /** @var array<int, int> $backoff */
        $backoff = [];

        foreach ($raw as $item) {
            if (is_numeric($item) && (int) $item >= 0) {
                $backoff[] = (int) $item;
            }
        }

        return $backoff !== [] ? $backoff : [10, 60, 300];
    }

    /**
     * @return array<string, string>
     */
    private function normalizeHeaders(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        /** @var array<string, string> $headers */
        $headers = [];

        foreach ($raw as $name => $value) {
            if (is_string($name) && trim($name) !== '' && is_scalar($value)) {
                $headers[trim($name)] = (string) $value;
            }
        }

        return $headers;
    }
}
