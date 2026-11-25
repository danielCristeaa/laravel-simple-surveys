<?php

declare(strict_types=1);

use DanielCristea\SimpleSurveys\Enums\WidgetPosition;
use DanielCristea\SimpleSurveys\Enums\WidgetTheme;
use DanielCristea\SimpleSurveys\Services\WidgetStyles;
use DanielCristea\SimpleSurveys\SurveyAppearance;

it('hydrates valid appearance presets and style tokens while ignoring invalid values', function (): void {
    $appearance = SurveyAppearance::fromArray([
        'position' => 'center',
        'theme' => 'dark',
        'styles' => [
            'accent' => ' #10B981 ',
            'background' => '#0f172a',
            'card_width' => '440px',
            'card_max_height' => '75vh',
            'radius' => '1rem',
            'font_family' => '"Inter", sans-serif',
            // Invalid values that must be ignored:
            'border' => 'red; } body { display: none }',
            'button_radius' => 'calc(10px + 2px); color: red',
            'text' => 'javascript:alert(1)',
            'muted' => '',
            'unknown_token' => '#000000',
        ],
    ]);

    expect($appearance->isEmpty())->toBeFalse()
        ->and($appearance->position)->toBe(WidgetPosition::Center)
        ->and($appearance->theme)->toBe(WidgetTheme::Dark)
        ->and($appearance->styles)->toBe([
            'accent' => '#10b981',
            'background' => '#0f172a',
            'card_width' => '440px',
            'card_max_height' => '75vh',
            'radius' => '1rem',
            'font_family' => '"Inter", sans-serif',
        ])
        ->and($appearance->toInlineStyle())
        ->toBe('--lss-accent: #10b981; --lss-bg: #0f172a; --lss-card-width: 440px; --lss-card-max-height: 75vh; --lss-radius: 1rem; --lss-font-family: "Inter", sans-serif;')
        ->and($appearance->toStorageArray())->toBe([
            'position' => 'center',
            'theme' => 'dark',
            'styles' => [
                'accent' => '#10b981',
                'background' => '#0f172a',
                'card_width' => '440px',
                'card_max_height' => '75vh',
                'radius' => '1rem',
                'font_family' => '"Inter", sans-serif',
            ],
        ]);
});

it('returns empty storage payload and clean defaults when uncustomized', function (): void {
    $empty = SurveyAppearance::fromArray(null);

    expect($empty->isEmpty())->toBeTrue()
        ->and($empty->toInlineStyle())->toBe('')
        ->and($empty->toStorageArray())->toBeNull()
        ->and($empty->resolvedPosition())->toBe(WidgetPosition::BottomRight)
        ->and($empty->resolvedPosition(WidgetPosition::TopLeft))->toBe(WidgetPosition::TopLeft)
        ->and($empty->resolvedTheme())->toBe(WidgetTheme::Light)
        ->and($empty->resolvedTheme(WidgetTheme::Auto))->toBe(WidgetTheme::Auto)
        ->and($empty->toArray())->toBe([
            'position' => 'bottom-right',
            'theme' => 'light',
            'styles' => [],
        ]);
});

it('rejects dangerous font_family strings during normalization', function (): void {
    expect(SurveyAppearance::normalizeTokenValue('font_family', 'url(https://evil.test/x.css)'))->toBeNull()
        ->and(SurveyAppearance::normalizeTokenValue('font_family', 'expression(alert(1))'))->toBeNull()
        ->and(SurveyAppearance::normalizeTokenValue('font_family', 'Arial; background: red'))->toBeNull()
        ->and(SurveyAppearance::normalizeTokenValue('font_family', 'Inter, sans-serif'))->toBe('Inter, sans-serif');
});

it('keeps widget.css backwards compatible with every token in SurveyAppearance and simple-surveys.css', function (): void {
    $widgetCss = WidgetStyles::css();
    $starterPath = dirname(__DIR__, 2).'/resources/css/simple-surveys.css';
    $starterCss = file_get_contents($starterPath);

    expect($widgetCss)->not->toBe('')
        ->and($starterCss)->toBeString()
        ->and($widgetCss)->toContain(':where(.lss-root)')
        ->and($widgetCss)->toContain(':where(.lss-root[data-lss-theme="dark"])')
        ->and($widgetCss)->toContain(':where(.lss-root[data-lss-theme="auto"])');

    // Every PHP token CSS variable must be consumed in widget.css and documented in simple-surveys.css
    foreach (SurveyAppearance::STYLE_TOKENS as $meta) {
        $cssVar = $meta['css_var'];
        expect($widgetCss)->toContain('var('.$cssVar.',')
            ->and($starterCss)->toContain($cssVar.':');
    }

    // Every --lss-* variable documented in simple-surveys.css must be consumed in widget.css
    preg_match_all('/(--lss-[a-z0-9-]+)\s*:/', (string) $starterCss, $matches);
    $documentedVars = array_unique($matches[1]);

    expect($documentedVars)->not->toBeEmpty();

    foreach ($documentedVars as $cssVar) {
        expect($widgetCss)->toContain('var('.$cssVar.',');
        // Core stylesheet must never directly assign public --lss-* vars (prevents shadowing :root)
        expect(preg_match('/^\s*'.preg_quote($cssVar, '/').'\s*:/m', $widgetCss))->toBe(0);
    }
});
