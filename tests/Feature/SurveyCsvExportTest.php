<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyAnswer;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Models\SurveyResponse;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::define('viewSimpleSurveys', static fn (User $user): bool => $user->is_admin);
});

/**
 * Parse a streamed CSV string (verifying and stripping the UTF-8 BOM) into rows.
 *
 * @return list<list<string|null>>
 */
function parseExportedCsv(string $csv): array
{
    expect($csv)->toStartWith("\xEF\xBB\xBF");

    $withoutBom = substr($csv, 3);
    $stream = fopen('php://temp', 'r+');
    expect($stream)->not->toBeFalse();

    fwrite($stream, $withoutBom);
    rewind($stream);

    $rows = [];
    while (($row = fgetcsv($stream)) !== false) {
        $rows[] = $row;
    }

    fclose($stream);

    return $rows;
}

it('prepends a UTF-8 BOM and preserves accented and Unicode text cleanly', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $user = User::create(['name' => 'Renée', 'email' => 'renee@example.com']);

    SimpleSurveys::register(
        Survey::make('unicode-csv')
            ->title('Unicode CSV')
            ->questions([
                Question::text('feedback', 'Café Feedback')->required(),
                Question::textarea('notes', 'Übersicht'),
            ])
    );

    $this->actingAs($user)
        ->postJson(route('simple-surveys.responses.store', 'unicode-csv'), [
            'answers' => [
                'feedback' => 'Crème brûlée — 日本語',
                'notes' => 'Très naïve 🚀 €100',
            ],
            'url' => '/fr/café',
        ])
        ->assertCreated();

    $csv = $this->actingAs($admin)
        ->get(route('simple-surveys.admin.export', 'unicode-csv'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    $rows = parseExportedCsv($csv);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe([
            'Response ID',
            'Respondent Type',
            'Respondent ID',
            'Submitted At',
            'Page URL',
            'Café Feedback',
            'Übersicht',
            'Locale',
        ])
        ->and($rows[1][4])->toBe('/fr/café')
        ->and($rows[1][5])->toBe('Crème brûlée — 日本語')
        ->and($rows[1][6])->toBe('Très naïve 🚀 €100')
        ->and($rows[1][7])->toBe('en');
});

it('neutralizes formula prefixes submitted via HTTP in answers, urls, tracking, and headers', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    SimpleSurveys::resolveTrackingContext(static fn ($request, User $user): array => $user->is($alice)
        ? ['=bad_key' => '+cmd|\' /C calc\'!A0', '@source' => '-1+1']
        : ['=bad_key' => '@SUM(1+1)*cmd|\' /C calc\'!A0', '@source' => '--5']);

    SimpleSurveys::register(
        Survey::make('formula-http-csv')
            ->title('Formula HTTP CSV')
            ->questions([
                Question::text('short', '=Label'),
                Question::textarea('long', '+Notes'),
                Question::checkbox('tags', '@Tags')->options([
                    '=evil' => 'Evil',
                    'safe' => 'Safe',
                ]),
            ])
    );

    $this->actingAs($alice)
        ->postJson(route('simple-surveys.responses.store', 'formula-http-csv'), [
            'answers' => [
                'short' => '=HYPERLINK("https://evil.test","Click")',
                'long' => '+cmd|\' /C calc\'!A0',
                'tags' => ['=evil', 'safe'],
            ],
            'url' => '=HYPERLINK("https://evil.test","URL")',
        ])
        ->assertCreated();

    $this->actingAs($bob)
        ->postJson(route('simple-surveys.responses.store', 'formula-http-csv'), [
            'answers' => [
                'short' => '-1+1',
                'long' => '@SUM(1+1)*cmd|\' /C calc\'!A0',
                'tags' => ['safe'],
            ],
            'url' => '-SUM(A1:A2)',
        ])
        ->assertCreated();

    $csv = $this->actingAs($admin)
        ->get(route('simple-surveys.admin.export', 'formula-http-csv'))
        ->assertOk()
        ->streamedContent();

    $rows = parseExportedCsv($csv);

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toBe([
            'Response ID',
            'Respondent Type',
            'Respondent ID',
            'Submitted At',
            'Page URL',
            "'=Label",
            "'+Notes",
            "'@Tags",
            'Locale',
            "'=bad_key",
            "'@source",
        ])
        ->and($rows[1][4])->toBe('\'=HYPERLINK("https://evil.test","URL")')
        ->and($rows[1][5])->toBe('\'=HYPERLINK("https://evil.test","Click")')
        ->and($rows[1][6])->toBe('\'+cmd|\' /C calc\'!A0')
        ->and($rows[1][7])->toBe("'=evil, safe")
        ->and($rows[1][9])->toBe('\'+cmd|\' /C calc\'!A0')
        ->and($rows[1][10])->toBe("'-1+1")
        ->and($rows[2][4])->toBe("'-SUM(A1:A2)")
        ->and($rows[2][5])->toBe("'-1+1")
        ->and($rows[2][6])->toBe('\'@SUM(1+1)*cmd|\' /C calc\'!A0')
        ->and($rows[2][7])->toBe('safe')
        ->and($rows[2][9])->toBe('\'@SUM(1+1)*cmd|\' /C calc\'!A0')
        ->and($rows[2][10])->toBe("'--5");
});

it('neutralizes leading tab and carriage return formula payloads stored in responses and headers', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $user = User::create(['name' => 'ControlChar', 'email' => 'ctrl@example.com']);

    SimpleSurveys::register(
        Survey::make('control-char-csv')
            ->title('Control Char CSV')
            ->questions([
                Question::text('tab_q', "\t=TabHeader"),
                Question::textarea('cr_q', "\r+CrHeader"),
            ])
    );

    SimpleSurveys::sync();
    $record = SurveyRecord::query()->byKey('control-char-csv')->with('questions')->firstOrFail();
    $questionsByKey = $record->questions->keyBy('key');

    $response = SurveyResponse::query()->create([
        'survey_id' => $record->getKey(),
        'respondent_type' => $user->getMorphClass(),
        'respondent_id' => $user->getKey(),
        'metadata' => [
            'url' => "\t=HYPERLINK(\"https://evil.test\",\"TabURL\")",
            'locale' => "\ren",
            'tracking' => [
                "\ttrack" => "\t=1+1",
                "\rtrack" => "\r=1+1",
            ],
        ],
    ]);

    SurveyAnswer::query()->create([
        'response_id' => $response->getKey(),
        'question_id' => $questionsByKey['tab_q']->getKey(),
        'value' => "\t=1",
    ]);

    SurveyAnswer::query()->create([
        'response_id' => $response->getKey(),
        'question_id' => $questionsByKey['cr_q']->getKey(),
        'value' => "\r=1",
    ]);

    $csv = $this->actingAs($admin)
        ->get(route('simple-surveys.admin.export', 'control-char-csv'))
        ->assertOk()
        ->streamedContent();

    $rows = parseExportedCsv($csv);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe([
            'Response ID',
            'Respondent Type',
            'Respondent ID',
            'Submitted At',
            'Page URL',
            "'\t=TabHeader",
            "'\r+CrHeader",
            'Locale',
            "'\ttrack",
            "'\rtrack",
        ])
        ->and($rows[1][4])->toBe("'\t=HYPERLINK(\"https://evil.test\",\"TabURL\")")
        ->and($rows[1][5])->toBe("'\t=1")
        ->and($rows[1][6])->toBe("'\r=1")
        ->and($rows[1][7])->toBe('en')
        ->and($rows[1][8])->toBe("'\t=1+1")
        ->and($rows[1][9])->toBe("'\r=1+1");
});

it('preserves legitimate negative numbers while still neutralizing non-numeric minus strings', function (): void {
    $user = User::create(['name' => 'Numeric', 'email' => 'numeric@example.com']);

    SimpleSurveys::resolveTrackingContext(static fn (): array => [
        'int_delta' => -5,
        'float_delta' => -3.14,
        'string_neg_int' => '-42',
        'string_neg_float' => '-0.25',
        'string_formula' => '-2+3',
        'positive_plus' => '+42',
    ]);

    SimpleSurveys::register(
        Survey::make('numeric-csv')
            ->title('Numeric CSV')
            ->questions([
                Question::rating('nps', 'NPS')->scale(-10, 10)->required(),
                Question::number('temp', 'Temperature')->min(-100)->max(100)->required(),
                Question::text('note', 'Note')->required(),
            ])
    );

    $this->actingAs($user)
        ->postJson(route('simple-surveys.responses.store', 'numeric-csv'), [
            'answers' => [
                'nps' => -5,
                'temp' => -3.14,
                'note' => '-1+1',
            ],
            'url' => '/sensors',
        ])
        ->assertCreated();

    $stream = SimpleSurveys::exportCsv('numeric-csv');
    expect($stream)->not->toBeNull();

    ob_start();
    $stream?->sendContent();
    $csv = (string) ob_get_clean();

    $rows = parseExportedCsv($csv);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe([
            'Response ID',
            'Respondent Type',
            'Respondent ID',
            'Submitted At',
            'Page URL',
            'NPS',
            'Temperature',
            'Note',
            'Locale',
            'float_delta',
            'int_delta',
            'positive_plus',
            'string_formula',
            'string_neg_float',
            'string_neg_int',
        ])
        ->and($rows[1][5])->toBe('-5')
        ->and($rows[1][6])->toBe('-3.14')
        ->and($rows[1][7])->toBe("'-1+1")
        ->and($rows[1][9])->toBe('-3.14')
        ->and($rows[1][10])->toBe('-5')
        ->and($rows[1][11])->toBe("'+42")
        ->and($rows[1][12])->toBe("'-2+3")
        ->and($rows[1][13])->toBe('-0.25')
        ->and($rows[1][14])->toBe('-42');
});
