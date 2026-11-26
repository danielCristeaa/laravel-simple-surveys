<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Events\SurveyDismissed;
use DanielCristea\SimpleSurveys\Events\SurveyOpened;
use DanielCristea\SimpleSurveys\Events\SurveySeen;
use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

it('resolves configured cookies and custom tracking context across all lifecycle events', function (): void {
    Event::fake([
        SurveySeen::class,
        SurveyOpened::class,
        SurveyDismissed::class,
        SurveySubmitted::class,
    ]);

    config()->set('simple-surveys.tracking.cookies', [
        'distinct_id' => 'ph_distinct_id',
        'click_id',
    ]);

    SimpleSurveys::resolveTrackingContext(static fn (Request $request, User $user, Survey $survey): array => [
        'plan' => $user->plan,
        'survey_tag' => $survey->key(),
        'empty_ignored' => '   ',
        'null_ignored' => null,
    ]);

    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'plan' => 'pro']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'plan' => 'free']);

    SimpleSurveys::register(
        Survey::make('lifecycle-tracking')
            ->repromptAfterDays(7)
            ->questions([
                Question::rating('score', 'Score')->scale(1, 5)->required(),
            ])
    );

    $cookies = [
        'ph_distinct_id' => 'ph_user_123',
        'click_id' => 'clk_abc',
    ];

    // 1. Seen
    $this->actingAs($alice)
        ->withCredentials()
        ->withCookies($cookies)
        ->postJson(route('simple-surveys.impression', 'lifecycle-tracking'), [
            'url' => '/dashboard',
        ])
        ->assertOk();

    Event::assertDispatched(SurveySeen::class, static fn (SurveySeen $event): bool => $event->context === ['url' => '/dashboard']
        && $event->tracking === [
            'distinct_id' => 'ph_user_123',
            'click_id' => 'clk_abc',
            'plan' => 'pro',
            'survey_tag' => 'lifecycle-tracking',
        ]);

    // 2. Opened
    $this->actingAs($alice)
        ->withCredentials()
        ->withCookies($cookies)
        ->postJson(route('simple-surveys.open', 'lifecycle-tracking'), [
            'url' => '/dashboard',
        ])
        ->assertOk();

    Event::assertDispatched(SurveyOpened::class, static fn (SurveyOpened $event): bool => $event->context === ['url' => '/dashboard']
        && $event->tracking['distinct_id'] === 'ph_user_123'
        && $event->tracking['plan'] === 'pro');

    // 3. Submitted with duration_ms
    $this->actingAs($alice)
        ->withCredentials()
        ->withCookies($cookies)
        ->postJson(route('simple-surveys.responses.store', 'lifecycle-tracking'), [
            'answers' => ['score' => 5],
            'url' => '/dashboard',
            'duration_ms' => 4250,
        ])
        ->assertCreated();

    Event::assertDispatched(SurveySubmitted::class, static fn (SurveySubmitted $event): bool => $event->context === [
        'url' => '/dashboard',
        'duration_ms' => 4250,
        'locale' => 'en',
    ] && $event->tracking['click_id'] === 'clk_abc');

    $response = SurveyResponse::query()->forSurvey('lifecycle-tracking')->forRespondent($alice)->firstOrFail();

    expect($response->metadata)->toBe([
        'url' => '/dashboard',
        'duration_ms' => 4250,
        'locale' => 'en',
        'tracking' => [
            'distinct_id' => 'ph_user_123',
            'click_id' => 'clk_abc',
            'plan' => 'pro',
            'survey_tag' => 'lifecycle-tracking',
        ],
    ]);

    // 4. Dismissed with source tracking and repeat dismiss_count across reprompt cooldown
    $this->actingAs($bob)
        ->withCredentials()
        ->withCookies($cookies)
        ->postJson(route('simple-surveys.dismiss', 'lifecycle-tracking'), [
            'source' => 'banner',
            'url' => '/pricing',
        ])
        ->assertOk();

    $dismissal = SurveyDismissal::query()->forSurvey('lifecycle-tracking')->forRespondent($bob)->firstOrFail();

    expect($dismissal->source)->toBe('banner')
        ->and($dismissal->dismiss_count)->toBe(1);

    Event::assertDispatched(SurveyDismissed::class, static fn (SurveyDismissed $event): bool => $event->context === [
        'url' => '/pricing',
        'source' => 'banner',
    ] && $event->tracking['plan'] === 'free');

    // Expire dismissal cooldown and dismiss again from the expanded card
    $dismissal->update(['dismissed_at' => now()->subDays(10)]);

    $this->actingAs($bob)
        ->withCredentials()
        ->withCookies($cookies)
        ->postJson(route('simple-surveys.dismiss', 'lifecycle-tracking'), [
            'source' => 'card',
            'url' => '/billing',
        ])
        ->assertOk();

    $dismissal->refresh();

    expect($dismissal->source)->toBe('card')
        ->and($dismissal->dismiss_count)->toBe(2);
});

it('sanitizes invalid dismissal sources and out-of-range duration values', function (): void {
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    SimpleSurveys::register(
        Survey::make('sanitize-check')
            ->questions([Question::text('note', 'Note')])
    );

    $this->actingAs($alice)
        ->postJson(route('simple-surveys.dismiss', 'sanitize-check'), [
            'source' => 'unknown-source',
        ])
        ->assertOk();

    $dismissal = SurveyDismissal::query()->forSurvey('sanitize-check')->forRespondent($alice)->firstOrFail();
    expect($dismissal->source)->toBeNull();

    $this->actingAs($bob)
        ->postJson(route('simple-surveys.responses.store', 'sanitize-check'), [
            'answers' => ['note' => 'Hi'],
            'duration_ms' => -50,
        ])
        ->assertCreated();

    $response = SurveyResponse::query()->forSurvey('sanitize-check')->forRespondent($bob)->firstOrFail();
    expect($response->metadata)->toBe(['locale' => 'en']);
});

it('appends duration and tracking context columns dynamically in csv exports', function (): void {
    Gate::define('viewSimpleSurveys', static fn (User $user): bool => $user->is_admin);

    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'plan' => 'pro']);

    SimpleSurveys::resolveTrackingContext(static fn (Request $request, User $user): array => [
        'plan' => $user->plan,
        'distinct_id' => 'ph_999',
    ]);

    SimpleSurveys::register(
        Survey::make('csv-tracking')
            ->title('CSV Tracking')
            ->questions([
                Question::rating('score', 'Score')->required(),
            ])
    );

    $this->actingAs($alice)
        ->postJson(route('simple-surveys.responses.store', 'csv-tracking'), [
            'answers' => ['score' => 4],
            'url' => '/app/home',
            'duration_ms' => 3100,
        ])
        ->assertCreated();

    $csv = $this->actingAs($admin)
        ->get(route('simple-surveys.admin.export', 'csv-tracking'))
        ->assertOk()
        ->streamedContent();

    expect($csv)
        ->toContain('"Response ID","Respondent Type","Respondent ID","Submitted At","Page URL",Score,"Duration (ms)",Locale,distinct_id,plan')
        ->toContain('/app/home,4,3100,en,ph_999,pro');
});

it('renders browser CustomEvent dispatchers and dismissal source hooks in the widget', function (): void {
    $user = User::create(['name' => 'Sam', 'email' => 'sam@example.com']);

    SimpleSurveys::register(
        Survey::make('events-widget')
            ->title('Events Widget')
            ->questions([Question::text('feedback', 'Feedback')])
    );

    $this->actingAs($user);

    $html = Blade::render('<x-simple-surveys::widget />');

    expect($html)
        ->toContain('data-lss-dismiss-source="banner"')
        ->toContain('data-lss-dismiss-source="card"')
        ->toContain("new CustomEvent('simple-surveys:' + name")
        ->toContain("emitSurveyEvent('seen'")
        ->toContain("emitSurveyEvent('opened')")
        ->toContain("emitSurveyEvent('minimized'")
        ->toContain("emitSurveyEvent('dismissed'")
        ->toContain("emitSurveyEvent('submitted'")
        ->toContain('submitPayload.duration_ms');
});

it('derives duration_ms server-side from first_opened_at and preserves point-in-time cohort attributes', function (): void {
    Gate::define('viewSimpleSurveys', static fn (User $user): bool => $user->is_admin);

    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'plan' => 'pro']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'plan' => 'enterprise']);

    SimpleSurveys::resolveTrackingContext(static fn (Request $request, User $user): array => [
        'plan' => $user->plan,
    ]);

    SimpleSurveys::register(
        Survey::make('server-duration')
            ->title('Server Duration')
            ->questions([
                Question::rating('score', 'Score')->required(),
            ])
    );

    // Alice opens, waits 5 seconds, and submits without client-side duration_ms
    $this->actingAs($alice)->postJson(route('simple-surveys.open', 'server-duration'))->assertOk();
    $this->travel(5)->seconds();

    $this->actingAs($alice)
        ->postJson(route('simple-surveys.responses.store', 'server-duration'), [
            'answers' => ['score' => 5],
        ])
        ->assertCreated();

    // User's plan changes later, but snapshot stays "pro"
    $alice->update(['plan' => 'free']);

    $aliceResponse = SurveyResponse::query()->forSurvey('server-duration')->forRespondent($alice)->firstOrFail();

    expect($aliceResponse->metadata['duration_ms'] ?? null)->toBeGreaterThanOrEqual(4500)
        ->and($aliceResponse->metadata['duration_ms'] ?? null)->toBeLessThanOrEqual(6000)
        ->and($aliceResponse->metadata['tracking']['plan'] ?? null)->toBe('pro');

    // Bob opens and submits 2 days later (> 24h cap -> duration_ms omitted)
    $this->actingAs($bob)->postJson(route('simple-surveys.open', 'server-duration'))->assertOk();
    $this->travel(2)->days();

    $this->actingAs($bob)
        ->postJson(route('simple-surveys.responses.store', 'server-duration'), [
            'answers' => ['score' => 4],
        ])
        ->assertCreated();

    $bobResponse = SurveyResponse::query()->forSurvey('server-duration')->forRespondent($bob)->firstOrFail();

    expect($bobResponse->metadata)->toBe([
        'locale' => 'en',
        'tracking' => [
            'plan' => 'enterprise',
        ],
    ]);

    $csv = $this->actingAs($admin)
        ->get(route('simple-surveys.admin.export', 'server-duration'))
        ->assertOk()
        ->streamedContent();

    expect($csv)
        ->toContain('"Duration (ms)",Locale,plan')
        ->toContain(',en,pro')
        ->toContain(',en,enterprise');
});
