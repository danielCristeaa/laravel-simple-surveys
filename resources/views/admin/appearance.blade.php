@extends('simple-surveys::admin.layout')

@section('title', $survey->getTitle() . ' — Appearance — Simple Surveys Admin')

@section('content')
    @php
        use DanielCristea\SimpleSurveys\Enums\QuestionType;
        use DanielCristea\SimpleSurveys\Services\WidgetStyles;

        $colorTokens = array_filter($tokens, static fn (array $meta): bool => $meta['group'] === 'colors');
        $dimensionTokens = array_filter($tokens, static fn (array $meta): bool => $meta['group'] === 'dimensions');
        $typographyTokens = array_filter($tokens, static fn (array $meta): bool => $meta['group'] === 'typography');

        $selectedTheme = old('theme', $appearance->theme?->value ?? '');
        $selectedPosition = old('position', $appearance->position?->value ?? '');
        $previewTheme = $selectedTheme !== '' ? $selectedTheme : 'light';
        $previewPosition = $selectedPosition !== '' ? $selectedPosition : 'bottom-right';
        $previewQuestions = array_slice($survey->getQuestions(), 0, 3);
    @endphp

    <style>{!! WidgetStyles::css() !!}</style>

    <div style="margin-bottom: 14px;">
        <a href="{{ route('simple-surveys.admin.index') }}">&larr; All Surveys</a>
    </div>

    <div class="page-header">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <h1 class="page-title">{{ $survey->getTitle() }}</h1>
                <span class="badge badge-{{ $status->value }}">{{ $status->value }}</span>
            </div>
            @if ($survey->getDescription())
                <p class="page-subtitle">{{ $survey->getDescription() }}</p>
            @endif
        </div>

        @if (! $appearance->isEmpty())
            <div style="display: flex; align-items: center; gap: 8px;">
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

    <nav class="tabs" aria-label="Survey sections">
        <a href="{{ route('simple-surveys.admin.show', $survey->key()) }}" class="tab-link">
            Results &amp; Metrics
        </a>
        <a href="{{ route('simple-surveys.admin.appearance.edit', $survey->key()) }}" class="tab-link is-active" aria-current="page">
            Appearance
        </a>
    </nav>

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
            <div class="card">
                <h2 class="section-heading">Presets &amp; Placement</h2>
                <p class="section-desc">
                    Choose a theme mode and screen position for this survey. Leave any field on <strong>Inherit</strong> to defer to your Blade component or CSS.
                </p>

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

            {{-- Colors --}}
            <div class="card">
                <h2 class="section-heading">Colors</h2>
                <p class="section-desc">
                    Customize button, background, surface, border, and text colors using hex values (e.g. <code>#4f46e5</code>). Blank fields inherit from CSS.
                </p>

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

            {{-- Dimensions & Shape --}}
            <div class="card">
                <h2 class="section-heading">Dimensions &amp; Shape</h2>
                <p class="section-desc">
                    Control widget width, max height, and corner radii using CSS lengths (<code>px</code>, <code>rem</code>, <code>em</code>, <code>vh</code>, <code>vw</code>, or <code>%</code>).
                </p>

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

            {{-- Typography --}}
            <div class="card">
                <h2 class="section-heading">Typography</h2>
                <p class="section-desc">
                    Override the base font size or font stack for this survey widget.
                </p>

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

            <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                <span class="muted" style="font-size: 12.5px;">
                    Empty fields inherit from your codebase CSS (<code>resources/css/simple-surveys.css</code>).
                </span>
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
                            <div class="lss-banner-icon" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
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
                                                    $maxR = min((int) ($question->getMax() ?? 5), $minR + 9);
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
            <div class="card">
                <h2 class="section-heading">Style in Codebase CSS</h2>
                <p class="section-desc" style="margin-bottom: 6px;">
                    Prefer keeping styles in Git? Target this survey in <code>resources/css/simple-surveys.css</code>:
                </p>
                <pre class="code-snippet"><code>[data-lss-survey="{{ $survey->key() }}"] {
    --lss-accent: #4f46e5;
    --lss-bg: #ffffff;
    --lss-card-width: 390px;
}</code></pre>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var previewRoot = document.getElementById('lss-preview-root');
            var previewCanvas = document.getElementById('lss-preview-canvas');
            var previewCard = document.getElementById('lss-preview-card');
            var previewBanner = document.getElementById('lss-preview-banner');
            var positionLabel = document.getElementById('lss-preview-position-label');
            var themeSelect = document.querySelector('[data-preview-theme]');
            var positionSelect = document.querySelector('[data-preview-position]');

            if (!previewRoot) {
                return;
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
