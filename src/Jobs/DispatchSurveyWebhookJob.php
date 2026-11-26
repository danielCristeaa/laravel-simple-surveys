<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

final class DispatchSurveyWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /** @var array<int, int> */
    public array $backoff;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @param  array<int, int>  $backoff
     */
    public function __construct(
        public readonly string $url,
        public readonly string $eventName,
        public readonly array $payload,
        public readonly ?string $secret = null,
        public readonly string $signatureHeader = 'X-Simple-Surveys-Signature',
        public readonly array $headers = [],
        public readonly int $timeout = 5,
        int $tries = 3,
        array $backoff = [10, 60, 300],
    ) {
        $this->tries = max(1, $tries);
        $this->backoff = $backoff;
    }

    public static function signPayload(string $jsonBody, string $secret): string
    {
        return hash_hmac('sha256', $jsonBody, $secret);
    }

    public function handle(): void
    {
        $jsonBody = json_encode($this->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $headers = array_merge([
            'Accept' => 'application/json',
            'X-Simple-Surveys-Event' => $this->eventName,
        ], $this->headers);

        if ($this->secret !== null && $this->secret !== '') {
            $headers[$this->signatureHeader] = self::signPayload($jsonBody, $this->secret);
        }

        Http::timeout(max(1, $this->timeout))
            ->withHeaders($headers)
            ->withBody($jsonBody, 'application/json')
            ->post($this->url)
            ->throw();
    }
}
