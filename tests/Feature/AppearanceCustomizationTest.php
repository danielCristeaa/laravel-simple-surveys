<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Models\SurveyRecord;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::define('viewSimpleSurveys', static fn (User $user): bool => $user->is_admin);
});

it('renders survey hook attributes, theme and placement presets, and zero-specificity defaults', function (): void {
    $user = User::create(['name' => 'Maya', 'email' => 'maya@example.com']);

    SimpleSurveys::register(
        Survey::make('onboarding-feedback')
            ->title('Onboarding Feedback')
            ->questions([Question::rating('score', 'Score')->required()])
    );

    $this->actingAs($user);

    $defaultHtml = Blade::render('<x-simple-surveys::widget />');

    expect($defaultHtml)
        ->toContain('data-lss-survey="onboarding-feedback"')
        ->toContain('data-lss-position="bottom-right"')
        ->toContain('data-lss-theme="light"')
        ->toContain('lss-pos-bottom-right')
        ->toContain('data-lss-backdrop')
        ->toContain(':where(.lss-root)')
        ->not->toContain('style="--lss-');

    $presetHtml = Blade::render('<x-simple-surveys::widget position="center" theme="dark" />');

    expect($presetHtml)
        ->toContain('lss-pos-center')
        ->toContain('data-lss-position="center"')
        ->toContain('data-lss-theme="dark"');

    $topLeftAutoHtml = Blade::render('<x-simple-surveys::widget position="top-left" theme="auto" />');

    expect($topLeftAutoHtml)
        ->toContain('lss-pos-top-left')
        ->toContain('data-lss-position="top-left"')
        ->toContain('data-lss-theme="auto"');
});

it('enforces authorization and 404 handling on admin appearance routes', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $regular = User::create(['name' => 'Regular', 'email' => 'regular@example.com', 'is_admin' => false]);

    // Archived (unregistered) survey in DB
    SimpleSurveys::register(Survey::make('archived-survey')->title('Archived Survey'));
    SimpleSurveys::sync();
    SimpleSurveys::clear();

    SimpleSurveys::register(Survey::make('live-survey')->title('Live Survey'));

    $this->getJson(route('simple-surveys.admin.appearance.edit', 'live-survey'))
        ->assertUnauthorized();

    $this->actingAs($regular)
        ->get(route('simple-surveys.admin.appearance.edit', 'live-survey'))
        ->assertForbidden();

    $this->actingAs($regular)
        ->put(route('simple-surveys.admin.appearance.update', 'live-survey'), ['theme' => 'dark'])
        ->assertForbidden();

    $this->actingAs($regular)
        ->delete(route('simple-surveys.admin.appearance.reset', 'live-survey'))
        ->assertForbidden();

    // Unknown and archived surveys return 404
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.appearance.edit', 'missing-survey'))
        ->assertNotFound();

    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.appearance.edit', 'archived-survey'))
        ->assertNotFound();

    // Archived survey results page does not show Appearance tab
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'archived-survey'))
        ->assertOk()
        ->assertDontSee(route('simple-surveys.admin.appearance.edit', 'archived-survey'));
});

it('lets admins customize survey appearance with live preview, applies it everywhere, preserves across sync, and resets cleanly', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $customer = User::create(['name' => 'Customer', 'email' => 'customer@example.com', 'is_admin' => false]);

    SimpleSurveys::register(
        Survey::make('nps-pulse')
            ->title('NPS Pulse')
            ->banner('Quick 1-question pulse check')
            ->questions([
                Question::rating('score', 'How likely are you to recommend us?')->scale(1, 5)->required(),
            ])
    );

    // Results page links to the Appearance tab
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'nps-pulse'))
        ->assertOk()
        ->assertSee('Results &amp; Metrics', false)
        ->assertSee('Appearance')
        ->assertSee(route('simple-surveys.admin.appearance.edit', 'nps-pulse'));

    // Viewing Appearance tab does not force-sync the survey record
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.appearance.edit', 'nps-pulse'))
        ->assertOk()
        ->assertSee('Live Preview')
        ->assertSee('Presets &amp; Placement', false)
        ->assertSee('[data-lss-survey="nps-pulse"]', false)
        ->assertDontSee('Reset to Defaults');

    expect(SurveyRecord::query()->byKey('nps-pulse')->exists())->toBeFalse();

    // Save custom appearance from Dashboard
    $this->actingAs($admin)
        ->put(route('simple-surveys.admin.appearance.update', 'nps-pulse'), [
            'position' => 'center',
            'theme' => 'dark',
            'styles' => [
                'accent' => '#10b981',
                'background' => '#111827',
                'card_width' => '460px',
                'card_max_height' => '70vh',
                'radius' => '16px',
                'font_size' => '15px',
                'muted' => '', // Blank field must stay inherited (omitted)
            ],
        ])
        ->assertRedirect(route('simple-surveys.admin.appearance.edit', 'nps-pulse'))
        ->assertSessionHas('simple_surveys_admin_status');

    // Re-syncing from code must preserve saved dashboard appearance
    SimpleSurveys::sync();

    $record = SurveyRecord::query()->byKey('nps-pulse')->firstOrFail();
    expect($record->appearance)->toBe([
        'position' => 'center',
        'theme' => 'dark',
        'styles' => [
            'accent' => '#10b981',
            'background' => '#111827',
            'card_width' => '460px',
            'card_max_height' => '70vh',
            'radius' => '16px',
            'font_size' => '15px',
        ],
    ]);

    // Appearance tab now shows Reset to Defaults button
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.appearance.edit', 'nps-pulse'))
        ->assertOk()
        ->assertSee('Reset to Defaults');

    // Blade widget applies saved overrides (overriding component tag defaults)
    $this->actingAs($customer);
    $bladeHtml = Blade::render('<x-simple-surveys::widget position="bottom-left" theme="light" />');

    expect($bladeHtml)
        ->toContain('lss-pos-center')
        ->toContain('data-lss-position="center"')
        ->toContain('data-lss-theme="dark"')
        ->toContain('--lss-accent: #10b981;')
        ->toContain('--lss-bg: #111827;')
        ->toContain('--lss-card-width: 460px;')
        ->toContain('--lss-card-max-height: 70vh;')
        ->toContain('--lss-radius: 16px;')
        ->toContain('--lss-font-size: 15px;')
        ->not->toContain('--lss-muted:');

    // Dynamic SPA widget endpoint applies identical saved overrides
    $this->actingAs($customer)
        ->get(route('simple-surveys.widget', ['position' => 'bottom-left', 'theme' => 'light']))
        ->assertOk()
        ->assertSee('lss-pos-center')
        ->assertSee('data-lss-theme="dark"', false)
        ->assertSee('--lss-accent: #10b981;', false)
        ->assertSee('--lss-card-width: 460px;', false);

    // Headless JSON API returns resolved appearance + explicit style overrides
    $this->actingAs($customer)
        ->getJson(route('simple-surveys.eligible'))
        ->assertOk()
        ->assertJsonPath('survey.appearance', [
            'position' => 'center',
            'theme' => 'dark',
            'icon' => [
                'type' => 'preset',
                'preset' => 'chat',
                'url' => null,
            ],
            'styles' => [
                'accent' => '#10b981',
                'background' => '#111827',
                'card_width' => '460px',
                'card_max_height' => '70vh',
                'radius' => '16px',
                'font_size' => '15px',
            ],
        ]);

    // Admin resets appearance back to defaults
    $this->actingAs($admin)
        ->delete(route('simple-surveys.admin.appearance.reset', 'nps-pulse'))
        ->assertRedirect(route('simple-surveys.admin.appearance.edit', 'nps-pulse'))
        ->assertSessionHas('simple_surveys_admin_status');

    expect($record->fresh()?->appearance)->toBeNull();

    // Widget returns to inheriting component defaults with no inline style overrides
    $this->actingAs($customer);
    $resetHtml = Blade::render('<x-simple-surveys::widget position="bottom-left" theme="light" />');

    expect($resetHtml)
        ->toContain('lss-pos-bottom-left')
        ->toContain('data-lss-theme="light"')
        ->not->toContain('style="--lss-');

    $this->actingAs($customer)
        ->getJson(route('simple-surveys.eligible'))
        ->assertOk()
        ->assertJsonPath('survey.appearance', [
            'position' => 'bottom-right',
            'theme' => 'light',
            'icon' => [
                'type' => 'preset',
                'preset' => 'chat',
                'url' => null,
            ],
            'styles' => [],
        ]);
});

it('validates appearance inputs strictly and blocks css injection attempts', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);

    SimpleSurveys::register(Survey::make('strict-validation')->title('Strict Validation'));

    $this->actingAs($admin)
        ->from(route('simple-surveys.admin.appearance.edit', 'strict-validation'))
        ->put(route('simple-surveys.admin.appearance.update', 'strict-validation'), [
            'position' => 'invalid-corner',
            'theme' => 'neon',
            'styles' => [
                'accent' => 'red; background: url(evil)',
                'card_width' => '500px; position: absolute',
                'font_family' => 'url(https://evil.example/a.css)',
            ],
        ])
        ->assertRedirect(route('simple-surveys.admin.appearance.edit', 'strict-validation'))
        ->assertSessionHasErrors([
            'position',
            'theme',
            'styles.accent',
            'styles.card_width',
            'styles.font_family',
        ]);
});

it('hides the appearance tab and returns 404 when admin.appearance_editor is disabled', function (): void {
    config()->set('simple-surveys.admin.appearance_editor', false);

    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);

    SimpleSurveys::register(Survey::make('locked-styles')->title('Locked Styles'));

    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'locked-styles'))
        ->assertOk()
        ->assertDontSee(route('simple-surveys.admin.appearance.edit', 'locked-styles'));

    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.appearance.edit', 'locked-styles'))
        ->assertNotFound();

    $this->actingAs($admin)
        ->put(route('simple-surveys.admin.appearance.update', 'locked-styles'), ['theme' => 'dark'])
        ->assertNotFound();

    $this->actingAs($admin)
        ->delete(route('simple-surveys.admin.appearance.reset', 'locked-styles'))
        ->assertNotFound();
});

it('passes theme and icon attributes to dynamic widget endpoint url only when explicitly provided and keeps guest requests zero-query', function (): void {
    SimpleSurveys::register(Survey::make('theme-spa')->title('Theme SPA'));

    DB::enableQueryLog();
    DB::flushQueryLog();

    $htmlWithThemeAndIcon = Blade::render('<x-simple-surveys::widget dynamic position="top-right" theme="dark" icon="sparkles" />');

    expect($htmlWithThemeAndIcon)
        ->toContain(e(route('simple-surveys.widget', ['position' => 'top-right', 'theme' => 'dark', 'icon' => 'sparkles'])))
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('supports banner icon customization across global config, Blade prop, fluent API, Admin dashboard, SPA widget, and eligible JSON', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $customer = User::create(['name' => 'Customer', 'email' => 'customer@example.com', 'is_admin' => false]);

    // 1. Global config default applies when no survey/Blade/dashboard override is set
    config()->set('simple-surveys.widget.icon', 'megaphone');

    SimpleSurveys::register(
        Survey::make('icon-survey')
            ->title('Icon Survey')
            ->questions([Question::rating('score', 'Score')->required()])
    );

    $this->actingAs($customer);

    expect(Blade::render('<x-simple-surveys::widget />'))
        ->toContain('data-lss-icon-type="preset"')
        ->toContain('data-lss-icon="megaphone"');

    $this->actingAs($customer)
        ->getJson(route('simple-surveys.eligible'))
        ->assertOk()
        ->assertJsonPath('survey.appearance.icon', [
            'type' => 'preset',
            'preset' => 'megaphone',
            'url' => null,
        ]);

    // 2. Blade component prop overrides global config
    expect(Blade::render('<x-simple-surveys::widget icon="star" />'))
        ->toContain('data-lss-icon-type="preset"')
        ->toContain('data-lss-icon="star"');

    expect(Blade::render('<x-simple-surveys::widget icon="none" />'))
        ->not->toContain('class="lss-banner-icon"');

    expect(Blade::render('<x-simple-surveys::widget icon="/images/custom-badge.png" />'))
        ->toContain('data-lss-icon-type="asset"')
        ->toContain('class="lss-banner-icon-img"')
        ->toContain('src="'.asset('images/custom-badge.png').'"');

    // 3. Fluent per-survey Survey::icon() overrides Blade prop & global config
    SimpleSurveys::clear();
    SimpleSurveys::register(
        Survey::make('icon-survey')
            ->title('Icon Survey')
            ->icon('lightbulb')
            ->questions([Question::rating('score', 'Score')->required()])
    );

    expect(Blade::render('<x-simple-surveys::widget icon="star" />'))
        ->toContain('data-lss-icon="lightbulb"');

    $this->actingAs($customer)
        ->getJson(route('simple-surveys.eligible'))
        ->assertOk()
        ->assertJsonPath('survey.appearance.icon', [
            'type' => 'preset',
            'preset' => 'lightbulb',
            'url' => null,
        ]);

    // 4. Admin Dashboard Appearance tab shows picker and saves preset override (highest precedence)
    $this->actingAs($admin)
        ->get(route('simple-surveys.admin.appearance.edit', 'icon-survey'))
        ->assertOk()
        ->assertSee('Banner Icon')
        ->assertSee('Inherit (Lightbulb)')
        ->assertSee('data-icon-preset="sparkles"', false);

    $this->actingAs($admin)
        ->put(route('simple-surveys.admin.appearance.update', 'icon-survey'), [
            'icon' => 'sparkles',
        ])
        ->assertRedirect(route('simple-surveys.admin.appearance.edit', 'icon-survey'));

    SimpleSurveys::sync();

    expect(SurveyRecord::query()->byKey('icon-survey')->firstOrFail()->appearance)
        ->toBe(['icon' => 'sparkles']);

    $this->actingAs($customer);
    expect(Blade::render('<x-simple-surveys::widget icon="star" />'))
        ->toContain('data-lss-icon="sparkles"');

    $this->actingAs($customer)
        ->get(route('simple-surveys.widget', ['icon' => 'star']))
        ->assertOk()
        ->assertSee('data-lss-icon="sparkles"', false);

    $this->actingAs($customer)
        ->getJson(route('simple-surveys.eligible'))
        ->assertOk()
        ->assertJsonPath('survey.appearance.icon', [
            'type' => 'preset',
            'preset' => 'sparkles',
            'url' => null,
        ]);

    // 5. Admin Dashboard saves custom .svg asset path
    $this->actingAs($admin)
        ->put(route('simple-surveys.admin.appearance.update', 'icon-survey'), [
            'icon' => '/images/brand/survey-mark.svg',
        ])
        ->assertRedirect(route('simple-surveys.admin.appearance.edit', 'icon-survey'));

    expect(SurveyRecord::query()->byKey('icon-survey')->firstOrFail()->appearance)
        ->toBe(['icon' => 'images/brand/survey-mark.svg']);

    $this->actingAs($customer);
    expect(Blade::render('<x-simple-surveys::widget />'))
        ->toContain('data-lss-icon-type="asset"')
        ->toContain('src="'.asset('images/brand/survey-mark.svg').'"');

    $this->actingAs($customer)
        ->getJson(route('simple-surveys.eligible'))
        ->assertOk()
        ->assertJsonPath('survey.appearance.icon', [
            'type' => 'asset',
            'preset' => null,
            'url' => asset('images/brand/survey-mark.svg'),
        ]);

    // 6. Admin Dashboard hides icon completely ("none")
    $this->actingAs($admin)
        ->put(route('simple-surveys.admin.appearance.update', 'icon-survey'), [
            'icon' => 'none',
        ])
        ->assertRedirect(route('simple-surveys.admin.appearance.edit', 'icon-survey'));

    $this->actingAs($customer);
    expect(Blade::render('<x-simple-surveys::widget />'))
        ->not->toContain('class="lss-banner-icon"');

    $this->actingAs($customer)
        ->get(route('simple-surveys.widget'))
        ->assertOk()
        ->assertDontSee('class="lss-banner-icon"', false);

    $this->actingAs($customer)
        ->getJson(route('simple-surveys.eligible'))
        ->assertOk()
        ->assertJsonPath('survey.appearance.icon', [
            'type' => 'none',
            'preset' => null,
            'url' => null,
        ]);

    // 7. Reset to Defaults clears icon override and falls back to Survey::icon()
    $this->actingAs($admin)
        ->delete(route('simple-surveys.admin.appearance.reset', 'icon-survey'))
        ->assertRedirect(route('simple-surveys.admin.appearance.edit', 'icon-survey'));

    $this->actingAs($customer)
        ->getJson(route('simple-surveys.eligible'))
        ->assertOk()
        ->assertJsonPath('survey.appearance.icon', [
            'type' => 'preset',
            'preset' => 'lightbulb',
            'url' => null,
        ]);
});

it('rejects invalid banner icon values on admin appearance update', function (string $invalidIcon): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);

    SimpleSurveys::register(Survey::make('icon-validation')->title('Icon Validation'));

    $this->actingAs($admin)
        ->from(route('simple-surveys.admin.appearance.edit', 'icon-validation'))
        ->put(route('simple-surveys.admin.appearance.update', 'icon-validation'), [
            'icon' => $invalidIcon,
        ])
        ->assertRedirect(route('simple-surveys.admin.appearance.edit', 'icon-validation'))
        ->assertSessionHasErrors(['icon']);
})->with([
    'unknown preset' => 'not-a-preset',
    'external url' => 'https://evil.test/icon.svg',
    'protocol relative' => '//evil.test/icon.png',
    'path traversal' => '../secret.svg',
    'unsupported ext' => 'images/photo.jpg',
    'script injection' => 'images/icon.svg" onerror="alert(1)',
]);
