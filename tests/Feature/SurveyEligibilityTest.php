<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\PageContext;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\Order;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

it('returns nothing and runs zero queries for guests or when no surveys are active', function (): void {
    SimpleSurveys::register(Survey::make('active-survey'));

    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(SimpleSurveys::forUser(null))->toBeNull()
        ->and(SimpleSurveys::eligibleFor(null))->toBeEmpty()
        ->and(SimpleSurveys::isEligible('active-survey', null))->toBeFalse()
        ->and(DB::getQueryLog())->toBeEmpty();

    SimpleSurveys::clear();
    SimpleSurveys::register(Survey::make('draft-survey')->draft());

    $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

    DB::flushQueryLog();

    expect(SimpleSurveys::forUser($user))->toBeNull()
        ->and(SimpleSurveys::eligibleFor($user))->toBeEmpty()
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('excludes surveys already completed or dismissed by the user', function (): void {
    $user = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $otherUser = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    SimpleSurveys::register(Survey::make('first')->priority(10));
    SimpleSurveys::register(Survey::make('second')->priority(5));
    SimpleSurveys::register(Survey::make('third')->priority(1));

    SimpleSurveys::sync();

    $firstRecord = SurveyRecord::query()->byKey('first')->firstOrFail();
    $secondRecord = SurveyRecord::query()->byKey('second')->firstOrFail();
    $thirdRecord = SurveyRecord::query()->byKey('third')->firstOrFail();

    SurveyResponse::query()->create([
        'survey_id' => $firstRecord->id,
        'respondent_type' => $user->getMorphClass(),
        'respondent_id' => $user->id,
    ]);

    SurveyDismissal::query()->create([
        'survey_id' => $secondRecord->id,
        'respondent_type' => $user->getMorphClass(),
        'respondent_id' => $user->id,
        'dismissed_at' => now(),
    ]);

    SurveyResponse::query()->create([
        'survey_id' => $thirdRecord->id,
        'respondent_type' => $otherUser->getMorphClass(),
        'respondent_id' => $otherUser->id,
    ]);

    expect(SimpleSurveys::forUser($user)?->key())->toBe('third')
        ->and(SimpleSurveys::eligibleFor($user)->map->key()->all())->toBe(['third'])
        ->and(SimpleSurveys::isEligible('first', $user))->toBeFalse()
        ->and(SimpleSurveys::isEligible('second', $user))->toBeFalse()
        ->and(SimpleSurveys::isEligible('third', $user))->toBeTrue()
        ->and(SimpleSurveys::forUser($otherUser)?->key())->toBe('first');
});

it('targets users using eloquent where and whereHas queries', function (): void {
    $freeUser = User::create(['name' => 'Free', 'email' => 'free@example.com', 'plan' => 'free']);
    $proWithoutOrders = User::create(['name' => 'ProNoOrders', 'email' => 'pro1@example.com', 'plan' => 'pro']);
    $proWithPaidOrder = User::create(['name' => 'ProPaid', 'email' => 'pro2@example.com', 'plan' => 'pro']);

    Order::create([
        'user_id' => $proWithPaidOrder->id,
        'status' => 'paid',
        'total_cents' => 4900,
    ]);

    SimpleSurveys::register(
        Survey::make('vip-feedback')->target(function (Builder $query): void {
            $query->where('plan', 'pro')
                ->whereHas('orders', fn (Builder $orders) => $orders->where('status', 'paid'));
        })
    );

    expect(SimpleSurveys::forUser($freeUser))->toBeNull()
        ->and(SimpleSurveys::forUser($proWithoutOrders))->toBeNull()
        ->and(SimpleSurveys::forUser($proWithPaidOrder)?->key())->toBe('vip-feedback');
});

it('safely scopes orWhere conditions inside target callbacks to the current user', function (): void {
    $freeUser = User::create(['name' => 'Free', 'email' => 'free@example.com', 'plan' => 'free']);
    User::create(['name' => 'Enterprise', 'email' => 'ent@example.com', 'plan' => 'enterprise']);

    SimpleSurveys::register(
        Survey::make('paid-tiers')->target(function (Builder $query): void {
            $query->where('plan', 'pro')->orWhere('plan', 'enterprise');
        })
    );

    expect(SimpleSurveys::forUser($freeUser))->toBeNull()
        ->and(SimpleSurveys::isEligible('paid-tiers', $freeUser))->toBeFalse();
});

it('supports direct targetUser predicates and combines them with eloquent targets', function (): void {
    $regularPro = User::create(['name' => 'Pro', 'email' => 'pro@example.com', 'plan' => 'pro', 'is_admin' => false]);
    $adminPro = User::create(['name' => 'AdminPro', 'email' => 'admin@example.com', 'plan' => 'pro', 'is_admin' => true]);

    SimpleSurveys::register(
        Survey::make('admin-pro-survey')
            ->targetUser(fn ($user): bool => $user instanceof User && $user->is_admin)
            ->target(fn (Builder $query) => $query->where('plan', 'pro'))
    );

    expect(SimpleSurveys::forUser($regularPro))->toBeNull()
        ->and(SimpleSurveys::forUser($adminPro)?->key())->toBe('admin-pro-survey');
});

it('respects date windows and priority ordering when multiple surveys match', function (): void {
    $user = User::create(['name' => 'Sam', 'email' => 'sam@example.com']);
    $now = CarbonImmutable::parse('2026-11-20 12:00:00');

    SimpleSurveys::register(
        Survey::make('scheduled-high')
            ->priority(100)
            ->startsAt('2026-12-01 00:00:00')
    );

    SimpleSurveys::register(
        Survey::make('expired-high')
            ->priority(90)
            ->until('2026-11-10 00:00:00')
    );

    SimpleSurveys::register(Survey::make('active-low')->priority(5));
    SimpleSurveys::register(Survey::make('active-high')->priority(25));

    expect(SimpleSurveys::forUser($user, $now)?->key())->toBe('active-high')
        ->and(SimpleSurveys::eligibleFor($user, $now)->map->key()->all())->toBe([
            'active-high',
            'active-low',
        ])
        ->and(SimpleSurveys::isEligible('unknown-survey', $user, $now))->toBeFalse();
});

it('re-prompts eligible users after the dismissal cooldown expires', function (): void {
    $user = User::create(['name' => 'Pat', 'email' => 'pat@example.com']);
    $dismissedAt = CarbonImmutable::parse('2026-11-01 10:00:00');

    SimpleSurveys::register(
        Survey::make('reprompt-survey')
            ->repromptAfterDays(14)
    );

    $record = SimpleSurveys::sync()->firstOrFail();

    SurveyDismissal::query()->create([
        'survey_id' => $record->id,
        'respondent_type' => $user->getMorphClass(),
        'respondent_id' => $user->id,
        'dismissed_at' => $dismissedAt,
    ]);

    $day13 = $dismissedAt->addDays(13);
    $day14 = $dismissedAt->addDays(14);

    expect(SimpleSurveys::forUser($user, $day13))->toBeNull()
        ->and(SimpleSurveys::eligibleFor($user, $day13))->toBeEmpty()
        ->and(SimpleSurveys::isEligible('reprompt-survey', $user, $day13))->toBeFalse()
        ->and(SimpleSurveys::forUser($user, $day14)?->key())->toBe('reprompt-survey')
        ->and(SimpleSurveys::eligibleFor($user, $day14)->map->key()->all())->toBe(['reprompt-survey'])
        ->and(SimpleSurveys::isEligible('reprompt-survey', $user, $day14))->toBeTrue();
});

it('filters eligible surveys by page route and path with zero db queries when none match', function (): void {
    $user = User::create(['name' => 'Nora', 'email' => 'nora@example.com']);

    SimpleSurveys::register(
        Survey::make('billing-only')
            ->priority(50)
            ->onRoutes('billing.*')
    );

    SimpleSurveys::register(
        Survey::make('dashboard-only')
            ->priority(10)
            ->onPaths('/dashboard/*')
            ->exceptPaths('/dashboard/secret')
    );

    $billingContext = new PageContext('billing.invoices', 'billing/invoices');
    $dashboardContext = new PageContext('dashboard.analytics', 'dashboard/analytics');
    $secretContext = new PageContext('dashboard.secret', 'dashboard/secret');
    $settingsContext = new PageContext('settings', 'settings');

    expect(SimpleSurveys::forUser($user, context: $billingContext)?->key())->toBe('billing-only')
        ->and(SimpleSurveys::forUser($user, context: $dashboardContext)?->key())->toBe('dashboard-only')
        ->and(SimpleSurveys::eligibleFor($user, context: $dashboardContext)->map->key()->all())->toBe(['dashboard-only'])
        ->and(SimpleSurveys::isEligible('dashboard-only', $user, context: $secretContext))->toBeFalse();

    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(SimpleSurveys::forUser($user, context: $settingsContext))->toBeNull()
        ->and(SimpleSurveys::eligibleFor($user, context: $settingsContext))->toBeEmpty()
        ->and(SimpleSurveys::isEligible('billing-only', $user, context: $settingsContext))->toBeFalse()
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('evaluates starts_at and ends_at windows at runtime even after a survey is synced to the database', function (): void {
    $user = User::create(['name' => 'Elena', 'email' => 'elena@example.com']);
    $baseTime = CarbonImmutable::parse('2026-11-27 12:00:00');
    $this->travelTo($baseTime);

    SimpleSurveys::register(
        Survey::make('scheduled-synced')
            ->startsAt($baseTime->addHour())
    );

    SimpleSurveys::register(
        Survey::make('expiring-synced')
            ->until($baseTime->addHours(2))
    );

    SimpleSurveys::sync();

    // Initially: expiring-synced is active, scheduled-synced is scheduled
    expect(SimpleSurveys::forUser($user)?->key())->toBe('expiring-synced')
        ->and(SimpleSurveys::isEligible('scheduled-synced', $user))->toBeFalse()
        ->and(SimpleSurveys::isEligible('expiring-synced', $user))->toBeTrue();

    // After 90 minutes (without re-syncing): scheduled-synced is now active alongside expiring-synced
    $this->travelTo($baseTime->addMinutes(90));

    expect(SimpleSurveys::eligibleFor($user)->map->key()->all())->toBe([
        'scheduled-synced',
        'expiring-synced',
    ])
        ->and(SimpleSurveys::isEligible('scheduled-synced', $user))->toBeTrue()
        ->and(SimpleSurveys::isEligible('expiring-synced', $user))->toBeTrue();

    // After 3 hours (without re-syncing): expiring-synced has expired and is no longer eligible
    $this->travelTo($baseTime->addHours(3));

    expect(SimpleSurveys::forUser($user)?->key())->toBe('scheduled-synced')
        ->and(SimpleSurveys::eligibleFor($user)->map->key()->all())->toBe(['scheduled-synced'])
        ->and(SimpleSurveys::isEligible('expiring-synced', $user))->toBeFalse();
});

it('updates starts_at and ends_at on subsequent syncs without clobbering manual overrides', function (): void {
    $user = User::create(['name' => 'Marco', 'email' => 'marco@example.com']);
    $baseTime = CarbonImmutable::parse('2026-11-27 12:00:00');
    $this->travelTo($baseTime);

    SimpleSurveys::register(
        Survey::make('rescheduled')
            ->startsAt($baseTime->addDays(5))
            ->until($baseTime->addDays(10))
    );
    SimpleSurveys::sync();

    expect(SimpleSurveys::isEligible('rescheduled', $user))->toBeFalse();

    // Developer moves schedule earlier and re-syncs
    SimpleSurveys::register(
        Survey::make('rescheduled')
            ->startsAt($baseTime->subHour())
            ->until($baseTime->addHour())
    );
    SimpleSurveys::sync();

    $record = SurveyRecord::query()->byKey('rescheduled')->firstOrFail();
    expect($record->starts_at?->equalTo($baseTime->subHour()))->toBeTrue()
        ->and($record->ends_at?->equalTo($baseTime->addHour()))->toBeTrue()
        ->and(SimpleSurveys::isEligible('rescheduled', $user))->toBeTrue();

    // Wait past ends_at, then admin reopens -> subsequent sync must preserve reopened state
    $this->travelTo($baseTime->addHours(2));
    expect(SimpleSurveys::isEligible('rescheduled', $user))->toBeFalse();

    SimpleSurveys::reopen('rescheduled');
    SimpleSurveys::sync();

    $reopened = SurveyRecord::query()->byKey('rescheduled')->firstOrFail();
    expect($reopened->ends_at)->toBeNull()
        ->and(SimpleSurveys::isEligible('rescheduled', $user))->toBeTrue();
});
