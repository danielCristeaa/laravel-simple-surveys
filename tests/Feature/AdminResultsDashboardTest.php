<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
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
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);
    $clara = User::create(['name' => 'Clara', 'email' => 'clara@example.com']);

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

    $this->actingAs($clara)->postJson(route('simple-surveys.dismiss', 'product-check'))->assertOk();

    $aggregator = app(SurveyResultsAggregator::class);
    $results = $aggregator->forSurvey('product-check');

    expect($results)->not->toBeNull()
        ->and($results['responses_count'])->toBe(2)
        ->and($results['dismissals_count'])->toBe(1)
        ->and($results['completion_rate'])->toBe(66.7);

    $byKey = collect($results['questions'])->keyBy('key');

    expect($byKey['score']['stats'])->toBe(['avg' => 4.0, 'min' => 3.0, 'max' => 5.0])
        ->and($byKey['seats']['stats'])->toBe(['avg' => 20.0, 'min' => 10.0, 'max' => 30.0])
        ->and(collect($byKey['source']['breakdown'])->pluck('count', 'value')->all())->toBe(['google' => 1, 'friend' => 1])
        ->and(collect($byKey['features']['breakdown'])->pluck('count', 'value')->all())->toBe(['api' => 2, 'ui' => 1])
        ->and(collect($byKey['recommend']['breakdown'])->pluck('count', 'label')->all())->toBe(['Yes' => 1, 'No' => 1])
        ->and($byKey['notes']['answered_count'])->toBe(2);

    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'product-check'))
        ->assertOk()
        ->assertSee('Product Check')
        ->assertSee('66.7%')
        ->assertSee('Fantastic product!')
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

    // Initially active: admin sees End Survey Now button, user is eligible
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'toggle-survey'))
        ->assertOk()
        ->assertSee('End Survey Now')
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
