<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Jobs\DispatchSurveyWebhookJob;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('does not dispatch webhooks when disabled and survey has no webhooks', function (): void {
    Queue::fake();

    $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

    SimpleSurveys::register(
        Survey::make('quiet-survey')
            ->questions([Question::text('note', 'Note')])
    );

    $this->actingAs($user)->postJson(route('simple-surveys.impression', 'quiet-survey'))->assertOk();
    $this->actingAs($user)->postJson(route('simple-surveys.open', 'quiet-survey'))->assertOk();
    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'quiet-survey'), [
        'answers' => ['note' => 'hello'],
    ])->assertCreated();

    Queue::assertNotPushed(DispatchSurveyWebhookJob::class);
});

it('dispatches queued global webhooks across all lifecycle events with per-event url overrides', function (): void {
    Queue::fake();

    config()->set('simple-surveys.webhooks', [
        'enabled' => true,
        'url' => 'https://data.example.test/hooks/default',
        'events' => [
            'seen',
            'opened',
            'dismissed',
            'submitted' => 'https://data.example.test/hooks/submissions',
        ],
        'secret' => 'whsec_global',
        'signature_header' => 'X-Simple-Surveys-Signature',
        'headers' => ['Authorization' => 'Bearer global-token'],
        'queued' => true,
        'queue' => 'webhooks',
        'timeout' => 8,
        'tries' => 4,
        'backoff' => [5, 30],
    ]);

    SimpleSurveys::resolveTrackingContext(static fn (Request $request, User $user): array => [
        'distinct_id' => 'ph_42',
        'plan' => $user->plan,
    ]);

    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'plan' => 'pro']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'plan' => 'free']);

    SimpleSurveys::register(
        Survey::make('data-funnel')
            ->title('Data Funnel')
            ->questions([
                Question::rating('score', 'Score')->scale(1, 5)->required(),
            ])
    );

    $this->actingAs($alice)->postJson(route('simple-surveys.impression', 'data-funnel'), ['url' => '/home'])->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.open', 'data-funnel'), ['url' => '/home'])->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.responses.store', 'data-funnel'), [
        'answers' => ['score' => 5],
        'url' => '/home',
        'duration_ms' => 2100,
    ])->assertCreated();
    $this->actingAs($bob)->postJson(route('simple-surveys.dismiss', 'data-funnel'), [
        'source' => 'banner',
        'url' => '/pricing',
    ])->assertOk();

    Queue::assertPushedOn('webhooks', DispatchSurveyWebhookJob::class, static fn (DispatchSurveyWebhookJob $job): bool => $job->eventName === 'survey.seen'
        && $job->url === 'https://data.example.test/hooks/default'
        && $job->payload['tracking'] === ['distinct_id' => 'ph_42', 'plan' => 'pro']
        && $job->payload['context'] === ['url' => '/home']);

    Queue::assertPushedOn('webhooks', DispatchSurveyWebhookJob::class, static fn (DispatchSurveyWebhookJob $job): bool => $job->eventName === 'survey.opened'
        && $job->url === 'https://data.example.test/hooks/default'
        && $job->payload['data']['open_count'] === 1);

    Queue::assertPushedOn('webhooks', DispatchSurveyWebhookJob::class, static fn (DispatchSurveyWebhookJob $job): bool => $job->eventName === 'survey.submitted'
        && $job->url === 'https://data.example.test/hooks/submissions'
        && $job->payload['data']['answers'] === ['score' => 5]
        && $job->payload['context'] === ['url' => '/home', 'duration_ms' => 2100]
        && $job->tries === 4
        && $job->backoff === [5, 30]);

    Queue::assertPushedOn('webhooks', DispatchSurveyWebhookJob::class, static fn (DispatchSurveyWebhookJob $job): bool => $job->eventName === 'survey.dismissed'
        && $job->url === 'https://data.example.test/hooks/default'
        && $job->payload['data']['source'] === 'banner'
        && $job->payload['data']['dismiss_count'] === 1);
});

it('delivers signed per-survey webhooks synchronously when queued is false', function (): void {
    Http::fake([
        'https://collector.example.test/*' => Http::response(['ok' => true], 200),
    ]);

    config()->set('simple-surveys.webhooks.enabled', false);
    config()->set('simple-surveys.webhooks.queued', false);

    SimpleSurveys::resolveTrackingContext(static fn (Request $request, User $user): array => [
        'click_id' => 'clk_777',
    ]);

    $user = User::create(['name' => 'Clara', 'email' => 'clara@example.com']);

    SimpleSurveys::register(
        Survey::make('nps-hook')
            ->title('NPS Hook')
            ->webhook(
                url: 'https://collector.example.test/nps',
                events: ['submitted'],
                secret: 'whsec_per_survey',
                headers: ['X-Custom-Team' => 'data-eng'],
            )
            ->questions([
                Question::rating('nps', 'NPS')->scale(0, 10)->required(),
            ])
    );

    // Seen & opened are NOT subscribed on this survey webhook -> no HTTP calls
    $this->actingAs($user)->postJson(route('simple-surveys.impression', 'nps-hook'))->assertOk();
    $this->actingAs($user)->postJson(route('simple-surveys.open', 'nps-hook'))->assertOk();

    Http::assertNothingSent();

    // Submitted IS subscribed -> sends signed HTTP POST
    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'nps-hook'), [
        'answers' => ['nps' => 9],
        'url' => '/checkout',
        'duration_ms' => 1500,
    ])->assertCreated();

    Http::assertSentCount(1);
    Http::assertSent(static function (HttpClientRequest $request): bool {
        $expectedSignature = DispatchSurveyWebhookJob::signPayload($request->body(), 'whsec_per_survey');
        $decoded = json_decode($request->body(), true);

        return $request->url() === 'https://collector.example.test/nps'
            && $request->hasHeader('X-Simple-Surveys-Event', 'survey.submitted')
            && $request->hasHeader('X-Simple-Surveys-Signature', $expectedSignature)
            && $request->hasHeader('X-Custom-Team', 'data-eng')
            && is_array($decoded)
            && $decoded['event'] === 'survey.submitted'
            && $decoded['survey']['key'] === 'nps-hook'
            && $decoded['tracking'] === ['click_id' => 'clk_777']
            && $decoded['context'] === ['url' => '/checkout', 'duration_ms' => 1500]
            && $decoded['data']['answers'] === ['nps' => 9];
    });
});

it('throws on non-2xx webhook responses so queued jobs retry and validates empty webhook urls', function (): void {
    expect(static fn () => Survey::make('bad-hook')->webhook('   '))
        ->toThrow(InvalidArgumentException::class, 'Webhook URL cannot be empty.');

    Http::fake([
        'https://failing.example.test/*' => Http::response(['error' => 'down'], 500),
    ]);

    $job = new DispatchSurveyWebhookJob(
        url: 'https://failing.example.test/hook',
        eventName: 'survey.submitted',
        payload: ['event' => 'survey.submitted'],
    );

    expect(static fn () => $job->handle())
        ->toThrow(RequestException::class);
});

it('suppresses webhooks when withoutWebhooks is set on a survey', function (): void {
    Queue::fake();

    config()->set('simple-surveys.webhooks.enabled', true);
    config()->set('simple-surveys.webhooks.url', 'https://data.example.test/hooks/default');

    $user = User::create(['name' => 'Dana', 'email' => 'dana@example.com']);

    SimpleSurveys::register(
        Survey::make('silent-survey')
            ->withoutWebhooks()
            ->questions([Question::text('note', 'Note')])
    );

    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'silent-survey'), [
        'answers' => ['note' => 'quiet'],
    ])->assertCreated();

    Queue::assertNotPushed(DispatchSurveyWebhookJob::class);
});

it('does not break survey submissions when a synchronous webhook endpoint fails', function (): void {
    Http::fake([
        'https://down.example.test/*' => Http::response(['error' => 'unavailable'], 500),
    ]);

    config()->set('simple-surveys.webhooks.enabled', true);
    config()->set('simple-surveys.webhooks.url', 'https://down.example.test/hook');
    config()->set('simple-surveys.webhooks.events', ['submitted']);
    config()->set('simple-surveys.webhooks.queued', false);

    $user = User::create(['name' => 'Evan', 'email' => 'evan@example.com']);

    SimpleSurveys::register(
        Survey::make('resilient-survey')
            ->questions([Question::text('note', 'Note')])
    );

    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'resilient-survey'), [
        'answers' => ['note' => 'still saved'],
    ])->assertCreated();

    Http::assertSentCount(1);
});
