@extends('simple-surveys::admin.layout')

@section('title', $survey->getTitle() . ' — Appearance — Simple Surveys Admin')

@section('content')
    @php
        use DanielCristea\SimpleSurveys\Enums\QuestionType;
        use DanielCristea\SimpleSurveys\Enums\WidgetIconPreset;
        use DanielCristea\SimpleSurveys\Services\WidgetStyles;
        use DanielCristea\SimpleSurveys\WidgetIcon;

        $colorTokens = array_filter($tokens, static fn (array $meta): bool => $meta['group'] === 'colors');
        $dimensionTokens = array_filter($tokens, static fn (array $meta): bool => $meta['group'] === 'dimensions');
        $typographyTokens = array_filter($tokens, static fn (array $meta): bool => $meta['group'] === 'typography');

        $selectedTheme = old('theme', $appearance->theme?->value ?? '');
        $selectedPosition = old('position', $appearance->position?->value ?? '');
        $selectedIconRaw = (string) old('icon', $appearance->icon?->toStorageValue() ?? '');

        if ($selectedIconRaw === '') {
            $selectedIconMode = 'inherit';
        } elseif ($selectedIconRaw === WidgetIcon::NONE) {
            $selectedIconMode = 'none';
        } elseif (WidgetIconPreset::tryFrom($selectedIconRaw) !== null) {
            $selectedIconMode = 'preset';
        } else {
            $selectedIconMode = 'custom';
        }

        $customIconPath = $selectedIconMode === 'custom' ? $selectedIconRaw : '';
        $previewBannerIcon = $selectedIconRaw !== ''
            ? (WidgetIcon::tryFrom($selectedIconRaw) ?? $inheritedIcon)
            : $inheritedIcon;

        $presetSvgMap = [];
        foreach ($iconPresets as $presetCase) {
            $presetSvgMap[$presetCase->value] = $presetCase->svg();
        }

        $inheritedIconPayload = [
            'type' => $inheritedIcon->type,
            'preset' => $inheritedIcon->preset?->value,
            'svg' => $inheritedIcon->svg(),
            'url' => $inheritedIcon->assetUrl(),
        ];

        $hasDimensionOverrides = false;
        foreach ($dimensionTokens as $tk => $m) {
            if ((string) old('styles.' . $tk, $appearance->style($tk) ?? '') !== '' || $errors->has('styles.' . $tk)) {
                $hasDimensionOverrides = true;
                break;
            }
        }

        $hasTypographyOverrides = false;
        foreach ($typographyTokens as $tk => $m) {
            if ((string) old('styles.' . $tk, $appearance->style($tk) ?? '') !== '' || $errors->has('styles.' . $tk)) {
                $hasTypographyOverrides = true;
                break;
            }
        }

        $previewTheme = $selectedTheme !== '' ? $selectedTheme : 'light';
        $previewPosition = $selectedPosition !== '' ? $selectedPosition : 'bottom-right';
        $previewQuestions = array_slice($survey->getQuestions(), 0, 3);
        $surveyOptions = $surveyOptions ?? [];
        $priority = $priority ?? $survey->getPriority();
        $questionsCount = $questionsCount ?? count($survey->getQuestions());
        $responsesCount = $responsesCount ?? 0;
    @endphp

    <style>{!! WidgetStyles::css() !!}</style>

    <div class="breadcrumb-bar">
        <a href="{{ route('simple-surveys.admin.index') }}" class="breadcrumb-link">&larr; All Surveys</a>
        @if (count($surveyOptions) > 1)
            <span class="breadcrumb-sep" aria-hidden="true">/</span>
            <select class="survey-switcher" data-survey-switcher aria-label="Switch survey">
                @foreach ($surveyOptions as $opt)
                    @if ($opt['registered'])
                        <option
                            value="{{ route('simple-surveys.admin.appearance.edit', $opt['key']) }}"
                            @selected($opt['key'] === $survey->key())
                        >
                            {{ $opt['title'] }} ({{ $opt['status']->value }})
                        </option>
                    @endif
                @endforeach
            </select>
        @endif
    </div>

    <div class="page-header">
        <div style="min-width: 0; flex: 1;">
            <div class="page-title-row">
                <h1 class="page-title">{{ $survey->getTitle() }}</h1>
                <span class="badge badge-{{ $status->value }}">
                    <span class="badge-dot" aria-hidden="true"></span>
                    {{ $status->value }}
                </span>
            </div>
            @if ($survey->getDescription())
                <p class="page-subtitle">{{ $survey->getDescription() }}</p>
            @endif
            <div class="meta-strip">
                <code>{{ $survey->key() }}</code>
                <span class="meta-pill {{ $priority > 0 ? 'meta-pill-accent' : '' }}" title="Higher priority surveys are shown first when multiple surveys match">
                    Priority: {{ $priority }}
                </span>
                <span class="meta-pill">
                    {{ $questionsCount }} {{ $questionsCount === 1 ? 'question' : 'questions' }}
                </span>
            </div>
        </div>

        @if (! $appearance->isEmpty())
            <div class="header-actions">
                <form
                    method="POST"
                    action="{{ route('simple-surveys.admin.appearance.reset', $survey->key()) }}"
                    class="inline-form"
                    data-confirm-title="Reset survey appearance?"
                    data-confirm-message="This will clear all dashboard style overrides for this survey so it inherits your codebase CSS and default theme again."
                    data-confirm-button="Reset Appearance"
                    data-confirm-variant="warning"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-warning">Reset to Defaults</button>
                </form>
            </div>
        @endif
    </div>

    <div class="tabs-bar">
        <nav class="tabs" aria-label="Survey sections">
            <a href="{{ route('simple-surveys.admin.show', $survey->key()) }}" class="tab-link">
                Results &amp; Metrics
            </a>
            <a href="{{ route('simple-surveys.admin.show', ['surveyKey' => $survey->key(), 'tab' => 'responses']) }}" class="tab-link">
                <span>Responses</span>
                <span class="tab-count">{{ number_format($responsesCount) }}</span>
            </a>
            <a href="{{ route('simple-surveys.admin.appearance.edit', $survey->key()) }}" class="tab-link is-active" aria-current="page">
                Appearance
            </a>
        </nav>
    </div>

    @if ($errors->any())
        <div class="alert-error" role="alert">
            <strong>Please fix the following appearance settings:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="appearance-grid">
        <form
            method="POST"
            action="{{ route('simple-surveys.admin.appearance.update', $survey->key()) }}"
            class="stack"
            id="lss-appearance-form"
        >
            @csrf
            @method('PUT')

            {{-- Presets & Placement --}}
            <details class="collapsible-card" open>
                <summary class="collapsible-summary">
                    <div>
                        <h2 class="section-heading" style="margin: 0;">Presets &amp; Placement</h2>
                        <p class="section-desc" style="margin: 2px 0 0;">Theme preset, screen position, and quick accent palettes.</p>
                    </div>
                    <svg class="collapsible-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </summary>

                <div class="collapsible-body">
                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-label" for="lss-field-theme">Theme Preset</label>
                            <select id="lss-field-theme" name="theme" class="form-control" data-preview-theme>
                                <option value="" @selected($selectedTheme === '')>Inherit (Default)</option>
                                @foreach ($themes as $themeCase)
                                    <option value="{{ $themeCase->value }}" @selected($selectedTheme === $themeCase->value)>
                                        {{ $themeCase->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('theme')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-label" for="lss-field-position">Widget Placement</label>
                            <select id="lss-field-position" name="position" class="form-control" data-preview-position>
                                <option value="" @selected($selectedPosition === '')>Inherit (Default)</option>
                                @foreach ($positions as $positionCase)
                                    <option value="{{ $positionCase->value }}" @selected($selectedPosition === $positionCase->value)>
                                        {{ $positionCase->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('position')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="preset-chips" aria-label="Quick accent palettes">
                        <button type="button" class="preset-chip" data-palette-accent="#4f46e5" data-palette-hover="#4338ca" data-palette-btn-text="#ffffff">
                            <span class="preset-dot" style="background: #4f46e5;"></span> Indigo
                        </button>
                        <button type="button" class="preset-chip" data-palette-accent="#10b981" data-palette-hover="#059669" data-palette-btn-text="#ffffff">
                            <span class="preset-dot" style="background: #10b981;"></span> Emerald
                        </button>
                        <button type="button" class="preset-chip" data-palette-accent="#e11d48" data-palette-hover="#be123c" data-palette-btn-text="#ffffff">
                            <span class="preset-dot" style="background: #e11d48;"></span> Rose
                        </button>
                        <button type="button" class="preset-chip" data-palette-accent="#d97706" data-palette-hover="#b45309" data-palette-btn-text="#ffffff">
                            <span class="preset-dot" style="background: #d97706;"></span> Amber
                        </button>
                        <button type="button" class="preset-chip" data-palette-accent="#0284c7" data-palette-hover="#0369a1" data-palette-btn-text="#ffffff">
                            <span class="preset-dot" style="background: #0284c7;"></span> Sky
                        </button>
                        <button type="button" class="preset-chip" data-palette-accent="#0f172a" data-palette-hover="#1e293b" data-palette-btn-text="#ffffff">
                            <span class="preset-dot" style="background: #0f172a;"></span> Slate
                        </button>
                    </div>
                </div>
            </details>

            {{-- Banner Icon --}}
            <details class="collapsible-card" open>
                <summary class="collapsible-summary">
                    <div>
                        <h2 class="section-heading" style="margin: 0;">Banner Icon</h2>
                        <p class="section-desc" style="margin: 2px 0 0;">Built-in presets, hidden icon, or custom <code>public/</code> image.</p>
                    </div>
                    <svg class="collapsible-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </summary>

                <div class="collapsible-body">
                    <input type="hidden" name="icon" id="lss-field-icon" value="{{ $selectedIconRaw }}">

                    <div class="icon-picker-grid" role="group" aria-label="Banner icon selection">
                        <button
                            type="button"
                            class="icon-tile {{ $selectedIconMode === 'inherit' ? 'is-selected' : '' }}"
                            data-icon-choice="inherit"
                            aria-pressed="{{ $selectedIconMode === 'inherit' ? 'true' : 'false' }}"
                        >
                            <span class="icon-tile-glyph" aria-hidden="true">
                                @if ($inheritedIcon->isPreset())
                                    {!! $inheritedIcon->svg() !!}
                                @elseif ($inheritedIcon->isAsset())
                                    <img src="{{ $inheritedIcon->assetUrl() }}" alt="">
                                @else
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                    </svg>
                                @endif
                            </span>
                            <span>Inherit ({{ $inheritedIcon->label() }})</span>
                        </button>

                        <button
                            type="button"
                            class="icon-tile {{ $selectedIconMode === 'none' ? 'is-selected' : '' }}"
                            data-icon-choice="none"
                            aria-pressed="{{ $selectedIconMode === 'none' ? 'true' : 'false' }}"
                        >
                            <span class="icon-tile-glyph" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                </svg>
                            </span>
                            <span>Hidden</span>
                        </button>

                        @foreach ($iconPresets as $presetCase)
                            @php
                                $isPresetSelected = $selectedIconMode === 'preset' && $selectedIconRaw === $presetCase->value;
                            @endphp
                            <button
                                type="button"
                                class="icon-tile {{ $isPresetSelected ? 'is-selected' : '' }}"
                                data-icon-choice="preset"
                                data-icon-preset="{{ $presetCase->value }}"
                                aria-pressed="{{ $isPresetSelected ? 'true' : 'false' }}"
                            >
                                <span class="icon-tile-glyph" aria-hidden="true">{!! $presetCase->svg() !!}</span>
                                <span>{{ $presetCase->label() }}</span>
                            </button>
                        @endforeach

                        <button
                            type="button"
                            class="icon-tile {{ $selectedIconMode === 'custom' ? 'is-selected' : '' }}"
                            data-icon-choice="custom"
                            aria-pressed="{{ $selectedIconMode === 'custom' ? 'true' : 'false' }}"
                        >
                            <span class="icon-tile-glyph" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                            </span>
                            <span>Custom</span>
                        </button>
                    </div>

                    <div class="icon-custom-panel {{ $selectedIconMode === 'custom' ? '' : 'is-hidden' }}" id="lss-icon-custom-panel">
                        <div class="form-field">
                            <div class="form-label-row">
                                <label class="form-label" for="lss-icon-custom-input">Custom Asset Path (.png or .svg)</label>
                                <span class="token-tag">public/</span>
                            </div>
                            <div class="color-input-wrap">
                                <input
                                    type="text"
                                    id="lss-icon-custom-input"
                                    class="form-control"
                                    value="{{ $customIconPath }}"
                                    placeholder="images/survey-icon.svg"
                                    autocomplete="off"
                                >
                                <button
                                    type="button"
                                    class="clear-field-btn"
                                    title="Clear custom path"
                                    aria-label="Clear custom icon path"
                                    id="lss-icon-custom-clear"
                                >&times;</button>
                            </div>
                            <p class="icon-helper">
                                Relative path inside <code>public/</code> resolved via <code>asset()</code> (e.g. <code>images/survey-icon.svg</code> or <code>/images/brand.png</code>).
                            </p>
                            <div class="icon-preview-warning is-hidden" id="lss-icon-load-warning" role="status"></div>
                        </div>
                    </div>

                    @error('icon')
                        <p class="form-error" style="margin-top: 8px;">{{ $message }}</p>
                    @enderror
                </div>
            </details>

            {{-- Colors --}}
            <details class="collapsible-card" open>
                <summary class="collapsible-summary">
                    <div>
                        <h2 class="section-heading" style="margin: 0;">Colors</h2>
                        <p class="section-desc" style="margin: 2px 0 0;">Hex color overrides; blank fields inherit from CSS.</p>
                    </div>
                    <svg class="collapsible-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </summary>

                <div class="collapsible-body">
                    <div class="form-grid">
                        @foreach ($colorTokens as $tokenKey => $meta)
                            @php
                                $currentVal = old('styles.' . $tokenKey, $appearance->style($tokenKey) ?? '');
                                $swatchVal = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $currentVal) === 1
                                    ? $currentVal
                                    : $meta['placeholder_light'];
                            @endphp
                            <div class="form-field">
                                <div class="form-label-row">
                                    <label class="form-label" for="lss-style-{{ $tokenKey }}">{{ $meta['label'] }}</label>
                                    <span class="token-tag">{{ $meta['css_var'] }}</span>
                                </div>
                                <div class="color-input-wrap">
                                    <input
                                        type="color"
                                        class="color-swatch {{ $currentVal === '' ? 'is-inherited' : '' }}"
                                        value="{{ $swatchVal }}"
                                        aria-label="{{ $meta['label'] }} picker"
                                        data-color-picker-for="{{ $tokenKey }}"
                                        data-default-light="{{ $meta['placeholder_light'] }}"
                                        data-default-dark="{{ $meta['placeholder_dark'] }}"
                                    >
                                    <input
                                        type="text"
                                        id="lss-style-{{ $tokenKey }}"
                                        name="styles[{{ $tokenKey }}]"
                                        class="form-control"
                                        value="{{ $currentVal }}"
                                        placeholder="Inherit ({{ $meta['placeholder_light'] }})"
                                        data-style-input="{{ $tokenKey }}"
                                        data-css-var="{{ $meta['css_var'] }}"
                                    >
                                    <button
                                        type="button"
                                        class="clear-field-btn"
                                        title="Clear override (inherit)"
                                        aria-label="Clear {{ $meta['label'] }}"
                                        data-clear-style="{{ $tokenKey }}"
                                    >&times;</button>
                                </div>
                                @error('styles.' . $tokenKey)
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </details>

            {{-- Dimensions & Shape --}}
            <details class="collapsible-card" @if ($hasDimensionOverrides) open @endif>
                <summary class="collapsible-summary">
                    <div>
                        <h2 class="section-heading" style="margin: 0;">Dimensions &amp; Shape</h2>
                        <p class="section-desc" style="margin: 2px 0 0;">Widget width, max height, and border radii (<code>px</code>, <code>rem</code>, <code>vh</code>, etc.).</p>
                    </div>
                    <svg class="collapsible-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </summary>

                <div class="collapsible-body">
                    <div class="form-grid">
                        @foreach ($dimensionTokens as $tokenKey => $meta)
                            @php
                                $currentVal = old('styles.' . $tokenKey, $appearance->style($tokenKey) ?? '');
                            @endphp
                            <div class="form-field">
                                <div class="form-label-row">
                                    <label class="form-label" for="lss-style-{{ $tokenKey }}">{{ $meta['label'] }}</label>
                                    <span class="token-tag">{{ $meta['css_var'] }}</span>
                                </div>
                                <div class="color-input-wrap">
                                    <input
                                        type="text"
                                        id="lss-style-{{ $tokenKey }}"
                                        name="styles[{{ $tokenKey }}]"
                                        class="form-control"
                                        value="{{ $currentVal }}"
                                        placeholder="Inherit ({{ $meta['placeholder_light'] }})"
                                        data-style-input="{{ $tokenKey }}"
                                        data-css-var="{{ $meta['css_var'] }}"
                                    >
                                    <button
                                        type="button"
                                        class="clear-field-btn"
                                        title="Clear override (inherit)"
                                        aria-label="Clear {{ $meta['label'] }}"
                                        data-clear-style="{{ $tokenKey }}"
                                    >&times;</button>
                                </div>
                                @error('styles.' . $tokenKey)
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </details>

            {{-- Typography --}}
            <details class="collapsible-card" @if ($hasTypographyOverrides) open @endif>
                <summary class="collapsible-summary">
                    <div>
                        <h2 class="section-heading" style="margin: 0;">Typography</h2>
                        <p class="section-desc" style="margin: 2px 0 0;">Override the base font size or font stack for this survey widget.</p>
                    </div>
                    <svg class="collapsible-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </summary>

                <div class="collapsible-body">
                    <div class="form-grid">
                        @foreach ($typographyTokens as $tokenKey => $meta)
                            @php
                                $currentVal = old('styles.' . $tokenKey, $appearance->style($tokenKey) ?? '');
                            @endphp
                            <div class="form-field {{ $tokenKey === 'font_family' ? 'form-field-full' : '' }}">
                                <div class="form-label-row">
                                    <label class="form-label" for="lss-style-{{ $tokenKey }}">{{ $meta['label'] }}</label>
                                    <span class="token-tag">{{ $meta['css_var'] }}</span>
                                </div>
                                <div class="color-input-wrap">
                                    <input
                                        type="text"
                                        id="lss-style-{{ $tokenKey }}"
                                        name="styles[{{ $tokenKey }}]"
                                        class="form-control"
                                        value="{{ $currentVal }}"
                                        placeholder="Inherit ({{ $meta['placeholder_light'] }})"
                                        @if ($tokenKey === 'font_family') list="lss-font-presets" @endif
                                        data-style-input="{{ $tokenKey }}"
                                        data-css-var="{{ $meta['css_var'] }}"
                                    >
                                    <button
                                        type="button"
                                        class="clear-field-btn"
                                        title="Clear override (inherit)"
                                        aria-label="Clear {{ $meta['label'] }}"
                                        data-clear-style="{{ $tokenKey }}"
                                    >&times;</button>
                                </div>
                                @error('styles.' . $tokenKey)
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    <datalist id="lss-font-presets">
                        <option value='ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'>System Sans</option>
                        <option value='"Inter", ui-sans-serif, system-ui, sans-serif'>Inter</option>
                        <option value='Georgia, Cambria, "Times New Roman", Times, serif'>Editorial Serif</option>
                        <option value='ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace'>Monospace</option>
                    </datalist>
                </div>
            </details>

            <div class="sticky-save-bar">
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <span class="unsaved-pill" id="lss-unsaved-pill">Unsaved changes</span>
                    <span class="muted" style="font-size: 12px;">
                        Empty fields inherit from <code>resources/css/simple-surveys.css</code>.
                    </span>
                </div>
                <button type="submit" class="btn btn-primary">Save Appearance</button>
            </div>
        </form>

        {{-- Live Preview Column --}}
        <div class="preview-sticky stack">
            <div class="card">
                <div class="preview-toolbar">
                    <div class="preview-toolbar-row">
                        <h2 class="section-heading" style="margin: 0;">Live Preview</h2>
                        <span class="preview-placement-badge">
                            Placement: <strong id="lss-preview-position-label">{{ \DanielCristea\SimpleSurveys\Enums\WidgetPosition::tryFrom($previewPosition)?->label() ?? 'Bottom Right' }}</strong>
                        </span>
                    </div>

                    <div class="preview-toolbar-row">
                        <div class="segmented" role="group" aria-label="Preview view">
                            <button type="button" class="segmented-btn is-active" data-preview-mode="card">Card</button>
                            <button type="button" class="segmented-btn" data-preview-mode="banner">Banner</button>
                        </div>
                        <div class="segmented" role="group" aria-label="Canvas background">
                            <button type="button" class="segmented-btn is-active" data-canvas-bg="light">Light BG</button>
                            <button type="button" class="segmented-btn" data-canvas-bg="dark">Dark BG</button>
                        </div>
                    </div>
                </div>

                <div class="preview-canvas" id="lss-preview-canvas">
                    <div
                        class="lss-root lss-pos-{{ $previewPosition }}"
                        id="lss-preview-root"
                        data-lss-survey="{{ $survey->key() }}"
                        data-lss-position="{{ $previewPosition }}"
                        data-lss-theme="{{ $previewTheme }}"
                        @if ($appearance->toInlineStyle() !== '') style="{{ $appearance->toInlineStyle() }}" @endif
                    >
                        {{-- Preview Banner --}}
                        <div class="lss-banner lss-hidden" id="lss-preview-banner">
                            <div
                                class="lss-banner-icon {{ $previewBannerIcon->isNone() ? 'lss-hidden' : '' }}"
                                id="lss-preview-banner-icon"
                                @if ($previewBannerIcon->isPreset())
                                    data-lss-icon-type="preset"
                                    data-lss-icon="{{ $previewBannerIcon->preset?->value }}"
                                @elseif ($previewBannerIcon->isAsset())
                                    data-lss-icon-type="asset"
                                @endif
                                aria-hidden="true"
                            >
                                @if ($previewBannerIcon->isPreset())
                                    {!! $previewBannerIcon->svg() !!}
                                @elseif ($previewBannerIcon->isAsset())
                                    <img class="lss-banner-icon-img" src="{{ $previewBannerIcon->assetUrl() }}" alt="" decoding="async">
                                @endif
                            </div>
                            <p class="lss-banner-text">{{ $survey->getBannerText() }}</p>
                            <div class="lss-banner-actions">
                                <button type="button" class="lss-btn-primary" data-preview-switch="card">Take Survey</button>
                                @if ($survey->isDismissible())
                                    <button type="button" class="lss-btn-icon" aria-label="Dismiss">&times;</button>
                                @endif
                            </div>
                        </div>

                        {{-- Preview Card --}}
                        <div class="lss-card" id="lss-preview-card">
                            <div class="lss-card-header">
                                <div>
                                    <h3 class="lss-card-title">{{ $survey->getTitle() }}</h3>
                                    @if ($survey->getDescription())
                                        <p class="lss-card-desc">{{ $survey->getDescription() }}</p>
                                    @endif
                                </div>
                                <button type="button" class="lss-btn-icon" data-preview-switch="banner" aria-label="Minimize">&times;</button>
                            </div>

                            <div class="lss-card-body">
                                @forelse ($previewQuestions as $question)
                                    <div class="lss-field">
                                        <span class="lss-label">
                                            {{ $question->label() }}
                                            @if ($question->isRequired())
                                                <span class="lss-required" aria-hidden="true">*</span>
                                            @endif
                                        </span>
                                        @if ($question->getHelpText())
                                            <p class="lss-help">{{ $question->getHelpText() }}</p>
                                        @endif

                                        @switch($question->type())
                                            @case(QuestionType::Rating)
                                                @php
                                                    $minR = (int) ($question->getMin() ?? 1);
                                                    $maxR = min((int) ($question->getMax() ?? 5), $minR + 10);
                                                @endphp
                                                <div class="lss-rating-group">
                                                    @for ($s = $minR; $s <= $maxR; $s++)
                                                        <label class="lss-rating-item">
                                                            <input type="radio" name="preview_{{ $question->key() }}" @checked($s === $maxR)>
                                                            <span>{{ $s }}</span>
                                                        </label>
                                                    @endfor
                                                </div>
                                                @break

                                            @case(QuestionType::Boolean)
                                                <div class="lss-boolean-group">
                                                    <label class="lss-boolean-item">
                                                        <input type="radio" name="preview_{{ $question->key() }}" checked>
                                                        <span>Yes</span>
                                                    </label>
                                                    <label class="lss-boolean-item">
                                                        <input type="radio" name="preview_{{ $question->key() }}">
                                                        <span>No</span>
                                                    </label>
                                                </div>
                                                @break

                                            @case(QuestionType::Select)
                                                <select class="lss-select" aria-label="{{ $question->label() }}">
                                                    <option>{{ $question->getPlaceholder() ?? 'Select an option...' }}</option>
                                                    @foreach ($question->getOptions() as $optLabel)
                                                        <option>{{ $optLabel }}</option>
                                                    @endforeach
                                                </select>
                                                @break

                                            @case(QuestionType::Radio)
                                            @case(QuestionType::Checkbox)
                                                <div class="lss-options">
                                                    @foreach (array_slice($question->getOptions(), 0, 3, true) as $optLabel)
                                                        <label class="lss-option-item">
                                                            <input type="{{ $question->type() === QuestionType::Checkbox ? 'checkbox' : 'radio' }}" name="preview_{{ $question->key() }}">
                                                            <span>{{ $optLabel }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                @break

                                            @case(QuestionType::Textarea)
                                                <textarea class="lss-textarea" rows="2" placeholder="{{ $question->getPlaceholder() ?? 'Share your thoughts...' }}"></textarea>
                                                @break

                                            @default
                                                <input type="text" class="lss-input" placeholder="{{ $question->getPlaceholder() ?? 'Type your answer...' }}">
                                        @endswitch
                                    </div>
                                @empty
                                    <div class="lss-field">
                                        <span class="lss-label">How would you rate your experience? <span class="lss-required">*</span></span>
                                        <div class="lss-rating-group">
                                            @for ($s = 1; $s <= 5; $s++)
                                                <label class="lss-rating-item">
                                                    <input type="radio" name="preview_sample_rating" @checked($s === 5)>
                                                    <span>{{ $s }}</span>
                                                </label>
                                            @endfor
                                        </div>
                                    </div>
                                    <div class="lss-field">
                                        <span class="lss-label">Anything we can improve?</span>
                                        <input type="text" class="lss-input" placeholder="Share a quick thought...">
                                    </div>
                                @endforelse
                            </div>

                            <div class="lss-card-footer">
                                @if ($survey->isDismissible())
                                    <button type="button" class="lss-btn-link">
                                        {{ $survey->getRepromptAfter() !== null ? 'Remind me later' : "Don't show again" }}
                                    </button>
                                @else
                                    <span></span>
                                @endif
                                <button type="button" class="lss-btn-primary">{{ $survey->getSubmitLabel() }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Codebase CSS Helper --}}
            <details class="collapsible-card">
                <summary class="collapsible-summary">
                    <div>
                        <h2 class="section-heading" style="margin: 0;">Style in Codebase CSS</h2>
                        <p class="section-desc" style="margin: 2px 0 0;">Copyable selector for <code>resources/css/simple-surveys.css</code>.</p>
                    </div>
                    <svg class="collapsible-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </summary>
                <div class="collapsible-body">
                    <pre class="code-snippet" style="margin: 0;"><code>[data-lss-survey="{{ $survey->key() }}"] {
    --lss-accent: #4f46e5;
    --lss-bg: #ffffff;
    --lss-card-width: 390px;
}</code></pre>
                </div>
            </details>
        </div>
    </div>

    <script>
        (function () {
            var form = document.getElementById('lss-appearance-form');
            var unsavedPill = document.getElementById('lss-unsaved-pill');
            var previewRoot = document.getElementById('lss-preview-root');
            var previewCanvas = document.getElementById('lss-preview-canvas');
            var previewCard = document.getElementById('lss-preview-card');
            var previewBanner = document.getElementById('lss-preview-banner');
            var previewBannerIcon = document.getElementById('lss-preview-banner-icon');
            var positionLabel = document.getElementById('lss-preview-position-label');
            var themeSelect = document.querySelector('[data-preview-theme]');
            var positionSelect = document.querySelector('[data-preview-position]');
            var iconHiddenInput = document.getElementById('lss-field-icon');
            var iconCustomPanel = document.getElementById('lss-icon-custom-panel');
            var iconCustomInput = document.getElementById('lss-icon-custom-input');
            var iconCustomClear = document.getElementById('lss-icon-custom-clear');
            var iconLoadWarning = document.getElementById('lss-icon-load-warning');
            var presetSvgs = @json($presetSvgMap);
            var inheritedIcon = @json($inheritedIconPayload);
            var assetBaseUrl = @json($assetBaseUrl);

            if (!previewRoot) {
                return;
            }

            function markDirty() {
                if (unsavedPill) {
                    unsavedPill.classList.add('is-visible');
                }
            }

            if (form) {
                form.addEventListener('input', markDirty);
                form.addEventListener('change', markDirty);
            }

            var positionNames = {
                'bottom-right': 'Bottom Right',
                'bottom-left': 'Bottom Left',
                'top-right': 'Top Right',
                'top-left': 'Top Left',
                'center': 'Centered Modal'
            };

            function isSixHex(value) {
                return /^#[0-9a-fA-F]{6}$/.test(value);
            }

            function syncColorPicker(tokenKey, value) {
                var picker = document.querySelector('[data-color-picker-for="' + tokenKey + '"]');
                if (!picker) {
                    return;
                }
                var trimmed = value.trim();
                if (isSixHex(trimmed)) {
                    picker.value = trimmed;
                    picker.classList.remove('is-inherited');
                } else if (trimmed === '') {
                    var activeTheme = (themeSelect && themeSelect.value) || 'light';
                    picker.value = activeTheme === 'dark'
                        ? (picker.dataset.defaultDark || '#0f172a')
                        : (picker.dataset.defaultLight || '#ffffff');
                    picker.classList.add('is-inherited');
                } else {
                    picker.classList.remove('is-inherited');
                }
            }

            function applyStyleToken(input) {
                var cssVar = input.dataset.cssVar;
                var tokenKey = input.dataset.styleInput;
                var val = input.value.trim();

                if (val === '') {
                    previewRoot.style.removeProperty(cssVar);
                } else {
                    previewRoot.style.setProperty(cssVar, val);
                }

                syncColorPicker(tokenKey, val);
            }

            document.querySelectorAll('[data-style-input]').forEach(function (input) {
                input.addEventListener('input', function () {
                    applyStyleToken(input);
                });
                applyStyleToken(input);
            });

            document.querySelectorAll('[data-color-picker-for]').forEach(function (picker) {
                picker.addEventListener('input', function () {
                    var key = picker.dataset.colorPickerFor;
                    var textInput = document.querySelector('[data-style-input="' + key + '"]');
                    if (textInput) {
                        textInput.value = picker.value;
                        applyStyleToken(textInput);
                        markDirty();
                    }
                });
            });

            document.querySelectorAll('[data-clear-style]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var key = btn.dataset.clearStyle;
                    var textInput = document.querySelector('[data-style-input="' + key + '"]');
                    if (textInput) {
                        textInput.value = '';
                        applyStyleToken(textInput);
                        markDirty();
                    }
                });
            });

            document.querySelectorAll('[data-palette-accent]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    var updates = {
                        accent: chip.dataset.paletteAccent || '',
                        accent_hover: chip.dataset.paletteHover || '',
                        button_text: chip.dataset.paletteBtnText || ''
                    };
                    Object.keys(updates).forEach(function (key) {
                        var input = document.querySelector('[data-style-input="' + key + '"]');
                        if (input) {
                            input.value = updates[key];
                            applyStyleToken(input);
                        }
                    });
                    markDirty();
                });
            });

            if (themeSelect) {
                themeSelect.addEventListener('change', function () {
                    var nextTheme = themeSelect.value || 'light';
                    previewRoot.dataset.lssTheme = nextTheme;
                    document.querySelectorAll('[data-style-input]').forEach(function (input) {
                        syncColorPicker(input.dataset.styleInput, input.value);
                    });
                });
            }

            if (positionSelect) {
                positionSelect.addEventListener('change', function () {
                    var nextPos = positionSelect.value || 'bottom-right';
                    previewRoot.dataset.lssPosition = nextPos;
                    if (positionLabel) {
                        positionLabel.textContent = positionNames[nextPos] || 'Bottom Right';
                    }
                });
            }

            function setPreviewMode(mode) {
                document.querySelectorAll('[data-preview-mode]').forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.dataset.previewMode === mode);
                });
                if (previewCard && previewBanner) {
                    previewCard.classList.toggle('lss-hidden', mode !== 'card');
                    previewBanner.classList.toggle('lss-hidden', mode !== 'banner');
                }
            }

            var assetPathRegex = /^(?!.*\.\.)(?:[a-zA-Z0-9_-][a-zA-Z0-9._-]*\/)*[a-zA-Z0-9_-][a-zA-Z0-9._-]*\.(?:png|svg)$/i;

            function normalizeAssetPath(raw) {
                var trimmed = (raw || '').trim();
                if (!trimmed || trimmed.length > 255 || trimmed.indexOf('//') === 0) {
                    return null;
                }
                var candidate = trimmed.charAt(0) === '/' ? trimmed.slice(1) : trimmed;
                if (!candidate || !assetPathRegex.test(candidate)) {
                    return null;
                }
                return candidate;
            }

            function hideIconWarning() {
                if (iconLoadWarning) {
                    iconLoadWarning.textContent = '';
                    iconLoadWarning.classList.add('is-hidden');
                }
            }

            function showIconWarning(message) {
                if (iconLoadWarning) {
                    iconLoadWarning.textContent = message;
                    iconLoadWarning.classList.remove('is-hidden');
                }
            }

            function renderPreviewIconAsset(url, warnOnError) {
                if (!previewBannerIcon) {
                    return;
                }
                previewBannerIcon.classList.remove('lss-hidden');
                previewBannerIcon.dataset.lssIconType = 'asset';
                delete previewBannerIcon.dataset.lssIcon;
                previewBannerIcon.innerHTML = '';

                var img = document.createElement('img');
                img.className = 'lss-banner-icon-img';
                img.alt = '';
                img.decoding = 'async';
                img.addEventListener('load', function () {
                    hideIconWarning();
                });
                img.addEventListener('error', function () {
                    if (warnOnError) {
                        showIconWarning('Preview could not load "' + url + '". Make sure the file exists inside your application\'s public/ directory.');
                    }
                });
                img.src = url;
                previewBannerIcon.appendChild(img);
            }

            function renderPreviewIconPreset(presetKey, svgHtml) {
                if (!previewBannerIcon) {
                    return;
                }
                hideIconWarning();
                previewBannerIcon.classList.remove('lss-hidden');
                previewBannerIcon.dataset.lssIconType = 'preset';
                previewBannerIcon.dataset.lssIcon = presetKey;
                previewBannerIcon.innerHTML = svgHtml;
            }

            function renderPreviewIconHidden() {
                if (!previewBannerIcon) {
                    return;
                }
                hideIconWarning();
                previewBannerIcon.classList.add('lss-hidden');
                delete previewBannerIcon.dataset.lssIconType;
                delete previewBannerIcon.dataset.lssIcon;
                previewBannerIcon.innerHTML = '';
            }

            function renderInheritedPreviewIcon() {
                if (inheritedIcon && inheritedIcon.type === 'none') {
                    renderPreviewIconHidden();
                } else if (inheritedIcon && inheritedIcon.type === 'asset' && inheritedIcon.url) {
                    renderPreviewIconAsset(inheritedIcon.url, false);
                } else if (inheritedIcon && inheritedIcon.svg) {
                    renderPreviewIconPreset(inheritedIcon.preset || 'chat', inheritedIcon.svg);
                } else {
                    renderPreviewIconPreset('chat', presetSvgs.chat || '');
                }
            }

            function setSelectedIconTile(mode, presetKey) {
                document.querySelectorAll('[data-icon-choice]').forEach(function (tile) {
                    var tileMode = tile.dataset.iconChoice;
                    var isActive = tileMode === mode && (mode !== 'preset' || tile.dataset.iconPreset === presetKey);
                    tile.classList.toggle('is-selected', isActive);
                    tile.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                if (iconCustomPanel) {
                    iconCustomPanel.classList.toggle('is-hidden', mode !== 'custom');
                }
            }

            function applyCustomIconInput(switchToBanner) {
                var raw = iconCustomInput ? iconCustomInput.value.trim() : '';

                if (iconHiddenInput) {
                    iconHiddenInput.value = raw;
                }

                if (switchToBanner) {
                    setPreviewMode('banner');
                }

                if (raw === '') {
                    hideIconWarning();
                    renderInheritedPreviewIcon();
                    return;
                }

                var normalized = normalizeAssetPath(raw);
                if (!normalized) {
                    renderPreviewIconHidden();
                    showIconWarning('Invalid path format. Use a relative path inside public/ ending in .png or .svg (e.g. images/survey-icon.svg).');
                    return;
                }

                var resolvedUrl = (assetBaseUrl ? assetBaseUrl + '/' : '/') + normalized;
                renderPreviewIconAsset(resolvedUrl, true);
            }

            document.querySelectorAll('[data-icon-choice]').forEach(function (tile) {
                tile.addEventListener('click', function () {
                    var mode = tile.dataset.iconChoice;
                    var presetKey = tile.dataset.iconPreset || '';

                    setSelectedIconTile(mode, presetKey);
                    setPreviewMode('banner');
                    markDirty();

                    if (mode === 'inherit') {
                        if (iconHiddenInput) iconHiddenInput.value = '';
                        renderInheritedPreviewIcon();
                    } else if (mode === 'none') {
                        if (iconHiddenInput) iconHiddenInput.value = 'none';
                        renderPreviewIconHidden();
                    } else if (mode === 'preset') {
                        if (iconHiddenInput) iconHiddenInput.value = presetKey;
                        renderPreviewIconPreset(presetKey, presetSvgs[presetKey] || presetSvgs.chat || '');
                    } else if (mode === 'custom') {
                        applyCustomIconInput(true);
                        if (iconCustomInput) {
                            iconCustomInput.focus();
                        }
                    }
                });
            });

            if (iconCustomInput) {
                iconCustomInput.addEventListener('input', function () {
                    setSelectedIconTile('custom', '');
                    applyCustomIconInput(true);
                });

                if (iconCustomPanel && !iconCustomPanel.classList.contains('is-hidden')) {
                    applyCustomIconInput(false);
                }
            }

            if (iconCustomClear) {
                iconCustomClear.addEventListener('click', function () {
                    if (iconCustomInput) {
                        iconCustomInput.value = '';
                        applyCustomIconInput(true);
                        markDirty();
                        iconCustomInput.focus();
                    }
                });
            }

            document.querySelectorAll('[data-preview-mode]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setPreviewMode(btn.dataset.previewMode);
                });
            });

            document.querySelectorAll('[data-preview-switch]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setPreviewMode(btn.dataset.previewSwitch);
                });
            });

            document.querySelectorAll('[data-canvas-bg]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var dark = btn.dataset.canvasBg === 'dark';
                    document.querySelectorAll('[data-canvas-bg]').forEach(function (b) {
                        b.classList.toggle('is-active', b === btn);
                    });
                    if (previewCanvas) {
                        previewCanvas.classList.toggle('is-dark-canvas', dark);
                    }
                });
            });
        })();
    </script>
@endsection
