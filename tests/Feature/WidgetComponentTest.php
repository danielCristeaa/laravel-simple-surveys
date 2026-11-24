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

it('renders nothing for unauthenticated visitors', function (): void {
    SimpleSurveys::register(Survey::make('guest-test')->title('Guest Test'));

    $html = trim(Blade::render('<x-simple-surveys::widget />'));

    expect($html)->toBe('');
});

it('renders nothing when no survey is eligible for the authenticated user', function (): void {
    $user = User::create(['name' => 'Free User', 'email' => 'free@example.com', 'plan' => 'free']);

    SimpleSurveys::register(
        Survey::make('pro-only')->target(fn (Builder $q) => $q->where('plan', 'pro'))
    );

    $this->actingAs($user);

    expect(trim(Blade::render('<x-simple-surveys::widget />')))->toBe('');
});

it('renders the bottom-right widget with all question types for an eligible user', function (): void {
    $user = User::create(['name' => 'Pro User', 'email' => 'pro@example.com', 'plan' => 'pro']);

    SimpleSurveys::register(
        Survey::make('product-survey')
            ->title('Product Feedback')
            ->banner('Got 30 seconds? Share feedback!')
            ->description('Help us build better features.')
            ->submitLabel('Send Feedback')
            ->thankYouMessage('You rock!')
            ->questions([
                Question::text('role', 'Your role')->placeholder('Engineer')->required(),
                Question::textarea('notes', 'Additional notes')->maxLength(500),
                Question::number('team_size', 'Team size')->min(1)->max(100),
                Question::select('source', 'How did you find us?')->options(['google' => 'Google', 'friend' => 'Friend']),
                Question::radio('frequency', 'Usage frequency')->options(['Daily', 'Weekly']),
                Question::checkbox('features', 'Favorite features')->options(['api' => 'API', 'ui' => 'UI']),
                Question::rating('score', 'Overall score')->scale(1, 5)->required(),
                Question::boolean('recommend', 'Would you recommend us?'),
            ])
    );

    $this->actingAs($user);

    $html = Blade::render('<x-simple-surveys::widget />');

    expect($html)
        ->toContain('lss-pos-bottom-right')
        ->toContain('wire:ignore')
        ->toContain('banner.remove()')
        ->toContain('Got 30 seconds? Share feedback!')
        ->toContain('Product Feedback')
        ->toContain('Help us build better features.')
        ->toContain('Send Feedback')
        ->toContain("Don't show again")
        ->toContain('data-lss-dismiss')
        ->toContain(route('simple-surveys.responses.store', 'product-survey'))
        ->toContain(route('simple-surveys.dismiss', 'product-survey'))
        ->toContain(route('simple-surveys.open', 'product-survey'))
        ->toContain('name="answers[role]"')
        ->toContain('name="answers[notes]"')
        ->toContain('name="answers[team_size]"')
        ->toContain('name="answers[source]"')
        ->toContain('name="answers[frequency]"')
        ->toContain('name="answers[features][]"')
        ->toContain('name="answers[score]"')
        ->toContain('name="answers[recommend]"');
});

it('supports specific survey attribute, bottom-left position, and non-dismissible mode', function (): void {
    $user = User::create(['name' => 'Sam', 'email' => 'sam@example.com']);

    SimpleSurveys::register(Survey::make('high-priority')->priority(50)->title('High Priority'));
    SimpleSurveys::register(Survey::make('specific-one')->priority(1)->title('Specific Survey')->dismissible(false));

    $this->actingAs($user);

    $html = Blade::render('<x-simple-surveys::widget survey="specific-one" position="bottom-left" />');

    expect($html)
        ->toContain('lss-pos-bottom-left')
        ->toContain('Specific Survey')
        ->not->toContain('High Priority')
        ->not->toContain('aria-label="Dismiss survey"')
        ->not->toContain("Don't show again");

    SimpleSurveys::sync();
    $record = SurveyRecord::query()->byKey('specific-one')->firstOrFail();

    SurveyResponse::query()->create([
        'survey_id' => $record->id,
        'respondent_type' => $user->getMorphClass(),
        'respondent_id' => $user->id,
    ]);

    expect(trim(Blade::render('<x-simple-surveys::widget survey="specific-one" />')))->toBe('');
});

it('renders Remind me later label when reprompt cooldown is configured', function (): void {
    $user = User::create(['name' => 'Maya', 'email' => 'maya@example.com']);

    SimpleSurveys::register(
        Survey::make('reprompt-widget')->repromptAfterDays(7)
    );

    $this->actingAs($user);

    $html = Blade::render('<x-simple-surveys::widget />');

    expect($html)
        ->toContain('Remind me later')
        ->not->toContain("Don't show again");
});
