@php
    use DanielCristea\SimpleSurveys\Enums\QuestionType;
    /** @var \DanielCristea\SimpleSurveys\Survey $activeSurvey */
    $widgetId = 'lss-widget-' . \Illuminate\Support\Str::slug($activeSurvey->key());
@endphp

<div
    id="{{ $widgetId }}"
    class="lss-root lss-pos-{{ $position }}"
    wire:ignore
    data-lss-widget
    data-lss-submit-url="{{ $submitUrl }}"
    data-lss-dismiss-url="{{ $dismissUrl }}"
    data-lss-open-url="{{ $openUrl }}"
    data-lss-thank-you="{{ $activeSurvey->getThankYouMessage() }}"
>
    <style>
        .lss-root {
            --lss-accent: #4f46e5;
            --lss-accent-hover: #4338ca;
            --lss-bg: #ffffff;
            --lss-surface: #f8fafc;
            --lss-border: #e2e8f0;
            --lss-text: #0f172a;
            --lss-muted: #64748b;
            --lss-danger: #dc2626;
            --lss-success: #16a34a;
            --lss-radius: 12px;
            --lss-shadow: 0 12px 32px -4px rgba(15, 23, 42, 0.14), 0 4px 12px -2px rgba(15, 23, 42, 0.08);
            position: fixed;
            bottom: 20px;
            z-index: 9999;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: var(--lss-text);
            max-width: calc(100vw - 32px);
        }
        .lss-root, .lss-root * {
            box-sizing: border-box;
        }
        .lss-pos-bottom-right {
            right: 20px;
        }
        .lss-pos-bottom-left {
            left: 20px;
        }
        .lss-hidden {
            display: none !important;
        }
        .lss-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--lss-bg);
            border: 1px solid var(--lss-border);
            border-radius: var(--lss-radius);
            box-shadow: var(--lss-shadow);
            padding: 12px 14px;
            max-width: 380px;
        }
        .lss-banner-icon {
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #eef2ff;
            color: var(--lss-accent);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .lss-banner-text {
            flex: 1;
            font-weight: 500;
            color: var(--lss-text);
            margin: 0;
            font-size: 13.5px;
        }
        .lss-banner-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        .lss-btn-primary {
            appearance: none;
            border: none;
            background: var(--lss-accent);
            color: #ffffff;
            font-weight: 600;
            font-size: 13px;
            padding: 7px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.15s ease, opacity 0.15s ease;
        }
        .lss-btn-primary:hover {
            background: var(--lss-accent-hover);
        }
        .lss-btn-primary:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }
        .lss-btn-icon {
            appearance: none;
            border: none;
            background: transparent;
            color: var(--lss-muted);
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        .lss-btn-icon:hover {
            background: var(--lss-surface);
            color: var(--lss-text);
        }
        .lss-card {
            width: 390px;
            max-width: 100%;
            max-height: 82vh;
            display: flex;
            flex-direction: column;
            background: var(--lss-bg);
            border: 1px solid var(--lss-border);
            border-radius: var(--lss-radius);
            box-shadow: var(--lss-shadow);
            overflow: hidden;
        }
        .lss-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px 12px;
            border-bottom: 1px solid var(--lss-border);
        }
        .lss-card-title {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--lss-text);
        }
        .lss-card-desc {
            margin: 4px 0 0;
            font-size: 13px;
            color: var(--lss-muted);
        }
        .lss-form {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            margin: 0;
        }
        .lss-card-body {
            padding: 16px 18px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .lss-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .lss-label {
            font-size: 13.5px;
            font-weight: 500;
            color: var(--lss-text);
            margin: 0;
        }
        .lss-required {
            color: var(--lss-danger);
            margin-left: 2px;
        }
        .lss-help {
            font-size: 12px;
            color: var(--lss-muted);
            margin: -2px 0 0;
        }
        .lss-input,
        .lss-textarea,
        .lss-select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--lss-border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 13.5px;
            color: var(--lss-text);
            background: var(--lss-bg);
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .lss-input:focus,
        .lss-textarea:focus,
        .lss-select:focus {
            border-color: var(--lss-accent);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }
        .lss-textarea {
            resize: vertical;
            min-height: 76px;
        }
        .lss-options {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .lss-option-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border: 1px solid var(--lss-border);
            border-radius: 8px;
            cursor: pointer;
            font-size: 13.5px;
            transition: border-color 0.15s ease, background-color 0.15s ease;
        }
        .lss-option-item:hover {
            background: var(--lss-surface);
        }
        .lss-option-item input {
            margin: 0;
            accent-color: var(--lss-accent);
        }
        .lss-rating-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .lss-rating-item {
            position: relative;
            flex: 1 0 32px;
            max-width: 44px;
        }
        .lss-rating-item input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .lss-rating-item span {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 34px;
            border: 1px solid var(--lss-border);
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            background: var(--lss-bg);
            color: var(--lss-text);
            transition: all 0.15s ease;
        }
        .lss-rating-item span:hover {
            border-color: var(--lss-accent);
            background: #eef2ff;
        }
        .lss-rating-item input:checked + span {
            background: var(--lss-accent);
            border-color: var(--lss-accent);
            color: #ffffff;
        }
        .lss-boolean-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .lss-boolean-item {
            position: relative;
        }
        .lss-boolean-item input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .lss-boolean-item span {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border: 1px solid var(--lss-border);
            border-radius: 8px;
            font-weight: 500;
            font-size: 13.5px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .lss-boolean-item input:checked + span {
            background: #eef2ff;
            border-color: var(--lss-accent);
            color: var(--lss-accent);
            font-weight: 600;
        }
        .lss-error {
            font-size: 12px;
            color: var(--lss-danger);
            margin: 0;
        }
        .lss-general-error {
            padding: 8px 10px;
            border-radius: 8px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--lss-danger);
            font-size: 12.5px;
        }
        .lss-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 18px;
            border-top: 1px solid var(--lss-border);
            background: var(--lss-surface);
        }
        .lss-btn-link {
            appearance: none;
            border: none;
            background: transparent;
            color: var(--lss-muted);
            font-size: 12.5px;
            cursor: pointer;
            padding: 4px 0;
            text-decoration: underline;
        }
        .lss-btn-link:hover {
            color: var(--lss-text);
        }
        .lss-thanks {
            padding: 24px 18px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .lss-thanks-icon {
            width: 40px;
            height: 40px;
            border-radius: 9999px;
            background: #dcfce7;
            color: var(--lss-success);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .lss-thanks-msg {
            margin: 0;
            font-weight: 600;
            font-size: 14.5px;
            color: var(--lss-text);
        }
    </style>

    {{-- Collapsed Banner --}}
    <div class="lss-banner" data-lss-banner>
        <div class="lss-banner-icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
        </div>
        <p class="lss-banner-text">{{ $activeSurvey->getBannerText() }}</p>
        <div class="lss-banner-actions">
            <button type="button" class="lss-btn-primary" data-lss-open aria-expanded="false">
                Take Survey
            </button>
            @if ($activeSurvey->isDismissible())
                <button type="button" class="lss-btn-icon" data-lss-dismiss aria-label="Dismiss survey" title="Dismiss">
                    &times;
                </button>
            @endif
        </div>
    </div>

    {{-- Expanded Survey Card --}}
    <div class="lss-card lss-hidden" data-lss-card role="dialog" aria-label="{{ $activeSurvey->getTitle() }}">
        <div class="lss-card-header">
            <div>
                <h3 class="lss-card-title">{{ $activeSurvey->getTitle() }}</h3>
                @if ($activeSurvey->getDescription())
                    <p class="lss-card-desc">{{ $activeSurvey->getDescription() }}</p>
                @endif
            </div>
            <button type="button" class="lss-btn-icon" data-lss-close aria-label="Close survey" title="Minimize">
                &times;
            </button>
        </div>

        <form class="lss-form" data-lss-form action="{{ $submitUrl }}" method="POST" novalidate>
            @csrf

            <div class="lss-card-body">
                <div class="lss-general-error lss-hidden" data-lss-general-error role="alert"></div>

                @foreach ($activeSurvey->getQuestions() as $question)
                    @php
                        $inputId = $widgetId . '-' . $question->key();
                        $fieldName = 'answers[' . $question->key() . ']';
                    @endphp

                    <div class="lss-field" data-lss-field="{{ $question->key() }}">
                        <label class="lss-label" @if (in_array($question->type(), [QuestionType::Text, QuestionType::Textarea, QuestionType::Number, QuestionType::Select], true)) for="{{ $inputId }}" @endif>
                            {{ $question->label() }}
                            @if ($question->isRequired())
                                <span class="lss-required" aria-hidden="true">*</span>
                            @endif
                        </label>

                        @if ($question->getHelpText())
                            <p class="lss-help">{{ $question->getHelpText() }}</p>
                        @endif

                        @switch($question->type())
                            @case(QuestionType::Text)
                                <input
                                    id="{{ $inputId }}"
                                    type="text"
                                    name="{{ $fieldName }}"
                                    class="lss-input"
                                    @if ($question->getPlaceholder()) placeholder="{{ $question->getPlaceholder() }}" @endif
                                    @if ($question->getMaxLength() !== null) maxlength="{{ $question->getMaxLength() }}" @endif
                                    @if ($question->isRequired()) required @endif
                                >
                                @break

                            @case(QuestionType::Textarea)
                                <textarea
                                    id="{{ $inputId }}"
                                    name="{{ $fieldName }}"
                                    class="lss-textarea"
                                    rows="3"
                                    @if ($question->getPlaceholder()) placeholder="{{ $question->getPlaceholder() }}" @endif
                                    @if ($question->getMaxLength() !== null) maxlength="{{ $question->getMaxLength() }}" @endif
                                    @if ($question->isRequired()) required @endif
                                ></textarea>
                                @break

                            @case(QuestionType::Number)
                                <input
                                    id="{{ $inputId }}"
                                    type="number"
                                    name="{{ $fieldName }}"
                                    class="lss-input"
                                    @if ($question->getMin() !== null) min="{{ $question->getMin() }}" @endif
                                    @if ($question->getMax() !== null) max="{{ $question->getMax() }}" @endif
                                    @if ($question->getStep() !== null) step="{{ $question->getStep() }}" @endif
                                    @if ($question->getPlaceholder()) placeholder="{{ $question->getPlaceholder() }}" @endif
                                    @if ($question->isRequired()) required @endif
                                >
                                @break

                            @case(QuestionType::Select)
                                <select
                                    id="{{ $inputId }}"
                                    name="{{ $fieldName }}"
                                    class="lss-select"
                                    @if ($question->isRequired()) required @endif
                                >
                                    <option value="">{{ $question->getPlaceholder() ?? 'Select an option...' }}</option>
                                    @foreach ($question->getOptions() as $value => $optionLabel)
                                        <option value="{{ $value }}">{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                                @break

                            @case(QuestionType::Radio)
                                <div class="lss-options">
                                    @foreach ($question->getOptions() as $value => $optionLabel)
                                        <label class="lss-option-item">
                                            <input
                                                type="radio"
                                                name="{{ $fieldName }}"
                                                value="{{ $value }}"
                                                @if ($question->isRequired()) required @endif
                                            >
                                            <span>{{ $optionLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @break

                            @case(QuestionType::Checkbox)
                                <div class="lss-options">
                                    @foreach ($question->getOptions() as $value => $optionLabel)
                                        <label class="lss-option-item">
                                            <input
                                                type="checkbox"
                                                name="{{ $fieldName }}[]"
                                                value="{{ $value }}"
                                            >
                                            <span>{{ $optionLabel }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @break

                            @case(QuestionType::Rating)
                                @php
                                    $minRating = (int) ($question->getMin() ?? 1);
                                    $maxRating = (int) ($question->getMax() ?? 5);
                                @endphp
                                <div class="lss-rating-group">
                                    @for ($score = $minRating; $score <= $maxRating; $score++)
                                        <label class="lss-rating-item">
                                            <input
                                                type="radio"
                                                name="{{ $fieldName }}"
                                                value="{{ $score }}"
                                                @if ($question->isRequired()) required @endif
                                            >
                                            <span>{{ $score }}</span>
                                        </label>
                                    @endfor
                                </div>
                                @break

                            @case(QuestionType::Boolean)
                                <div class="lss-boolean-group">
                                    <label class="lss-boolean-item">
                                        <input type="radio" name="{{ $fieldName }}" value="1" @if ($question->isRequired()) required @endif>
                                        <span>Yes</span>
                                    </label>
                                    <label class="lss-boolean-item">
                                        <input type="radio" name="{{ $fieldName }}" value="0" @if ($question->isRequired()) required @endif>
                                        <span>No</span>
                                    </label>
                                </div>
                                @break
                        @endswitch

                        <p class="lss-error lss-hidden" data-lss-error="answers.{{ $question->key() }}"></p>
                    </div>
                @endforeach
            </div>

            <div class="lss-card-footer">
                @if ($activeSurvey->isDismissible())
                    <button type="button" class="lss-btn-link" data-lss-dismiss>
                        @if ($activeSurvey->getRepromptAfter() !== null)
                            Remind me later
                        @else
                            Don't show again
                        @endif
                    </button>
                @else
                    <span></span>
                @endif

                <button type="submit" class="lss-btn-primary" data-lss-submit>
                    {{ $activeSurvey->getSubmitLabel() }}
                </button>
            </div>
        </form>

        <div class="lss-thanks lss-hidden" data-lss-thanks>
            <div class="lss-thanks-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <p class="lss-thanks-msg" data-lss-thanks-msg>{{ $activeSurvey->getThankYouMessage() }}</p>
        </div>
    </div>

    <script>
        (function () {
            var root = document.getElementById(@json($widgetId));
            if (!root || root.dataset.lssInitialized === '1') {
                return;
            }
            root.dataset.lssInitialized = '1';

            var banner = root.querySelector('[data-lss-banner]');
            var card = root.querySelector('[data-lss-card]');
            var openBtn = root.querySelector('[data-lss-open]');
            var closeBtn = root.querySelector('[data-lss-close]');
            var dismissBtns = root.querySelectorAll('[data-lss-dismiss]');
            var form = root.querySelector('[data-lss-form]');
            var submitBtn = root.querySelector('[data-lss-submit]');
            var generalError = root.querySelector('[data-lss-general-error]');
            var thanksBox = root.querySelector('[data-lss-thanks]');
            var thanksMsg = root.querySelector('[data-lss-thanks-msg]');
            var submitUrl = root.dataset.lssSubmitUrl;
            var dismissUrl = root.dataset.lssDismissUrl;
            var openUrl = root.dataset.lssOpenUrl;
            var isSubmitted = false;
            var hasTrackedOpen = false;
            var removeTimer = null;

            function removeWidget() {
                if (removeTimer !== null) {
                    clearTimeout(removeTimer);
                    removeTimer = null;
                }
                root.remove();
            }

            function getCsrfToken() {
                var tokenInput = form ? form.querySelector('input[name="_token"]') : null;
                if (tokenInput && tokenInput.value) {
                    return tokenInput.value;
                }
                var meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.getAttribute('content') || '' : '';
            }

            function clearErrors() {
                if (generalError) {
                    generalError.textContent = '';
                    generalError.classList.add('lss-hidden');
                }
                root.querySelectorAll('[data-lss-error]').forEach(function (el) {
                    el.textContent = '';
                    el.classList.add('lss-hidden');
                });
            }

            function showGeneralError(message) {
                if (generalError) {
                    generalError.textContent = message;
                    generalError.classList.remove('lss-hidden');
                }
            }

            function openCard() {
                if (banner) banner.classList.add('lss-hidden');
                if (card) card.classList.remove('lss-hidden');
                if (openBtn) openBtn.setAttribute('aria-expanded', 'true');

                if (!hasTrackedOpen && openUrl) {
                    hasTrackedOpen = true;
                    fetch(openUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken()
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({})
                    }).catch(function () {});
                }
            }

            function closeCard() {
                if (isSubmitted) {
                    removeWidget();
                    return;
                }
                if (card) card.classList.add('lss-hidden');
                if (banner) banner.classList.remove('lss-hidden');
                if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
            }

            if (openBtn) {
                openBtn.addEventListener('click', openCard);
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', closeCard);
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && card && !card.classList.contains('lss-hidden')) {
                    closeCard();
                }
            });

            window.addEventListener('pageshow', function (event) {
                if (event.persisted && isSubmitted) {
                    removeWidget();
                }
            });

            dismissBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    fetch(dismissUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken()
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({})
                    }).then(function () {
                        root.remove();
                    }).catch(function () {
                        btn.disabled = false;
                    });
                });
            });

            if (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    clearErrors();

                    var answers = {};
                    var formData = new FormData(form);

                    formData.forEach(function (value, name) {
                        var arrayMatch = name.match(/^answers\[([^\]]+)\]\[\]$/);
                        if (arrayMatch) {
                            var arrayKey = arrayMatch[1];
                            if (!Array.isArray(answers[arrayKey])) {
                                answers[arrayKey] = [];
                            }
                            answers[arrayKey].push(value);
                            return;
                        }

                        var singleMatch = name.match(/^answers\[([^\]]+)\]$/);
                        if (singleMatch) {
                            answers[singleMatch[1]] = value;
                        }
                    });

                    var originalLabel = submitBtn ? submitBtn.textContent : '';
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Submitting...';
                    }

                    fetch(submitUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken()
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            answers: answers,
                            url: window.location.pathname
                        })
                    }).then(function (response) {
                        return response.json().catch(function () {
                            return {};
                        }).then(function (data) {
                            return { ok: response.ok, status: response.status, data: data };
                        });
                    }).then(function (result) {
                        if (result.ok) {
                            isSubmitted = true;
                            if (banner) {
                                banner.remove();
                            }
                            if (closeBtn) {
                                closeBtn.setAttribute('title', 'Close');
                            }
                            form.classList.add('lss-hidden');
                            if (thanksMsg && result.data && result.data.message) {
                                thanksMsg.textContent = result.data.message;
                            }
                            if (thanksBox) {
                                thanksBox.classList.remove('lss-hidden');
                            }
                            removeTimer = setTimeout(removeWidget, 3500);
                            return;
                        }

                        if (result.status === 422 && result.data && result.data.errors) {
                            Object.keys(result.data.errors).forEach(function (field) {
                                var normalizedField = field.replace(/\.\d+$/, '');
                                var errorEl = root.querySelector('[data-lss-error="' + normalizedField + '"]');
                                var messages = result.data.errors[field];
                                if (errorEl && Array.isArray(messages) && messages.length > 0) {
                                    errorEl.textContent = messages[0];
                                    errorEl.classList.remove('lss-hidden');
                                }
                            });
                        } else {
                            showGeneralError((result.data && result.data.message) || 'Something went wrong. Please try again.');
                        }

                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalLabel;
                        }
                    }).catch(function () {
                        showGeneralError('Network error. Please try again.');
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalLabel;
                        }
                    });
                });
            }
        })();
    </script>
</div>
