<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('returns 204 for guests and ineligible users on the widget endpoint', function (): void {
    SimpleSurveys::register(
        Survey::make('pro-poll')
            ->target(fn (Builder $q) => $q->where('plan', 'pro'))
            ->questions([Question::text('feedback', 'Feedback')])
    );

    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->get(route('simple-surveys.widget'))
        ->assertNoContent();

    expect(DB::getQueryLog())->toBeEmpty();

    $freeUser = User::create(['name' => 'Free', 'email' => 'free@example.com', 'plan' => 'free']);

    $this->actingAs($freeUser)
        ->get(route('simple-surveys.widget'))
        ->assertNoContent();
});

it('renders widget html with no-store cache headers when eligible on the widget endpoint', function (): void {
    $proUser = User::create(['name' => 'Pro', 'email' => 'pro@example.com', 'plan' => 'pro']);

    SimpleSurveys::register(Survey::make('default-high')->priority(50)->title('Default High'));
    SimpleSurveys::register(
        Survey::make('specific-poll')
            ->priority(1)
            ->title('Specific Poll')
            ->banner('Quick poll!')
            ->questions([Question::rating('score', 'Score')->required()])
    );

    $response = $this->actingAs($proUser)
        ->get(route('simple-surveys.widget'));

    $response->assertOk()
        ->assertSee('Default High')
        ->assertSee('lss-pos-bottom-right');

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('private');

    $specificResponse = $this->actingAs($proUser)
        ->get(route('simple-surveys.widget', [
            'survey' => 'specific-poll',
            'position' => 'bottom-left',
        ]));

    $specificResponse->assertOk()
        ->assertSee('Specific Poll')
        ->assertSee('lss-pos-bottom-left')
        ->assertDontSee('Default High');
});

it('returns serialized survey json or null on the eligible endpoint', function (): void {
    $freeUser = User::create(['name' => 'Free', 'email' => 'free@example.com', 'plan' => 'free']);
    $proUser = User::create(['name' => 'Pro', 'email' => 'pro@example.com', 'plan' => 'pro']);

    SimpleSurveys::register(
        Survey::make('onboarding')
            ->title('Onboarding Survey')
            ->banner('Got 30s?')
            ->description('Tell us what you think.')
            ->repromptAfterDays(7)
            ->target(fn (Builder $q) => $q->where('plan', 'pro'))
            ->questions([
                Question::rating('score', 'Score')->scale(1, 5)->required(),
                Question::select('source', 'Source')->options(['google' => 'Google', 'friend' => 'Friend']),
            ])
    );

    // Guests get 200 { survey: null } with 0 DB queries
    DB::enableQueryLog();
    DB::flushQueryLog();

    $guestResponse = $this->getJson(route('simple-surveys.eligible'));
    $guestResponse->assertOk()->assertExactJson(['survey' => null]);
    expect(DB::getQueryLog())->toBeEmpty();

    // Ineligible user gets { survey: null }
    $this->actingAs($freeUser)
        ->getJson(route('simple-surveys.eligible'))
        ->assertOk()
        ->assertExactJson(['survey' => null]);

    // Eligible user gets full serialized survey
    $this->actingAs($proUser)
        ->getJson(route('simple-surveys.eligible'))
        ->assertOk()
        ->assertJson([
            'survey' => [
                'key' => 'onboarding',
                'title' => 'Onboarding Survey',
                'banner_text' => 'Got 30s?',
                'description' => 'Tell us what you think.',
                'dismissible' => true,
                'reprompt_after_dismiss' => true,
                'urls' => [
                    'impression' => route('simple-surveys.impression', 'onboarding'),
                    'open' => route('simple-surveys.open', 'onboarding'),
                    'submit' => route('simple-surveys.responses.store', 'onboarding'),
                    'dismiss' => route('simple-surveys.dismiss', 'onboarding'),
                ],
                'questions' => [
                    [
                        'key' => 'score',
                        'type' => 'rating',
                        'label' => 'Score',
                        'required' => true,
                        'min' => 1,
                        'max' => 5,
                    ],
                    [
                        'key' => 'source',
                        'type' => 'select',
                        'label' => 'Source',
                        'required' => false,
                        'options' => [
                            'google' => 'Google',
                            'friend' => 'Friend',
                        ],
                    ],
                ],
            ],
        ]);

    // Specific unknown survey returns { survey: null }
    $this->actingAs($proUser)
        ->getJson(route('simple-surveys.eligible', ['survey' => 'missing']))
        ->assertOk()
        ->assertExactJson(['survey' => null]);
});

it('renders dynamic mount container for guests and ssr-includes widget when eligible', function (): void {
    $user = User::create(['name' => 'Alex', 'email' => 'alex@example.com']);

    SimpleSurveys::register(
        Survey::make('spa-survey')
            ->title('SPA Survey')
            ->banner('Quick SPA check!')
    );

    // Guest: still mounts dynamic wrapper + SPA navigation listeners, without widget HTML
    $guestHtml = Blade::render('<x-simple-surveys::widget dynamic position="bottom-left" />');

    expect($guestHtml)
        ->toContain('data-lss-dynamic-mount')
        ->toContain(route('simple-surveys.widget', ['position' => 'bottom-left']))
        ->toContain('inertia:navigate')
        ->toContain('livewire:navigated')
        ->toContain('simple-surveys:refresh')
        ->not->toContain('id="lss-widget-')
        ->not->toContain('Quick SPA check!');

    // Authenticated eligible user: SSR-includes widget immediately inside the dynamic wrapper
    $this->actingAs($user);

    $userHtml = Blade::render('<x-simple-surveys::widget dynamic survey="spa-survey" />');

    expect($userHtml)
        ->toContain('data-lss-dynamic-mount')
        ->toContain(e(route('simple-surveys.widget', ['survey' => 'spa-survey', 'position' => 'bottom-right'])))
        ->toContain('id="lss-widget-spa-survey"')
        ->toContain('Quick SPA check!');

    // Once completed, dynamic mount stays rendered for future SPA surveys, but widget is omitted
    SimpleSurveys::sync();
    $record = SurveyRecord::query()->byKey('spa-survey')->firstOrFail();

    SurveyResponse::query()->create([
        'survey_id' => $record->id,
        'respondent_type' => $user->getMorphClass(),
        'respondent_id' => $user->id,
    ]);

    $afterSubmitHtml = Blade::render('<x-simple-surveys::widget dynamic survey="spa-survey" />');

    expect($afterSubmitHtml)
        ->toContain('data-lss-dynamic-mount')
        ->not->toContain('id="lss-widget-');
});

it('evaluates route and path targeting from the url query parameter on dynamic endpoints', function (): void {
    $user = User::create(['name' => 'Leo', 'email' => 'leo@example.com']);

    Route::get('/billing/invoices', static fn (): string => 'ok')->name('billing.invoices');
    Route::get('/settings', static fn (): string => 'ok')->name('settings.index');

    SimpleSurveys::register(
        Survey::make('billing-poll')
            ->title('Billing Poll')
            ->onRoutes('billing.*')
            ->questions([Question::text('feedback', 'Feedback')->required()])
    );

    SimpleSurveys::register(
        Survey::make('dashboard-poll')
            ->title('Dashboard Poll')
            ->onPaths('/dashboard')
    );

    $this->actingAs($user)
        ->get(route('simple-surveys.widget', ['url' => '/billing/invoices']))
        ->assertOk()
        ->assertSee('Billing Poll');

    $this->actingAs($user)
        ->getJson(route('simple-surveys.eligible', ['url' => '/billing/invoices']))
        ->assertOk()
        ->assertJsonPath('survey.key', 'billing-poll');

    $this->actingAs($user)
        ->getJson(route('simple-surveys.eligible', ['url' => '/dashboard']))
        ->assertOk()
        ->assertJsonPath('survey.key', 'dashboard-poll');

    $this->actingAs($user)
        ->get(route('simple-surveys.widget', ['url' => '/settings']))
        ->assertNoContent();

    $this->actingAs($user)
        ->getJson(route('simple-surveys.eligible', ['url' => '/settings']))
        ->assertOk()
        ->assertExactJson(['survey' => null]);

    // Submission endpoint remains unaffected by page targeting
    $this->actingAs($user)
        ->postJson(route('simple-surveys.responses.store', 'billing-poll'), [
            'answers' => ['feedback' => 'Super easy!'],
        ])
        ->assertCreated();
});
