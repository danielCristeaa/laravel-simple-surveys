<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Events\SurveySubmitted;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Notifications\Channels\SlackWebhookChannel;
use DanielCristea\SimpleSurveys\Notifications\SurveySubmittedNotification;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

it('does not dispatch notifications when disabled and survey has no notification config', function (): void {
    Notification::fake();

    $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

    SimpleSurveys::register(
        Survey::make('quiet-survey')
            ->questions([Question::text('note', 'Note')])
    );

    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'quiet-survey'), [
        'answers' => ['note' => 'hello'],
    ])->assertCreated();

    Notification::assertNothingSent();
});

it('dispatches global queued notifications on mail and slack and serializes cleanly even with closure targeting', function (): void {
    Notification::fake();
    Notification::serializeAndRestore();

    config()->set('simple-surveys.notifications', [
        'enabled' => true,
        'notification' => SurveySubmittedNotification::class,
        'channels' => ['mail', 'slack'],
        'routes' => [
            'mail' => 'product@example.com',
            'slack' => 'https://hooks.slack.com/services/T000/B000/GLOBAL',
        ],
        'queued' => true,
        'queue' => 'notifications',
        'slack_timeout' => 5,
    ]);

    $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'plan' => 'pro']);

    SimpleSurveys::register(
        Survey::make('onboarding-nps')
            ->title('Onboarding NPS')
            ->target(static fn (Builder $query): Builder => $query->where('plan', 'pro'))
            ->targetUser(static fn (User $u): bool => $u->plan === 'pro')
            ->questions([
                Question::rating('score', 'Overall Score')->scale(1, 5)->required(),
                Question::boolean('recommend', 'Would you recommend us?')->required(),
                Question::checkbox('features', 'Favorite Features')->options(['api' => 'API', 'ui' => 'UI']),
            ])
    );

    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'onboarding-nps'), [
        'answers' => [
            'score' => 5,
            'recommend' => true,
            'features' => ['api', 'ui'],
        ],
        'url' => '/dashboard',
    ])->assertCreated();

    Notification::assertSentOnDemand(
        SurveySubmittedNotification::class,
        static function (SurveySubmittedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $notification->surveyKey === 'onboarding-nps'
                && $notification->surveyTitle === 'Onboarding NPS'
                && $notification->queue === 'notifications'
                && $channels === ['mail', SlackWebhookChannel::class]
                && $notifiable->routes === [
                    'mail' => 'product@example.com',
                    'slack' => 'https://hooks.slack.com/services/T000/B000/GLOBAL',
                ]
                && $notification->context === ['url' => '/dashboard'];
        },
    );
});

it('pushes notifications onto the configured queue when queued is true', function (): void {
    Queue::fake();

    config()->set('simple-surveys.notifications', [
        'enabled' => true,
        'notification' => SurveySubmittedNotification::class,
        'channels' => ['mail'],
        'routes' => [
            'mail' => 'ops@example.com',
        ],
        'queued' => true,
        'queue' => 'survey-alerts',
    ]);

    $user = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    SimpleSurveys::register(
        Survey::make('queued-survey')
            ->title('Queued Survey')
            ->questions([Question::text('note', 'Note')])
    );

    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'queued-survey'), [
        'answers' => ['note' => 'fast submission'],
    ])->assertCreated();

    Queue::assertPushedOn('survey-alerts', SendQueuedNotifications::class, static function (SendQueuedNotifications $job): bool {
        return $job->notification instanceof SurveySubmittedNotification
            && $job->notification->surveyKey === 'queued-survey';
    });
});

it('delivers per-survey slack webhook notifications synchronously with formatted payload when queued is false', function (): void {
    Http::fake([
        'https://hooks.slack.com/*' => Http::response('ok', 200),
    ]);

    config()->set('simple-surveys.notifications.enabled', false);
    config()->set('simple-surveys.notifications.queued', false);

    $user = User::create(['name' => 'Clara', 'email' => 'clara@example.com']);

    SimpleSurveys::register(
        Survey::make('checkout-feedback')
            ->title('Checkout Feedback')
            ->notifySlack('https://hooks.slack.com/services/T111/B222/PER_SURVEY')
            ->questions([
                Question::rating('score', 'Checkout Score')->scale(1, 5)->required(),
                Question::boolean('easy', 'Was checkout easy?')->required(),
                Question::text('comment', 'Comments'),
            ])
    );

    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'checkout-feedback'), [
        'answers' => [
            'score' => 4,
            'easy' => true,
            'comment' => 'Smooth experience',
        ],
        'url' => '/checkout/thanks',
    ])->assertCreated();

    Http::assertSentCount(1);
    Http::assertSent(static function (HttpClientRequest $request): bool {
        $data = $request->data();
        $blocksJson = json_encode($data['blocks'] ?? [], JSON_UNESCAPED_SLASHES);

        return $request->url() === 'https://hooks.slack.com/services/T111/B222/PER_SURVEY'
            && ($data['text'] ?? null) === 'New survey response: Checkout Feedback'
            && is_string($blocksJson)
            && str_contains($blocksJson, 'Clara (clara@example.com)')
            && str_contains($blocksJson, 'Checkout Score')
            && str_contains($blocksJson, 'Was checkout easy?')
            && str_contains($blocksJson, 'Yes')
            && str_contains($blocksJson, 'Smooth experience')
            && str_contains($blocksJson, '/checkout/thanks');
    });
});

it('renders mail and array notification representations accurately', function (): void {
    $user = User::create(['name' => 'Grace', 'email' => 'grace@example.com']);

    SimpleSurveys::register(
        Survey::make('mail-preview')
            ->title('Mail Preview Survey')
            ->questions([
                Question::rating('score', 'Score')->scale(1, 5)->required(),
                Question::boolean('recommend', 'Recommend')->required(),
                Question::checkbox('areas', 'Areas')->options(['docs' => 'Docs', 'speed' => 'Speed']),
            ])
    );

    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'mail-preview'), [
        'answers' => [
            'score' => 5,
            'recommend' => false,
            'areas' => ['docs', 'speed'],
        ],
        'url' => '/settings',
    ])->assertCreated();

    $response = SurveyResponse::query()->forSurvey('mail-preview')->firstOrFail();

    $notification = new SurveySubmittedNotification(
        response: $response,
        surveyKey: 'mail-preview',
        surveyTitle: 'Mail Preview Survey',
        context: ['url' => '/settings'],
        tracking: ['plan' => 'pro'],
        channels: ['mail', 'database'],
    );

    $notifiable = new AnonymousNotifiable;
    $mail = $notification->toMail($notifiable);

    expect($mail->subject)->toBe('New survey response: Mail Preview Survey')
        ->and($mail->introLines)->toContain('Respondent: Grace (grace@example.com)')
        ->and($mail->introLines)->toContain('Score: 5')
        ->and($mail->introLines)->toContain('Recommend: No')
        ->and($mail->introLines)->toContain('Areas: docs, speed')
        ->and($mail->introLines)->toContain('Submitted from: /settings')
        ->and($mail->actionText)->toBe('View Survey Results')
        ->and($mail->actionUrl)->toBe(route('simple-surveys.admin.show', 'mail-preview'));

    $array = $notification->toArray($notifiable);

    expect($array['event'])->toBe('survey.submitted')
        ->and($array['survey_key'])->toBe('mail-preview')
        ->and($array['answers'])->toBe([
            'score' => 5,
            'recommend' => false,
            'areas' => ['docs', 'speed'],
        ])
        ->and($array['tracking'])->toBe(['plan' => 'pro']);

    config()->set('simple-surveys.admin.enabled', false);
    expect($notification->toMail($notifiable)->actionUrl)->toBeNull();
});

it('supports per-survey notifyMail, notifyUsers, and global resolveNotifiables', function (): void {
    Notification::fake();

    $respondent = User::create(['name' => 'Customer', 'email' => 'customer@example.com']);
    $adminA = User::create(['name' => 'Admin A', 'email' => 'admin-a@example.com', 'is_admin' => true]);
    $adminB = User::create(['name' => 'Admin B', 'email' => 'admin-b@example.com', 'is_admin' => true]);

    config()->set('simple-surveys.notifications.enabled', true);
    config()->set('simple-surveys.notifications.channels', ['mail']);
    config()->set('simple-surveys.notifications.routes', []);

    SimpleSurveys::resolveNotifiables(static fn (SurveySubmitted $event) => [$adminA]);

    SimpleSurveys::register(
        Survey::make('multi-target')
            ->title('Multi Target')
            ->notifyMail(['pm@example.com', 'design@example.com'])
            ->notifyUsers(static fn (SurveySubmitted $event) => [$adminB], channels: ['mail', 'database'])
            ->questions([Question::text('note', 'Note')])
    );

    $this->actingAs($respondent)->postJson(route('simple-surveys.responses.store', 'multi-target'), [
        'answers' => ['note' => 'great'],
    ])->assertCreated();

    Notification::assertSentTo($adminA, SurveySubmittedNotification::class, static fn (SurveySubmittedNotification $n, array $channels): bool => $channels === ['mail']);
    Notification::assertSentTo($adminB, SurveySubmittedNotification::class, static fn (SurveySubmittedNotification $n, array $channels): bool => $channels === ['mail', 'database']);
    Notification::assertSentOnDemand(
        SurveySubmittedNotification::class,
        static fn (SurveySubmittedNotification $n, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail']
            && $notifiable->routes === ['mail' => ['pm@example.com', 'design@example.com']],
    );
});

it('supports custom notification class overrides globally and per survey', function (): void {
    Notification::fake();

    $customClass = new class(new SurveySubmitted(Survey::make('stub'), new SurveyResponse, new User)) extends BaseNotification
    {
        public function __construct(public readonly SurveySubmitted $event) {}

        /**
         * @return array<int, string>
         */
        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    };

    $customClassName = $customClass::class;

    $user = User::create(['name' => 'Hank', 'email' => 'hank@example.com']);

    SimpleSurveys::register(
        Survey::make('custom-notif')
            ->notifyMail('custom@example.com')
            ->notification($customClassName)
            ->questions([Question::text('note', 'Note')])
    );

    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'custom-notif'), [
        'answers' => ['note' => 'custom'],
    ])->assertCreated();

    Notification::assertSentOnDemand($customClassName);
});

it('suppresses all notifications when withoutNotifications is set on a survey', function (): void {
    Notification::fake();

    config()->set('simple-surveys.notifications.enabled', true);
    config()->set('simple-surveys.notifications.channels', ['mail']);
    config()->set('simple-surveys.notifications.routes', ['mail' => 'team@example.com']);

    $user = User::create(['name' => 'Ivy', 'email' => 'ivy@example.com']);

    SimpleSurveys::register(
        Survey::make('muted-survey')
            ->withoutNotifications()
            ->questions([Question::text('note', 'Note')])
    );

    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'muted-survey'), [
        'answers' => ['note' => 'muted'],
    ])->assertCreated();

    Notification::assertNothingSent();
});

it('does not break survey submission when a synchronous notification channel fails', function (): void {
    Http::fake([
        'https://hooks.slack.com/*' => Http::response(['error' => 'invalid_token'], 500),
    ]);

    config()->set('simple-surveys.notifications.enabled', false);
    config()->set('simple-surveys.notifications.queued', false);

    $user = User::create(['name' => 'Jules', 'email' => 'jules@example.com']);

    SimpleSurveys::register(
        Survey::make('resilient-notif')
            ->notifySlack('https://hooks.slack.com/services/T000/B000/FAIL')
            ->questions([Question::text('note', 'Note')])
    );

    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'resilient-notif'), [
        'answers' => ['note' => 'still saved'],
    ])->assertCreated();

    expect(SurveyResponse::query()->forSurvey('resilient-notif')->count())->toBe(1);
    Http::assertSentCount(1);
});

it('validates notification arguments on Survey', function (): void {
    expect(static fn () => Survey::make('bad-1')->notify('   ', 'a@example.com'))
        ->toThrow(InvalidArgumentException::class, 'Notification channel cannot be empty.')
        ->and(static fn () => Survey::make('bad-2')->notify('mail', '   '))
        ->toThrow(InvalidArgumentException::class, 'Notification route cannot be empty.')
        ->and(static fn () => Survey::make('bad-3')->notifyMail([]))
        ->toThrow(InvalidArgumentException::class, 'Notification route cannot be empty.')
        ->and(static fn () => Survey::make('bad-4')->notification(stdClass::class)) // @phpstan-ignore argument.type
        ->toThrow(InvalidArgumentException::class);
});
