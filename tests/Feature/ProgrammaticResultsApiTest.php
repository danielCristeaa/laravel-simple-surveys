<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Enums\SurveyStatus;
use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyDismissal;
use DanielCristea\SimpleSurveys\Models\SurveyImpression;
use DanielCristea\SimpleSurveys\Models\SurveyOpen;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('exposes survey summaries and detailed results through the facade', function (): void {
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);
    $clara = User::create(['name' => 'Clara', 'email' => 'clara@example.com']);

    // Archived DB survey
    SimpleSurveys::register(Survey::make('archived-pulse')->title('Archived Pulse'));
    SimpleSurveys::sync();
    SimpleSurveys::clear();

    $survey = Survey::make('onboarding-nps')
        ->title('Onboarding NPS')
        ->questions([
            Question::rating('score', 'Overall score')->scale(1, 5)->required(),
            Question::select('channel', 'How did you find us?')->options(['search' => 'Search', 'referral' => 'Referral']),
            Question::textarea('notes', 'Anything else?'),
        ]);

    SimpleSurveys::register($survey);
    SimpleSurveys::register(Survey::make('unsynced-draft')->title('Unsynced Draft')->draft());

    $this->actingAs($alice)->postJson(route('simple-surveys.impression', 'onboarding-nps'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.open', 'onboarding-nps'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.responses.store', 'onboarding-nps'), [
        'answers' => [
            'score' => 5,
            'channel' => 'search',
            'notes' => 'Smooth setup!',
        ],
    ])->assertCreated();

    $this->actingAs($bob)->postJson(route('simple-surveys.impression', 'onboarding-nps'))->assertOk();
    $this->actingAs($bob)->postJson(route('simple-surveys.open', 'onboarding-nps'))->assertOk();
    $this->actingAs($bob)->postJson(route('simple-surveys.responses.store', 'onboarding-nps'), [
        'answers' => [
            'score' => 3,
            'channel' => 'referral',
            'notes' => 'Docs helped.',
        ],
    ])->assertCreated();

    $this->actingAs($clara)->postJson(route('simple-surveys.impression', 'onboarding-nps'))->assertOk();
    $this->actingAs($clara)->postJson(route('simple-surveys.open', 'onboarding-nps'))->assertOk();
    $this->actingAs($clara)->postJson(route('simple-surveys.dismiss', 'onboarding-nps'))->assertOk();

    $summaries = SimpleSurveys::summaries();
    $byKey = $summaries->keyBy('key');

    expect($byKey->keys()->all())->toContain('onboarding-nps', 'unsynced-draft', 'archived-pulse')
        ->and($byKey['onboarding-nps']['impressions_count'])->toBe(3)
        ->and($byKey['onboarding-nps']['opens_count'])->toBe(3)
        ->and($byKey['onboarding-nps']['responses_count'])->toBe(2)
        ->and($byKey['onboarding-nps']['abandoned_count'])->toBe(1)
        ->and($byKey['onboarding-nps']['dismissals_count'])->toBe(1)
        ->and($byKey['onboarding-nps']['response_rate'])->toBe(66.7)
        ->and($byKey['onboarding-nps']['completion_rate'])->toBe(66.7)
        ->and($byKey['archived-pulse']['registered'])->toBeFalse()
        ->and($byKey['archived-pulse']['status'])->toBe(SurveyStatus::Closed);

    // Works with both Survey instance and string key, plus custom perPage
    $resultsFromObject = SimpleSurveys::results($survey, perPage: 1);
    $resultsFromKey = SimpleSurveys::results('onboarding-nps');

    expect($resultsFromObject)->not->toBeNull()
        ->and($resultsFromObject['key'])->toBe('onboarding-nps')
        ->and($resultsFromObject['responses'])->not->toBeNull()
        ->and($resultsFromObject['responses']->perPage())->toBe(1)
        ->and($resultsFromObject['responses']->total())->toBe(2)
        ->and($resultsFromKey)->not->toBeNull()
        ->and($resultsFromKey['questions'][0]['stats'])->toBe(['avg' => 4.0, 'min' => 3.0, 'max' => 5.0])
        ->and(SimpleSurveys::results('does-not-exist'))->toBeNull();

    // Results serialize cleanly to JSON for custom SPA endpoints
    $encoded = response()->json($resultsFromKey)->getContent();
    expect($encoded)->toBeString();
    $decoded = json_decode((string) $encoded, true, 512, JSON_THROW_ON_ERROR);
    expect($decoded['key'])->toBe('onboarding-nps')
        ->and($decoded['status'])->toBe('active')
        ->and($decoded['questions'][0]['type'])->toBe('rating')
        ->and($decoded['responses']['total'])->toBe(2);
});

it('closes, reopens, and resets surveys programmatically through the facade', function (): void {
    $user = User::create(['name' => 'Member', 'email' => 'member@example.com']);

    $survey = Survey::make('lifecycle-api')
        ->title('Lifecycle API')
        ->endsAt(now()->addDays(10))
        ->questions([
            Question::rating('score', 'Score')->scale(1, 5)->required(),
        ]);

    $otherSurvey = Survey::make('untouched-survey')
        ->title('Untouched Survey')
        ->questions([
            Question::text('note', 'Note')->required(),
        ]);

    SimpleSurveys::register($survey);
    SimpleSurveys::register($otherSurvey);
    SimpleSurveys::register(Survey::make('unsynced-only')->title('Unsynced Only'));

    expect(SimpleSurveys::close('unknown-survey'))->toBeNull()
        ->and(SimpleSurveys::reopen('unknown-survey'))->toBeNull()
        ->and(SimpleSurveys::reset('unknown-survey'))->toBeFalse();

    // Closing an unsynced survey syncs and marks it closed
    $closedRecord = SimpleSurveys::close($survey);
    expect($closedRecord)->not->toBeNull()
        ->and($closedRecord->status)->toBe(SurveyStatus::Closed)
        ->and($closedRecord->ends_at)->not->toBeNull()
        ->and(SimpleSurveys::isEligible('lifecycle-api', $user))->toBeFalse();

    // Reopening clears past ends_at and restores eligibility
    $reopenedRecord = SimpleSurveys::reopen('lifecycle-api');
    expect($reopenedRecord)->not->toBeNull()
        ->and($reopenedRecord->status)->toBe(SurveyStatus::Active)
        ->and(SimpleSurveys::isEligible($survey, $user))->toBeTrue();

    // Populate funnel records on both surveys
    $this->actingAs($user)->postJson(route('simple-surveys.impression', 'lifecycle-api'))->assertOk();
    $this->actingAs($user)->postJson(route('simple-surveys.open', 'lifecycle-api'))->assertOk();
    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'lifecycle-api'), [
        'answers' => ['score' => 4],
    ])->assertCreated();

    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'untouched-survey'), [
        'answers' => ['note' => 'Keep me'],
    ])->assertCreated();

    expect(SimpleSurveys::reset($survey))->toBeTrue()
        ->and(SimpleSurveys::reset('unsynced-only'))->toBeTrue()
        ->and(SurveyResponse::query()->forSurvey('lifecycle-api')->count())->toBe(0)
        ->and(SurveyAnswer::query()->count())->toBe(1)
        ->and(SurveyOpen::query()->forSurvey('lifecycle-api')->count())->toBe(0)
        ->and(SurveyImpression::query()->forSurvey('lifecycle-api')->count())->toBe(0)
        ->and(SurveyDismissal::query()->forSurvey('lifecycle-api')->count())->toBe(0)
        ->and(SurveyResponse::query()->forSurvey('untouched-survey')->count())->toBe(1)
        ->and(SimpleSurveys::isEligible('lifecycle-api', $user))->toBeTrue();
});

it('streams a csv download through the facade with optional custom filename', function (): void {
    $user = User::create(['name' => 'Exporter', 'email' => 'exporter@example.com']);

    SimpleSurveys::resolveTrackingContext(static fn (): array => ['plan' => 'pro']);

    $survey = Survey::make('facade-csv')
        ->title('Facade CSV')
        ->questions([
            Question::rating('score', 'Score')->required(),
            Question::boolean('happy', 'Happy?'),
        ]);

    SimpleSurveys::register($survey);

    // Unsynced or unknown surveys return null
    expect(SimpleSurveys::exportCsv('facade-csv'))->toBeNull()
        ->and(SimpleSurveys::exportCsv('unknown'))->toBeNull();

    app()->setLocale('fr');
    $this->actingAs($user)->postJson(route('simple-surveys.responses.store', 'facade-csv'), [
        'answers' => [
            'score' => 5,
            'happy' => true,
        ],
        'url' => '/pricing',
        'duration_ms' => 4200,
    ])->assertCreated();
    app()->setLocale('en');

    $stream = SimpleSurveys::exportCsv($survey, 'custom-export.csv');
    expect($stream)->toBeInstanceOf(StreamedResponse::class)
        ->and($stream->headers->get('content-type'))->toBe('text/csv; charset=UTF-8')
        ->and($stream->headers->get('content-disposition'))->toContain('custom-export.csv');

    ob_start();
    $stream->sendContent();
    $csv = (string) ob_get_clean();

    expect($csv)
        ->toContain('"Response ID","Respondent Type","Respondent ID","Submitted At","Page URL",Score,Happy?,"Duration (ms)",Locale,plan')
        ->toContain('/pricing,5,Yes,4200,fr,pro');
});

it('displays summaries, per-survey breakdowns, json output, and errors via simple-surveys:results command', function (): void {
    // Empty state
    $this->artisan('simple-surveys:results')
        ->expectsOutputToContain('No surveys found.')
        ->assertExitCode(0);

    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    SimpleSurveys::register(Survey::make('old-archived')->title('Old Archived'));
    SimpleSurveys::sync();
    SimpleSurveys::clear();

    SimpleSurveys::register(
        Survey::make('cli-feedback')
            ->title('CLI Feedback')
            ->questions([
                Question::rating('score', 'Rate us')->scale(1, 5)->required(),
                Question::select('source', 'Source')->options(['docs' => 'Docs', 'github' => 'GitHub']),
                Question::textarea('comments', 'Comments'),
            ])
    );

    $this->actingAs($alice)->postJson(route('simple-surveys.impression', 'cli-feedback'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.open', 'cli-feedback'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.responses.store', 'cli-feedback'), [
        'answers' => [
            'score' => 5,
            'source' => 'github',
            'comments' => 'Love the CLI output!',
        ],
    ])->assertCreated();

    $this->actingAs($bob)->postJson(route('simple-surveys.impression', 'cli-feedback'))->assertOk();
    $this->actingAs($bob)->postJson(route('simple-surveys.open', 'cli-feedback'))->assertOk();

    // Overview table
    $this->artisan('simple-surveys:results')
        ->expectsTable(
            ['Key', 'Title', 'Status', 'Seen', 'Open', 'Resp', 'Resp / Comp'],
            [
                ['cli-feedback', 'CLI Feedback', 'active', '2', '2', '1', '50% / 50%'],
                ['old-archived', 'Old Archived', 'archived', '0', '0', '0', '— / —'],
            ],
        )
        ->assertExitCode(0);

    // Single survey breakdown
    $this->artisan('simple-surveys:results', ['key' => 'cli-feedback'])
        ->expectsOutputToContain('CLI Feedback (cli-feedback) [active]')
        ->expectsTable(
            ['Seen', 'Opened', 'Responses', 'Abandoned', 'Dismissed', 'Response Rate', 'Completion Rate'],
            [['2', '2', '1', '1', '0', '50%', '50%']],
        )
        ->expectsOutputToContain('Rate us')
        ->expectsOutputToContain('Avg: 5 | Min: 5 | Max: 5')
        ->expectsOutputToContain('GitHub')
        ->expectsOutputToContain('Love the CLI output!')
        ->assertExitCode(0);

    // Missing survey returns failure
    $this->artisan('simple-surveys:results', ['key' => 'missing-survey'])
        ->expectsOutputToContain('Survey "missing-survey" not found.')
        ->assertExitCode(1);
});

it('outputs valid json when --json is passed to simple-surveys:results', function (): void {
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    SimpleSurveys::register(Survey::make('old-archived')->title('Old Archived'));
    SimpleSurveys::sync();
    SimpleSurveys::clear();

    SimpleSurveys::register(
        Survey::make('cli-feedback')
            ->title('CLI Feedback')
            ->questions([
                Question::rating('score', 'Rate us')->scale(1, 5)->required(),
                Question::select('source', 'Source')->options(['docs' => 'Docs', 'github' => 'GitHub']),
                Question::textarea('comments', 'Comments'),
            ])
    );

    $this->actingAs($alice)->postJson(route('simple-surveys.impression', 'cli-feedback'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.open', 'cli-feedback'))->assertOk();
    $this->actingAs($alice)->postJson(route('simple-surveys.responses.store', 'cli-feedback'), [
        'answers' => [
            'score' => 5,
            'source' => 'github',
            'comments' => 'Love the CLI output!',
        ],
    ])->assertCreated();

    $this->actingAs($bob)->postJson(route('simple-surveys.impression', 'cli-feedback'))->assertOk();
    $this->actingAs($bob)->postJson(route('simple-surveys.open', 'cli-feedback'))->assertOk();

    // JSON output for summaries
    $exitCode = Artisan::call('simple-surveys:results', ['--json' => true]);
    expect($exitCode)->toBe(0);
    $summariesJson = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($summariesJson)->toBeArray()
        ->and(collect($summariesJson)->pluck('key')->all())->toContain('cli-feedback', 'old-archived');

    // JSON output for single survey
    $detailExitCode = Artisan::call('simple-surveys:results', ['key' => 'cli-feedback', '--json' => true]);
    expect($detailExitCode)->toBe(0);
    $detailJson = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($detailJson['key'])->toBe('cli-feedback')
        ->and($detailJson['responses_count'])->toBe(1)
        ->and($detailJson['abandoned_count'])->toBe(1)
        ->and($detailJson['questions'])->toHaveCount(3);
});
