<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
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

    SurveyResponse::factory()->forSurvey('first')->forRespondent($user)->completed()->create();
    SurveyResponse::factory()->forSurvey('second')->forRespondent($user)->dismissed()->create();
    SurveyResponse::factory()->forSurvey('third')->forRespondent($otherUser)->completed()->create();

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
