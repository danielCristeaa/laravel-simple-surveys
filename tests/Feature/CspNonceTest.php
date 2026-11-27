<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Facades\SimpleSurveys;
use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Services\SurveyCspNonceResolver;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\Tests\Support\User;
use Illuminate\Foundation\Vite as FoundationVite;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;

/**
 * Assert every <script> and <style> opening tag in the given HTML has the expected nonce attribute (or none).
 */
function assertInlineTagsHaveNonce(string $html, ?string $expectedNonce): void
{
    preg_match_all('/<(script|style)\b([^>]*)>/i', $html, $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty();

    foreach ($matches as $match) {
        $attributes = $match[2];

        if ($expectedNonce === null) {
            expect($attributes)->not->toContain('nonce=');
        } else {
            expect($attributes)->toContain('nonce="'.$expectedNonce.'"');
        }
    }
}

/**
 * Assert the HTML contains no inline style="..." attributes or inline DOM event handlers (e.g. onchange="...").
 */
function assertNoCspUnsafeInlineAttributes(string $html): void
{
    // Strip <script> and <style> contents so JS strings / CSS snippets are not mistaken for HTML tag attributes
    $stripped = (string) preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);

    preg_match_all('/<[a-z][^>]*\sstyle\s*=/i', $stripped, $styleMatches);
    preg_match_all('/<[a-z][^>]*\son[a-z]+\s*=/i', $stripped, $eventMatches);

    expect($styleMatches[0])->toBeEmpty()
        ->and($eventMatches[0])->toBeEmpty();
}

beforeEach(function (): void {
    Gate::define('viewSimpleSurveys', static fn (User $user): bool => $user->is_admin);
});

it('omits nonce attributes by default across widget, dynamic widget, and admin views', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);

    SimpleSurveys::register(
        Survey::make('csp-default')
            ->title('CSP Default')
            ->questions([Question::rating('score', 'Score')->scale(1, 5)])
    );

    $this->actingAs($admin);

    $widgetHtml = Blade::render('<x-simple-surveys::widget />');
    assertInlineTagsHaveNonce($widgetHtml, null);

    $dynamicHtml = Blade::render('<x-simple-surveys::widget dynamic />');
    assertInlineTagsHaveNonce($dynamicHtml, null);
    expect($dynamicHtml)->not->toContain('data-lss-nonce');

    $endpointHtml = $this->get(route('simple-surveys.widget'))->assertOk()->getContent() ?: '';
    assertInlineTagsHaveNonce($endpointHtml, null);

    $indexHtml = $this->get(route('simple-surveys.admin.index'))->assertOk()->getContent() ?: '';
    assertInlineTagsHaveNonce($indexHtml, null);

    $showHtml = $this->get(route('simple-surveys.admin.show', 'csp-default'))->assertOk()->getContent() ?: '';
    assertInlineTagsHaveNonce($showHtml, null);

    $appearanceHtml = $this->get(route('simple-surveys.admin.appearance.edit', 'csp-default'))->assertOk()->getContent() ?: '';
    assertInlineTagsHaveNonce($appearanceHtml, null);
});

it('automatically applies Vite::useCspNonce across static widget, dynamic widget, endpoint, and admin views', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);

    SimpleSurveys::register(
        Survey::make('csp-vite')
            ->title('CSP Vite')
            ->questions([Question::rating('score', 'Score')->scale(1, 5)])
    );

    Vite::useCspNonce('test-nonce');

    $this->actingAs($admin);

    $widgetHtml = Blade::render('<x-simple-surveys::widget />');
    assertInlineTagsHaveNonce($widgetHtml, 'test-nonce');
    expect($widgetHtml)
        ->toContain('<style nonce="test-nonce">')
        ->toContain('<script nonce="test-nonce">');

    $dynamicHtml = Blade::render('<x-simple-surveys::widget dynamic />');
    assertInlineTagsHaveNonce($dynamicHtml, 'test-nonce');
    expect($dynamicHtml)->toContain('data-lss-nonce="test-nonce"');

    $endpointHtml = $this->get(route('simple-surveys.widget'))->assertOk()->getContent() ?: '';
    assertInlineTagsHaveNonce($endpointHtml, 'test-nonce');

    $indexHtml = $this->get(route('simple-surveys.admin.index'))->assertOk()->getContent() ?: '';
    assertInlineTagsHaveNonce($indexHtml, 'test-nonce');

    $showHtml = $this->get(route('simple-surveys.admin.show', 'csp-vite'))->assertOk()->getContent() ?: '';
    assertInlineTagsHaveNonce($showHtml, 'test-nonce');

    $appearanceHtml = $this->get(route('simple-surveys.admin.appearance.edit', 'csp-vite'))->assertOk()->getContent() ?: '';
    assertInlineTagsHaveNonce($appearanceHtml, 'test-nonce');
});

it('resolves custom callback per request, overrides Vite, ignores blanks, and clears via SimpleSurveys::clear', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);

    SimpleSurveys::register(
        Survey::make('csp-custom')
            ->title('CSP Custom')
            ->questions([Question::text('feedback', 'Feedback')])
    );

    Vite::useCspNonce('vite-nonce');

    $dynamicNonce = '  custom-nonce  ';
    SimpleSurveys::resolveCspNonce(static function () use (&$dynamicNonce): string {
        return $dynamicNonce;
    });

    expect(SimpleSurveys::cspNonce())->toBe('custom-nonce')
        ->and(app(SurveyCspNonceResolver::class))->toBe(app(SurveyCspNonceResolver::class));

    $this->actingAs($admin);

    $widgetHtml = Blade::render('<x-simple-surveys::widget />');
    assertInlineTagsHaveNonce($widgetHtml, 'custom-nonce');

    $adminHtml = $this->get(route('simple-surveys.admin.show', 'csp-custom'))->assertOk()->getContent() ?: '';
    assertInlineTagsHaveNonce($adminHtml, 'custom-nonce');

    // Octane safety: subsequent request reads fresh value without stale singleton caching
    $dynamicNonce = 'request-2-nonce';
    $secondHtml = Blade::render('<x-simple-surveys::widget />');
    assertInlineTagsHaveNonce($secondHtml, 'request-2-nonce');

    // Whitespace-only callback falls back to Vite::cspNonce() without emitting nonce=""
    $dynamicNonce = '   ';
    $blankCallbackHtml = Blade::render('<x-simple-surveys::widget />');
    assertInlineTagsHaveNonce($blankCallbackHtml, 'vite-nonce');

    // Clearing SimpleSurveys removes the custom callback and falls back to Vite::cspNonce()
    SimpleSurveys::clear();
    SimpleSurveys::register(Survey::make('csp-custom')->title('CSP Custom'));

    expect(SimpleSurveys::cspNonce())->toBe('vite-nonce');

    $afterClearHtml = Blade::render('<x-simple-surveys::widget />');
    assertInlineTagsHaveNonce($afterClearHtml, 'vite-nonce');
});

it('prioritizes explicit widget nonce prop over custom callback and Vite::cspNonce and ignores blank props', function (): void {
    $user = User::create(['name' => 'User', 'email' => 'user@example.com']);

    SimpleSurveys::register(Survey::make('csp-explicit')->title('CSP Explicit'));

    Vite::useCspNonce('vite-nonce');
    SimpleSurveys::resolveCspNonce(static fn (): string => 'custom-nonce');

    $this->actingAs($user);

    $widgetHtml = Blade::render('<x-simple-surveys::widget nonce="explicit-nonce" />');
    assertInlineTagsHaveNonce($widgetHtml, 'explicit-nonce');

    $dynamicHtml = Blade::render('<x-simple-surveys::widget dynamic nonce="explicit-nonce" />');
    assertInlineTagsHaveNonce($dynamicHtml, 'explicit-nonce');
    expect($dynamicHtml)->toContain('data-lss-nonce="explicit-nonce"');

    // Blank explicit prop never emits nonce=""
    SimpleSurveys::clear();
    SimpleSurveys::register(Survey::make('csp-explicit')->title('CSP Explicit'));
    app()->forgetInstance(FoundationVite::class);
    Vite::clearResolvedInstance(FoundationVite::class);

    $blankHtml = Blade::render('<x-simple-surveys::widget nonce="   " />');
    expect($blankHtml)->not->toContain('nonce=""');
    assertInlineTagsHaveNonce($blankHtml, null);
});

it('preserves active page nonce metadata and script/style injection hooks in dynamic widget mode', function (): void {
    Vite::useCspNonce('spa-page-nonce');

    $guestDynamicHtml = Blade::render('<x-simple-surveys::widget dynamic />');

    assertInlineTagsHaveNonce($guestDynamicHtml, 'spa-page-nonce');
    expect($guestDynamicHtml)
        ->toContain('data-lss-dynamic-mount')
        ->toContain('data-lss-nonce="spa-page-nonce"')
        ->toContain('mount.dataset.lssNonce || hostNonce')
        ->toContain("temp.querySelectorAll('style')")
        ->toContain("temp.querySelectorAll('script')")
        ->toContain("el.setAttribute('nonce', nonce)");
});

it('emits zero inline style attributes or inline event handlers across widget and all admin views under strict CSP', function (): void {
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
    $respondent = User::create(['name' => 'Respondent', 'email' => 'respondent@example.com']);
    $viewer = User::create(['name' => 'Viewer', 'email' => 'viewer@example.com']);

    SimpleSurveys::register(
        Survey::make('csp-strict-full')
            ->title('CSP Strict Full')
            ->banner('Quick strict CSP check')
            ->questions([
                Question::rating('nps', 'How likely are you to recommend us?')->scale(0, 10)->required(),
                Question::boolean('helpful', 'Was this helpful?')->required(),
                Question::select('channel', 'Channel')->options(['docs' => 'Docs', 'github' => 'GitHub']),
                Question::textarea('notes', 'Notes'),
            ])
    );

    // Save custom appearance overrides so widget & appearance preview override CSS paths are exercised
    $this->actingAs($admin)
        ->put(route('simple-surveys.admin.appearance.update', 'csp-strict-full'), [
            'position' => 'center',
            'theme' => 'dark',
            'styles' => [
                'accent' => '#10b981',
                'background' => '#0f172a',
                'card_width' => '440px',
            ],
        ])
        ->assertRedirect();

    // Generate impressions, opens, and a localized response so all admin bars, charts, and locale dropdowns render
    $this->actingAs($respondent)
        ->postJson(route('simple-surveys.impression', 'csp-strict-full'))
        ->assertOk();

    $this->actingAs($respondent)
        ->postJson(route('simple-surveys.open', 'csp-strict-full'))
        ->assertOk();

    app()->setLocale('ro');

    $this->actingAs($respondent)
        ->postJson(route('simple-surveys.responses.store', 'csp-strict-full'), [
            'answers' => [
                'nps' => 9,
                'helpful' => true,
                'channel' => 'docs',
                'notes' => 'Zero CSP violations!',
            ],
        ])
        ->assertCreated();

    app()->setLocale('en');

    Vite::useCspNonce('strict-csp-token');

    // Static widget with custom appearance overrides
    $this->actingAs($viewer);
    $staticWidgetHtml = Blade::render('<x-simple-surveys::widget />');
    assertInlineTagsHaveNonce($staticWidgetHtml, 'strict-csp-token');
    assertNoCspUnsafeInlineAttributes($staticWidgetHtml);
    expect($staticWidgetHtml)
        ->toContain('#lss-widget-csp-strict-full[data-lss-widget]')
        ->toContain('--lss-accent: #10b981;');

    // Dynamic SPA widget & endpoint with custom appearance overrides
    $dynamicWidgetHtml = Blade::render('<x-simple-surveys::widget dynamic />');
    assertInlineTagsHaveNonce($dynamicWidgetHtml, 'strict-csp-token');
    assertNoCspUnsafeInlineAttributes($dynamicWidgetHtml);

    $endpointHtml = $this->get(route('simple-surveys.widget'))->assertOk()->getContent() ?: '';
    assertInlineTagsHaveNonce($endpointHtml, 'strict-csp-token');
    assertNoCspUnsafeInlineAttributes($endpointHtml);

    // Admin index
    $indexHtml = $this->actingAs($admin)
        ->get(route('simple-surveys.admin.index'))
        ->assertOk()
        ->getContent() ?: '';
    assertInlineTagsHaveNonce($indexHtml, 'strict-csp-token');
    assertNoCspUnsafeInlineAttributes($indexHtml);
    expect($indexHtml)
        ->toContain('data-auto-submit')
        ->toContain('[data-lss-bar="1"]{width:');

    // Admin show — Overview tab (funnel, NPS, boolean, choice bars, dropoff dots)
    $overviewHtml = $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', 'csp-strict-full'))
        ->assertOk()
        ->getContent() ?: '';
    assertInlineTagsHaveNonce($overviewHtml, 'strict-csp-token');
    assertNoCspUnsafeInlineAttributes($overviewHtml);
    expect($overviewHtml)
        ->toContain('[data-lss-bar="1"]{width:')
        ->toContain('dropoff-dot-abandoned')
        ->toContain('dropoff-dot-yes');

    // Admin show — Responses tab (locale & per_page selects + table cells)
    $responsesHtml = $this->actingAs($admin)
        ->get(route('simple-surveys.admin.show', ['surveyKey' => 'csp-strict-full', 'tab' => 'responses']))
        ->assertOk()
        ->getContent() ?: '';
    assertInlineTagsHaveNonce($responsesHtml, 'strict-csp-token');
    assertNoCspUnsafeInlineAttributes($responsesHtml);
    expect($responsesHtml)->toContain('data-auto-submit');

    // Admin appearance tab (preset swatches + saved preview overrides)
    $appearanceHtml = $this->actingAs($admin)
        ->get(route('simple-surveys.admin.appearance.edit', 'csp-strict-full'))
        ->assertOk()
        ->getContent() ?: '';
    assertInlineTagsHaveNonce($appearanceHtml, 'strict-csp-token');
    assertNoCspUnsafeInlineAttributes($appearanceHtml);
    expect($appearanceHtml)
        ->toContain('preset-dot-indigo')
        ->toContain('id="lss-preview-overrides"')
        ->toContain('#lss-preview-root { --lss-accent: #10b981;');
});
