<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Models\SurveyImpression;
use DanielCristea\SimpleSurveys\Models\SurveyOpen;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Services\SurveyResultsAggregator;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::define('viewSimpleSurveys', static fn (User $user): bool => $user->is_admin);
});

it('denies access to guests and unauthorized users', function (): void {
    $regularUser = User::create(['name' => 'Regular', 'email' => 'regular@example.com', 'is_admin' => false]);

    $this->getJson(route('simple-surveys.admin.index'))
        ->assertUnauthorized();

    $this->actingAs($regularUser)
        ->get(route('simple-surveys.admin.index'))
        ->assertForbidden();

    $this->actingAs($regularUser)
        ->get(route('simple-surveys.admin.show', 'any-survey'))
        ->assertForbidden();

    $this->actingAs($regularUser)
        ->get(route('simple-surveys.admin.export', 'any-survey'))
        ->assertForbidden();
});

it('displays running, unsynced, and archived surveys on the admin index', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $user = User::create(['name' => 'Customer', 'email' => 'customer@example.com']);

    // Archived survey: synced to DB, then removed from code registry
    SimpleSurveys::register(Survey::make('legacy-survey')->title('Legacy Survey'));
    SimpleSurveys::sync();
    SimpleSurveys::clear();

    // Currently registered surveys (one with responses, one unsynced)
    SimpleSurveys::register(
        Survey::make('active-nps')
            ->title('Active NPS')
            ->questions([Question::rating('score', 'Score')->scale(1, 5)->required()])
    );
    SimpleSurveys::register(Survey::make('upcoming')->title('Upcoming Survey')->startsAt(now()->addWeek()));

    $this->actingAs($user)
        ->postJson(route('simple-surveys.responses.store', 'active-nps'), [
            'answers' => ['score' => 5],
        ])
        ->assertCreated();

    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.index'))
        ->assertOk()
        ->assertSee('Active NPS')
        ->assertSee('Upcoming Survey')
        ->assertSee('Legacy Survey')
        ->assertSee('archived');
});

it('aggregates all question types accurately and renders the show page', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'is_admin' => false]);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'is_admin' => false]);
    $clara = User::create(['name' => 'Clara', 'email' => 'clara@example.com', 'is_admin' => false]);
    $dave = User::create(['name' => 'Dave', 'email' => 'dave@example.com', 'is_admin' => false]);
    $eve = User::create(['name' => 'Eve', 'email' => 'eve@example.com', 'is_admin' => false]);

    SimpleSurveys::register(
        Survey::make('product-check')
            ->title('Product Check')
            ->questions([
                Question::rating('score', 'Score')->scale(1, 5)->required(),
                Question::number('seats', 'Seats')->min(1)->max(100),
                Question::select('source', 'Source')->options(['google' => 'Google', 'friend' => 'Friend']),
                Question::checkbox('features', 'Features')->options(['api' => 'API', 'ui' => 'UI']),
                Question::boolean('recommend', 'Recommend'),
                Question::textarea('notes', 'Notes'),
            ])
    );

    // Alice sees, opens, and submits
    $this->actingAs($alice)->postJson(route('simple-surveys.impression', 'product-check'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.open', 'product-check'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.responses.store', 'product-check'), [
        'answers' => [
            'score' => 5,
            'seats' => 10,
            'source' => 'google',
            'features' => ['api', 'ui'],
            'recommend' => true,
            'notes' => 'Fantastic product!',
        ],
    ])->assertCreated();

    // Bob sees and submits
    $this->actingAs($bob)->postJson(route('simple-surveys.impression', 'product-check'))->assertOk();
    $this->actingAs($bob)->postJson(route('simple-surveys.responses.store', 'product-check'), [
        'answers' => [
            'score' => 3,
            'seats' => 30,
            'source' => 'friend',
            'features' => ['api'],
            'recommend' => false,
            'notes' => 'Good overall.',
        ],
    ])->assertCreated();

    // Clara sees, opens, and dismisses without answering -> counts toward seen, opens & abandoned
    $this->actingAs($clara)->postJson(route('simple-surveys.impression', 'product-check'))->assertOk();
    $this->actingAs($clara)->postJson(route('simple-surveys.open', 'product-check'))->assertOk();
    $this->actingAs($clara)->postJson(route('simple-surveys.dismiss', 'product-check'))->assertOk();

    // Dave sees, opens, and abandons without submitting or dismissing
    $this->actingAs($dave)->postJson(route('simple-surveys.impression', 'product-check'))->assertOk();
    $this->actingAs($dave)->postJson(route('simple-surveys.open', 'product-check'))->assertOk();

    // Eve sees the banner and ignores it completely
    $this->actingAs($eve)->postJson(route('simple-surveys.impression', 'product-check'))->assertOk();

    $aggregator = app(SurveyResultsAggregator::class);
    $results = $aggregator->forSurvey('product-check');
    $summary = $aggregator->summaries()->firstWhere('key', 'product-check');

    expect($results)->not->toBeNull()
        ->and($results['impressions_count'])->toBe(5)
        ->and($results['opens_count'])->toBe(3)
        ->and($results['abandoned_count'])->toBe(2)
        ->and($results['responses_count'])->toBe(2)
        ->and($results['dismissals_count'])->toBe(1)
        ->and($results['response_rate'])->toBe(40.0)
        ->and($results['completion_rate'])->toBe(66.7)
        ->and($summary)->not->toBeNull()
        ->and($summary['impressions_count'])->toBe(5)
        ->and($summary['opens_count'])->toBe(3)
        ->and($summary['abandoned_count'])->toBe(2)
        ->and($summary['response_rate'])->toBe(40.0)
        ->and($summary['completion_rate'])->toBe(66.7);

    $byKey = collect($results['questions'])->keyBy('key');

    expect($byKey['score']['stats'])->toBe(['avg' => 4.0, 'min' => 3.0, 'max' => 5.0])
        ->and($byKey['seats']['stats'])->toBe(['avg' => 20.0, 'min' => 10.0, 'max' => 30.0])
        ->and(collect($byKey['source']['breakdown'])->pluck('count', 'value')->all())->toBe(['google' => 1, 'friend' => 1])
        ->and(collect($byKey['features']['breakdown'])->pluck('count', 'value')->all())->toBe(['api' => 2, 'ui' => 1])
        ->and(collect($byKey['recommend']['breakdown'])->pluck('count', 'label')->all())->toBe(['Yes' => 1, 'No' => 1])
        ->and($byKey['notes']['answered_count'])->toBe(2);

    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.index'))
        ->assertOk()
        ->assertSee('Avg Response Rate')
        ->assertSee('Avg Completion Rate')
        ->assertSee('Avg Completion Time')
        ->assertSee('40%')
        ->assertSee('66.7%');

    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'product-check'))
        ->assertOk()
        ->assertSee('Product Check')
        ->assertSee('Seen')
        ->assertSee('Response Rate')
        ->assertSee('Responses / Seen')
        ->assertSee('40%')
        ->assertSee('Completion Rate')
        ->assertSee('Responses / Opened')
        ->assertSee('66.7%')
        ->assertSee('Abandoned')
        ->assertSee('Fantastic product!')
        ->assertSee('btn btn-warning', false)
        ->assertSee('data-confirm-variant="warning"', false)
        ->assertSee('btn btn-danger-solid', false)
        ->assertSee('data-confirm-variant="danger"', false)
        ->assertSee('Export CSV');

    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'non-existent'))
        ->assertNotFound();
});

it('streams a valid csv export of survey responses', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

    SimpleSurveys::register(
        Survey::make('csv-survey')
            ->title('CSV Survey')
            ->questions([
                Question::rating('score', 'Rating Score')->required(),
                Question::checkbox('tags', 'Tags')->options(['fast' => 'Fast', 'clean' => 'Clean']),
                Question::boolean('recommend', 'Recommend'),
            ])
    );

    $this->actingAs($alice)->postJson(route('simple-surveys.responses.store', 'csv-survey'), [
        'answers' => [
            'score' => 5,
            'tags' => ['fast', 'clean'],
            'recommend' => true,
        ],
        'url' => '/billing',
    ])->assertCreated();

    $response = $this->actingAs($admin)
        ->get(route('simple-surveys.admin.export', 'csv-survey'));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('"Response ID","Respondent Type","Respondent ID","Submitted At","Page URL","Rating Score",Tags,Recommend')
        ->toContain('/billing')
        ->toContain('fast, clean')
        ->toContain('Yes');

    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.export', 'unknown'))
        ->assertNotFound();
});

it('allows an authorized admin to end a running survey and re-open it later', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $regularUser = User::create(['name' => 'Regular', 'email' => 'regular@example.com', 'is_admin' => false]);

    SimpleSurveys::register(
        Survey::make('toggle-survey')
            ->title('Toggle Survey')
            ->questions([
                Question::text('feedback', 'Feedback')->required(),
            ])
    );

    // Unauthorized users cannot close or reopen
    $this->actingAs($regularUser)
        ->post(route('simple-surveys.admin.close', 'toggle-survey'))
        ->assertForbidden();

    $this->actingAs($regularUser)
        ->post(route('simple-surveys.admin.reopen', 'toggle-survey'))
        ->assertForbidden();

    // Initially active: admin sees End Survey Now button, confirm prompt, and Back to App link
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'toggle-survey'))
        ->assertOk()
        ->assertSee('Back to App')
        ->assertSee('End Survey Now')
        ->assertSee('Are you sure you want to end this survey now?')
        ->assertDontSee('Re-open Survey');

    expect(SimpleSurveys::forUser($regularUser)?->key())->toBe('toggle-survey');

    // Admin ends the survey now
    $this->actingAs($admin)
        ->post(route('simple-surveys.admin.close', 'toggle-survey'))
        ->assertRedirect()
        ->assertSessionHas('simple_surveys_admin_status');

    // Re-syncing from code must NOT overwrite the admin's closed status
    SimpleSurveys::sync();

    expect(SimpleSurveys::forUser($regularUser))->toBeNull();

    $this->actingAs($regularUser)
        ->postJson(route('simple-surveys.responses.store', 'toggle-survey'), [
            'answers' => ['feedback' => 'Too late!'],
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'toggle-survey'))
        ->assertOk()
        ->assertSee('Re-open Survey')
        ->assertDontSee('End Survey Now');

    // Admin re-opens the survey
    $this->actingAs($admin)
        ->post(route('simple-surveys.admin.reopen', 'toggle-survey'))
        ->assertRedirect()
        ->assertSessionHas('simple_surveys_admin_status');

    expect(SimpleSurveys::forUser($regularUser)?->key())->toBe('toggle-survey');

    $this->actingAs($regularUser)
        ->postJson(route('simple-surveys.responses.store', 'toggle-survey'), [
            'answers' => ['feedback' => 'Back open!'],
        ])
        ->assertCreated();
});

it('allows an authorized admin to reset all responses and dismissals for a survey', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'is_admin' => false]);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'is_admin' => false]);

    SimpleSurveys::register(
        Survey::make('reset-me')
            ->title('Reset Me')
            ->questions([
                Question::rating('score', 'Score')->scale(1, 5)->required(),
                Question::text('feedback', 'Feedback'),
            ])
    );

    SimpleSurveys::register(
        Survey::make('keep-me')
            ->title('Keep Me')
            ->questions([
                Question::text('note', 'Note')->required(),
            ])
    );

    // Unauthorized users cannot reset
    $this->actingAs($alice)
        ->post(route('simple-surveys.admin.reset', 'reset-me'))
        ->assertForbidden();

    // Unknown survey returns 404
    $this->actingAs($admin)
        ->post(route('simple-surveys.admin.reset', 'does-not-exist'))
        ->assertNotFound();

    // Populate impressions, opens, responses, and dismissals
    $this->actingAs($alice)->postJson(route('simple-surveys.impression', 'reset-me'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.open', 'reset-me'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.responses.store', 'reset-me'), [
        'answers' => [
            'score' => 5,
            'feedback' => 'Great!',
        ],
    ])->assertCreated();

    $this->actingAs($bob)->postJson(route('simple-surveys.impression', 'reset-me'))->assertOk();
    $this->actingAs($bob)->postJson(route('simple-surveys.open', 'reset-me'))->assertOk();
    $this->actingAs($bob)->postJson(route('simple-surveys.dismiss', 'reset-me'))->assertOk();

    $this->actingAs($alice)->postJson(route('simple-surveys.impression', 'keep-me'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.open', 'keep-me'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.responses.store', 'keep-me'), [
        'answers' => ['note' => 'Keep this answer'],
    ])->assertCreated();

    expect(SimpleSurveys::isEligible('reset-me', $alice))->toBeFalse()
        ->and(SimpleSurveys::isEligible('reset-me', $bob))->toBeFalse()
        ->and(SurveyAnswer::query()->count())->toBe(3)
        ->and(SurveyOpen::query()->forSurvey('reset-me')->count())->toBe(2)
        ->and(SurveyOpen::query()->forSurvey('keep-me')->count())->toBe(1)
        ->and(SurveyImpression::query()->forSurvey('reset-me')->count())->toBe(2)
        ->and(SurveyImpression::query()->forSurvey('keep-me')->count())->toBe(1);

    // Admin sees Reset Responses button before reset
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'reset-me'))
        ->assertOk()
        ->assertSee('Reset Responses');

    // Admin resets responses for reset-me
    $this->actingAs($admin)
        ->post(route('simple-surveys.admin.reset', 'reset-me'))
        ->assertRedirect()
        ->assertSessionHas('simple_surveys_admin_status');

    // reset-me responses, answers, dismissals, opens, and impressions are deleted; keep-me is untouched
    expect(SurveyResponse::query()->forSurvey('reset-me')->count())->toBe(0)
        ->and(SurveyDismissal::query()->forSurvey('reset-me')->count())->toBe(0)
        ->and(SurveyOpen::query()->forSurvey('reset-me')->count())->toBe(0)
        ->and(SurveyImpression::query()->forSurvey('reset-me')->count())->toBe(0)
        ->and(
            SurveyAnswer::query()
                ->whereHas('response', static fn ($q) => $q->whereHas('survey', static fn ($sq) => $sq->where('key', 'reset-me')))
                ->count()
        )->toBe(0)
        ->and(SurveyAnswer::query()->count())->toBe(1)
        ->and(SurveyResponse::query()->forSurvey('keep-me')->count())->toBe(1)
        ->and(SurveyOpen::query()->forSurvey('keep-me')->count())->toBe(1)
        ->and(SurveyImpression::query()->forSurvey('keep-me')->count())->toBe(1);

    // Button disappears when no responses/dismissals remain
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'reset-me'))
        ->assertOk()
        ->assertDontSee('Reset Responses');

    // Both Alice and Bob are eligible again, and Alice can submit a fresh response
    expect(SimpleSurveys::isEligible('reset-me', $alice))->toBeTrue()
        ->and(SimpleSurveys::isEligible('reset-me', $bob))->toBeTrue();

    $this->actingAs($alice)->postJson(route('simple-surveys.responses.store', 'reset-me'), [
        'answers' => [
            'score' => 4,
            'feedback' => 'Submitted again after reset!',
        ],
    ])->assertCreated();
});

it('supports searching, status filtering, sorting, and read-only priority display on the admin index', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    SimpleSurveys::register(Survey::make('legacy-archived')->title('Legacy Archived')->priority(5));
    SimpleSurveys::sync();
    SimpleSurveys::clear();

    SimpleSurveys::register(
        Survey::make('billing-pulse')
            ->title('Billing Pulse')
            ->priority(50)
            ->questions([Question::rating('score', 'Score')->scale(1, 5)->required()])
    );
    SimpleSurveys::register(
        Survey::make('alpha-onboarding')
            ->title('Alpha Onboarding')
            ->priority(10)
            ->questions([Question::rating('score', 'Score')->scale(1, 5)->required()])
    );
    SimpleSurveys::register(
        Survey::make('draft-ideas')
            ->title('Draft Ideas')
            ->priority(0)
            ->draft()
    );

    // Give alpha-onboarding 2 timed responses (10s & 20s -> 15s avg) and billing-pulse 1 response
    $this->actingAs($alice)->postJson(route('simple-surveys.responses.store', 'alpha-onboarding'), ['answers' => ['score' => 5], 'duration_ms' => 10000])->assertCreated();
    $this->actingAs($bob)->postJson(route('simple-surveys.responses.store', 'alpha-onboarding'), ['answers' => ['score' => 4], 'duration_ms' => 20000])->assertCreated();
    $this->actingAs($alice)->postJson(route('simple-surveys.responses.store', 'billing-pulse'), ['answers' => ['score' => 5]])->assertCreated();

    // Default sort is priority descending (Billing Pulse [50] -> Alpha Onboarding [10] -> Legacy Archived [5] -> Draft Ideas [0])
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.index'))
        ->assertOk()
        ->assertSee('Priority')
        ->assertSee('Avg Completion Time')
        ->assertSee('15s')
        ->assertSee('Across 2 responses')
        ->assertSee('data-set-theme="dark"', false)
        ->assertSeeInOrder(['Billing Pulse', '50', 'Alpha Onboarding', '10', 'Legacy Archived', '5', 'Draft Ideas']);

    // Status filter: draft
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.index', ['status' => 'draft']))
        ->assertOk()
        ->assertSee('Draft Ideas')
        ->assertDontSee('Billing Pulse')
        ->assertDontSee('Legacy Archived');

    // Status filter: archived
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.index', ['status' => 'archived']))
        ->assertOk()
        ->assertSee('Legacy Archived')
        ->assertDontSee('Billing Pulse');

    // Search query
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.index', ['q' => 'billing']))
        ->assertOk()
        ->assertSee('Billing Pulse')
        ->assertDontSee('Alpha Onboarding');

    // Empty search state
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.index', ['q' => 'nonexistent-xyz']))
        ->assertOk()
        ->assertSee('No surveys match your current filters')
        ->assertSee('Clear filters');

    // Sort by responses descending (Alpha Onboarding [2] before Billing Pulse [1])
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.index', ['sort' => 'responses']))
        ->assertOk()
        ->assertSeeInOrder(['Alpha Onboarding', 'Billing Pulse']);

    // Sort by title ascending (Alpha Onboarding before Billing Pulse)
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.index', ['sort' => 'title']))
        ->assertOk()
        ->assertSeeInOrder(['Alpha Onboarding', 'Billing Pulse', 'Draft Ideas', 'Legacy Archived']);
});

it('renders tabbed overview charts, nps/histograms, and filtered responses tab with date ranges', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);
    $clara = User::create(['name' => 'Clara', 'email' => 'clara@example.com']);

    SimpleSurveys::register(
        Survey::make('detailed-nps')
            ->title('Detailed NPS')
            ->priority(25)
            ->questions([
                Question::rating('nps', 'Likelihood to recommend')->scale(0, 10)->required(),
                Question::number('team_size', 'Team Size')->min(1)->max(500),
                Question::select('source', 'Discovery Source')->options(['google' => 'Google', 'friend' => 'Friend']),
                Question::checkbox('features', 'Used Features')->options(['api' => 'API', 'ui' => 'UI']),
                Question::boolean('recommend', 'Would you return?'),
                Question::textarea('notes', 'Additional Notes'),
            ])
    );

    // Recent French response from Alice
    app()->setLocale('fr');
    $this->actingAs($alice)->postJson(route('simple-surveys.impression', 'detailed-nps'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.open', 'detailed-nps'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.responses.store', 'detailed-nps'), [
        'answers' => [
            'nps' => 10,
            'team_size' => 12,
            'source' => 'google',
            'features' => ['api', 'ui'],
            'recommend' => true,
            'notes' => 'Superbe produit!',
        ],
        'url' => '/fr/dashboard',
    ])->assertCreated();

    // Recent English response from Bob
    app()->setLocale('en');
    $this->actingAs($bob)->postJson(route('simple-surveys.impression', 'detailed-nps'))->assertOk();
    $this->actingAs($bob)->postJson(route('simple-surveys.open', 'detailed-nps'))->assertOk();
    $this->actingAs($bob)->postJson(route('simple-surveys.responses.store', 'detailed-nps'), [
        'answers' => [
            'nps' => 5,
            'team_size' => 45,
            'source' => 'friend',
            'features' => ['ui'],
            'recommend' => false,
            'notes' => 'Needs dark mode.',
        ],
        'url' => '/billing',
    ])->assertCreated();

    // Old English response from Clara (45 days ago)
    $this->actingAs($clara)->postJson(route('simple-surveys.responses.store', 'detailed-nps'), [
        'answers' => [
            'nps' => 8,
            'team_size' => 80,
            'source' => 'google',
            'features' => ['api'],
            'recommend' => true,
            'notes' => 'Older response text.',
        ],
        'url' => '/legacy',
    ])->assertCreated();

    $oldResponse = SurveyResponse::query()->forRespondent($clara)->firstOrFail();
    $oldResponse->forceFill(['created_at' => now()->subDays(45), 'updated_at' => now()->subDays(45)])->save();
    SurveyAnswer::query()->where('response_id', $oldResponse->id)->update(['created_at' => now()->subDays(45)]);

    // Overview tab: metadata, funnel, trend SVG, NPS, locales strip, and NO full responses table
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'detailed-nps'))
        ->assertOk()
        ->assertSee('Priority: 25')
        ->assertSee('6 questions')
        ->assertSee('Conversion Funnel')
        ->assertSee('Responses Over Time')
        ->assertSee('Net Promoter Score (NPS)')
        ->assertSee('<code>fr</code>', false)
        ->assertSee('<code>en</code>', false)
        ->assertDontSee('id="lss-response-drawer"', false);

    // Responses tab: table, drawer, showing count
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', ['surveyKey' => 'detailed-nps', 'tab' => 'responses']))
        ->assertOk()
        ->assertSee('id="lss-response-drawer"', false)
        ->assertSee('Showing 1–3 of 3 responses')
        ->assertSee('Superbe produit!')
        ->assertSee('Needs dark mode.')
        ->assertSee('Older response text.');

    // Responses tab filtered by search query
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', ['surveyKey' => 'detailed-nps', 'tab' => 'responses', 'q' => 'dark mode']))
        ->assertOk()
        ->assertSee('Needs dark mode.')
        ->assertDontSee('Superbe produit!');

    // Responses tab filtered by locale
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', ['surveyKey' => 'detailed-nps', 'tab' => 'responses', 'locale' => 'fr']))
        ->assertOk()
        ->assertSee('Superbe produit!')
        ->assertDontSee('Needs dark mode.');

    // Responses tab cross-filtered by choice answer
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', ['surveyKey' => 'detailed-nps', 'tab' => 'responses', 'question' => 'source', 'answer' => 'friend']))
        ->assertOk()
        ->assertSee('Needs dark mode.')
        ->assertDontSee('Superbe produit!');

    // Responses tab cross-filtered by checkbox answer
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', ['surveyKey' => 'detailed-nps', 'tab' => 'responses', 'question' => 'features', 'answer' => 'api']))
        ->assertOk()
        ->assertSee('Superbe produit!')
        ->assertSee('Older response text.')
        ->assertDontSee('Needs dark mode.');

    // Date range filter (7d) excludes Clara's 45-day-old response
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', ['surveyKey' => 'detailed-nps', 'tab' => 'responses', 'range' => '7d']))
        ->assertOk()
        ->assertSee('Showing 1–2 of 2 responses')
        ->assertSee('Superbe produit!')
        ->assertSee('Needs dark mode.')
        ->assertDontSee('Older response text.');

    // Appearance tab shows unified 3-tab bar, read-only priority, collapsible cards, and sticky save bar
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.appearance.edit', 'detailed-nps'))
        ->assertOk()
        ->assertSee('Priority: 25')
        ->assertSee('Results &amp; Metrics', false)
        ->assertSee('Responses')
        ->assertSee('lss-unsaved-pill', false);
});

it('preserves answer=0 in boolean, nps rating, choice cross-filter links, date-range links, and renders 0-10 in appearance preview', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $yesUser = User::create(['name' => 'Yes User', 'email' => 'yes@example.com']);
    $noUser = User::create(['name' => 'No User', 'email' => 'no@example.com']);

    SimpleSurveys::register(
        Survey::make('zero-value-test')
            ->title('Zero Value Test')
            ->questions([
                Question::rating('nps', 'NPS Score')->scale(0, 10)->required(),
                Question::boolean('recommend', 'Would you recommend us?')->required(),
                Question::select('tier', 'Plan Tier')->options(['0' => 'Free', 'pro' => 'Pro']),
                Question::text('note', 'Note'),
            ])
    );

    $this->actingAs($yesUser)->postJson(route('simple-surveys.responses.store', 'zero-value-test'), [
        'answers' => [
            'nps' => 10,
            'recommend' => true,
            'tier' => 'pro',
            'note' => 'Happy promoter',
        ],
    ])->assertCreated();

    $this->actingAs($noUser)->postJson(route('simple-surveys.responses.store', 'zero-value-test'), [
        'answers' => [
            'nps' => 0,
            'recommend' => false,
            'tier' => '0',
            'note' => 'Zero detractor',
        ],
    ])->assertCreated();

    // Overview tab must include answer=0 on Boolean No, NPS 0 bar, and '0' select choice links
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'zero-value-test'))
        ->assertOk()
        ->assertSee('question=recommend&amp;answer=0', false)
        ->assertSee('question=nps&amp;answer=0', false)
        ->assertSee('question=tier&amp;answer=0', false);

    // Filtering Responses tab by answer=0 returns strictly the false/zero respondent and preserves answer=0 on date-range links
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', [
            'surveyKey' => 'zero-value-test',
            'tab' => 'responses',
            'question' => 'recommend',
            'answer' => '0',
        ]))
        ->assertOk()
        ->assertSee('Zero detractor')
        ->assertDontSee('Happy promoter')
        ->assertSee('Would you recommend us? = 0')
        ->assertSee('range=7d&amp;question=recommend&amp;answer=0', false);

    // Appearance Live Preview must render all 11 options (0..10) for a 0-10 rating scale
    $appearanceResponse = $this->actingAs($admin)
        ->get(route('simple-surveys.admin.appearance.edit', 'zero-value-test'))
        ->assertOk()
        ->assertSee('<span>0</span>', false)
        ->assertSee('<span>10</span>', false);

    expect(substr_count($appearanceResponse->getContent(), 'name="preview_nps"'))->toBe(11);
});

it('reflects effective scheduled, expired, and reopened statuses accurately in admin summaries and detail views', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $user = User::create(['name' => 'Customer', 'email' => 'customer@example.com', 'is_admin' => false]);
    $baseTime = CarbonImmutable::parse('2026-11-27 12:00:00');
    $this->travelTo($baseTime);

    SimpleSurveys::register(
        Survey::make('scheduled-survey')
            ->title('Scheduled Survey')
            ->startsAt($baseTime->addHour())
            ->questions([Question::text('q', 'Q')])
    );

    SimpleSurveys::register(
        Survey::make('expiring-survey')
            ->title('Expiring Survey')
            ->until($baseTime->addHours(2))
            ->questions([Question::text('q', 'Q')])
    );

    SimpleSurveys::sync();

    $aggregator = app(SurveyResultsAggregator::class);

    $initialSummaries = $aggregator->summaries()->keyBy('key');
    expect($initialSummaries['scheduled-survey']['status'])->toBe(SurveyStatus::Scheduled)
        ->and($initialSummaries['expiring-survey']['status'])->toBe(SurveyStatus::Active)
        ->and($aggregator->forSurvey('scheduled-survey')['status'])->toBe(SurveyStatus::Scheduled)
        ->and($aggregator->forSurvey('expiring-survey')['status'])->toBe(SurveyStatus::Active);

    // Advance 3 hours without re-syncing: scheduled-survey is now Active, expiring-survey is now Closed
    $this->travelTo($baseTime->addHours(3));

    $laterSummaries = $aggregator->summaries()->keyBy('key');
    $laterOptions = collect($aggregator->surveyOptions())->keyBy('key');

    expect($laterSummaries['scheduled-survey']['status'])->toBe(SurveyStatus::Active)
        ->and($laterSummaries['expiring-survey']['status'])->toBe(SurveyStatus::Closed)
        ->and($laterOptions['scheduled-survey']['status'])->toBe(SurveyStatus::Active)
        ->and($laterOptions['expiring-survey']['status'])->toBe(SurveyStatus::Closed)
        ->and($aggregator->forSurvey('scheduled-survey')['status'])->toBe(SurveyStatus::Active)
        ->and($aggregator->forSurvey('expiring-survey')['status'])->toBe(SurveyStatus::Closed);

    // Admin detail page shows End Survey Now for scheduled-survey and Re-open Survey for expiring-survey
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'scheduled-survey'))
        ->assertOk()
        ->assertSee('End Survey Now')
        ->assertDontSee('Re-open Survey');

    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'expiring-survey'))
        ->assertOk()
        ->assertSee('Re-open Survey')
        ->assertDontSee('End Survey Now');

    // Appearance page badge also reflects effective status
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.appearance.edit', 'expiring-survey'))
        ->assertOk()
        ->assertSee('badge-closed', false);

    // Admin closes scheduled-survey and reopens expiring-survey (whose code ends_at is now in the past)
    $this->actingAs($admin)
        ->post(route('simple-surveys.admin.close', 'scheduled-survey'))
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('simple-surveys.admin.reopen', 'expiring-survey'))
        ->assertRedirect();

    // Subsequent sync preserves both manual overrides
    SimpleSurveys::sync();

    $overriddenSummaries = $aggregator->summaries()->keyBy('key');
    expect($overriddenSummaries['scheduled-survey']['status'])->toBe(SurveyStatus::Closed)
        ->and($overriddenSummaries['expiring-survey']['status'])->toBe(SurveyStatus::Active)
        ->and($overriddenSummaries['expiring-survey']['ends_at'])->toBeNull()
        ->and($aggregator->forSurvey('scheduled-survey')['status'])->toBe(SurveyStatus::Closed)
        ->and($aggregator->forSurvey('expiring-survey')['status'])->toBe(SurveyStatus::Active)
        ->and($aggregator->forSurvey('expiring-survey')['ends_at'])->toBeNull()
        ->and(SimpleSurveys::isEligible('scheduled-survey', $user))->toBeFalse()
        ->and(SimpleSurveys::isEligible('expiring-survey', $user))->toBeTrue();

    // Admin index filter counts reflect the effective statuses
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.index', ['status' => 'active']))
        ->assertOk()
        ->assertSee('Expiring Survey')
        ->assertDontSee('Scheduled Survey');

    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.index', ['status' => 'closed']))
        ->assertOk()
        ->assertSee('Scheduled Survey')
        ->assertDontSee('Expiring Survey');
});
