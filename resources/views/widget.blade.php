@php
    use DanielCristea\SimpleSurveys\Enums\QuestionType;
    use DanielCristea\SimpleSurveys\Services\SurveyTranslator;
    use DanielCristea\SimpleSurveys\Services\WidgetStyles;
    use DanielCristea\SimpleSurveys\WidgetIcon;
    /** @var \DanielCristea\SimpleSurveys\Survey $activeSurvey */
    $translator = app(SurveyTranslator::class);
    $widgetId = 'lss-widget-' . \Illuminate\Support\Str::slug($activeSurvey->key());
    $resolvedPosition = $position ?? 'bottom-right';
    $resolvedTheme = $theme ?? 'light';
    $resolvedBannerIcon = $bannerIcon ?? WidgetIcon::fromConfig();
    $resolvedInlineStyle = $inlineStyle ?? '';
    $surveyTitle = $translator->title($activeSurvey);
    $surveyBannerText = $translator->bannerText($activeSurvey);
    $surveyDescription = $translator->description($activeSurvey);
    $surveySubmitLabel = $translator->submitLabel($activeSurvey);
    $surveyThankYouMessage = $translator->thankYouMessage($activeSurvey);
@endphp

<div
    id="{{ $widgetId }}"
    class="lss-root lss-pos-{{ $resolvedPosition }}"
    wire:ignore
    data-lss-widget
    data-lss-survey="{{ $activeSurvey->key() }}"
    data-lss-position="{{ $resolvedPosition }}"
    data-lss-theme="{{ $resolvedTheme }}"
    @if ($resolvedInlineStyle !== '') style="{{ $resolvedInlineStyle }}" @endif
    data-lss-submit-url="{{ $submitUrl }}"
    data-lss-dismiss-url="{{ $dismissUrl }}"
    data-lss-open-url="{{ $openUrl }}"
    @if (! empty($impressionUrl)) data-lss-impression-url="{{ $impressionUrl }}" @endif
    data-lss-thank-you="{{ $surveyThankYouMessage }}"
>
    <style>{!! WidgetStyles::css() !!}</style>

    <div class="lss-backdrop lss-hidden" data-lss-backdrop aria-hidden="true"></div>

    {{-- Collapsed Banner --}}
    <div class="lss-banner" data-lss-banner>
        @if ($resolvedBannerIcon->isPreset())
            <div class="lss-banner-icon" data-lss-icon-type="preset" data-lss-icon="{{ $resolvedBannerIcon->preset?->value }}" aria-hidden="true">
                {!! $resolvedBannerIcon->svg() !!}
            </div>
        @elseif ($resolvedBannerIcon->isAsset())
            <div class="lss-banner-icon" data-lss-icon-type="asset" aria-hidden="true">
                <img class="lss-banner-icon-img" src="{{ $resolvedBannerIcon->assetUrl() }}" alt="" decoding="async">
            </div>
        @endif
        <p class="lss-banner-text">{{ $surveyBannerText }}</p>
        <div class="lss-banner-actions">
            <button type="button" class="lss-btn-primary" data-lss-open aria-expanded="false">
                {{ $translator->ui('take_survey') }}
            </button>
            @if ($activeSurvey->isDismissible())
                <button type="button" class="lss-btn-icon" data-lss-dismiss data-lss-dismiss-source="banner" aria-label="{{ $translator->ui('dismiss_aria_label') }}" title="{{ $translator->ui('dismiss_title') }}">
                    &times;
                </button>
            @endif
        </div>
    </div>

    {{-- Expanded Survey Card --}}
    <div class="lss-card lss-hidden" data-lss-card role="dialog" aria-label="{{ $surveyTitle }}">
        <div class="lss-card-header">
            <div>
                <h3 class="lss-card-title">{{ $surveyTitle }}</h3>
                @if ($surveyDescription)
                    <p class="lss-card-desc">{{ $surveyDescription }}</p>
                @endif
            </div>
            <button type="button" class="lss-btn-icon" data-lss-close aria-label="{{ $translator->ui('close_aria_label') }}" title="{{ $translator->ui('minimize_title') }}">
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
                        $questionLabel = $translator->questionLabel($activeSurvey, $question);
                        $questionHelpText = $translator->questionHelpText($activeSurvey, $question);
                        $questionPlaceholder = $translator->questionPlaceholder($activeSurvey, $question);
                        $questionOptions = $translator->questionOptions($activeSurvey, $question);
                        $questionConditions = array_map(
                            static fn (\DanielCristea\SimpleSurveys\QuestionCondition $condition): array => $condition->toArray(),
                            $question->getConditions(),
                        );
                    @endphp

                    <div
                        class="lss-field{{ $questionConditions !== [] ? ' lss-hidden' : '' }}"
                        data-lss-field="{{ $question->key() }}"
                        data-lss-question-type="{{ $question->type()->value }}"
                        @if ($questionConditions !== [])
                            data-lss-conditions="{{ json_encode($questionConditions, JSON_THROW_ON_ERROR) }}"
                            aria-hidden="true"
                        @endif
                    >
                        <label class="lss-label" @if (in_array($question->type(), [QuestionType::Text, QuestionType::Textarea, QuestionType::Number, QuestionType::Select], true)) for="{{ $inputId }}" @endif>
                            {{ $questionLabel }}
                            @if ($question->isRequired())
                                <span class="lss-required" aria-hidden="true">*</span>
                            @endif
                        </label>

                        @if ($questionHelpText)
                            <p class="lss-help">{{ $questionHelpText }}</p>
                        @endif

                        @switch($question->type())
                            @case(QuestionType::Text)
                                <input
                                    id="{{ $inputId }}"
                                    type="text"
                                    name="{{ $fieldName }}"
                                    class="lss-input"
                                    @if ($questionPlaceholder) placeholder="{{ $questionPlaceholder }}" @endif
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
                                    @if ($questionPlaceholder) placeholder="{{ $questionPlaceholder }}" @endif
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
                                    @if ($questionPlaceholder) placeholder="{{ $questionPlaceholder }}" @endif
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
                                    <option value="">{{ $questionPlaceholder ?? $translator->ui('select_placeholder') }}</option>
                                    @foreach ($questionOptions as $value => $optionLabel)
                                        <option value="{{ $value }}">{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                                @break

                            @case(QuestionType::Radio)
                                <div class="lss-options">
                                    @foreach ($questionOptions as $value => $optionLabel)
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
                                    @foreach ($questionOptions as $value => $optionLabel)
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
                                        <span>{{ $translator->ui('yes') }}</span>
                                    </label>
                                    <label class="lss-boolean-item">
                                        <input type="radio" name="{{ $fieldName }}" value="0" @if ($question->isRequired()) required @endif>
                                        <span>{{ $translator->ui('no') }}</span>
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
                    <button type="button" class="lss-btn-link" data-lss-dismiss data-lss-dismiss-source="card">
                        @if ($activeSurvey->getRepromptAfter() !== null)
                            {{ $translator->ui('remind_me_later') }}
                        @else
                            {{ $translator->ui('dont_show_again') }}
                        @endif
                    </button>
                @else
                    <span></span>
                @endif

                <button type="submit" class="lss-btn-primary" data-lss-submit>
                    {{ $surveySubmitLabel }}
                </button>
            </div>
        </form>

        <div class="lss-thanks lss-hidden" data-lss-thanks>
            <div class="lss-thanks-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <p class="lss-thanks-msg" data-lss-thanks-msg>{{ $surveyThankYouMessage }}</p>
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
            var backdrop = root.querySelector('[data-lss-backdrop]');
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
            var impressionUrl = root.dataset.lssImpressionUrl || '';
            var surveyKey = root.dataset.lssSurvey || '';
            var submittingLabel = @json($translator->ui('submitting'));
            var closeTitle = @json($translator->ui('close_title'));
            var genericErrorMessage = @json($translator->ui('generic_error'));
            var networkErrorMessage = @json($translator->ui('network_error'));
            var isSubmitted = false;
            var hasTrackedOpen = false;
            var openedAt = null;
            var removeTimer = null;

            function elapsedDurationMs() {
                return openedAt !== null ? Math.max(0, Date.now() - openedAt) : null;
            }

            function emitSurveyEvent(name, detail) {
                var payload = {
                    surveyKey: surveyKey,
                    url: window.location.pathname
                };
                if (detail && typeof detail === 'object') {
                    Object.keys(detail).forEach(function (key) {
                        payload[key] = detail[key];
                    });
                }
                window.dispatchEvent(new CustomEvent('simple-surveys:' + name, {
                    detail: payload
                }));
            }

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

            function trackImpression() {
                var storageKey = 'lss_seen_' + surveyKey;
                var alreadySeen = false;

                try {
                    alreadySeen = Boolean(window.sessionStorage && window.sessionStorage.getItem(storageKey) === '1');
                } catch (e) {}

                emitSurveyEvent('seen', { firstInSession: !alreadySeen });

                if (!impressionUrl || alreadySeen) {
                    return;
                }

                try {
                    if (window.sessionStorage) {
                        window.sessionStorage.setItem(storageKey, '1');
                    }
                } catch (e) {}

                fetch(impressionUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        url: window.location.pathname
                    })
                }).then(function (response) {
                    if (!response.ok) {
                        try {
                            if (window.sessionStorage) {
                                window.sessionStorage.removeItem(storageKey);
                            }
                        } catch (e) {}
                    }
                }).catch(function () {
                    try {
                        if (window.sessionStorage) {
                            window.sessionStorage.removeItem(storageKey);
                        }
                    } catch (e) {}
                });
            }

            trackImpression();

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
                if (openedAt === null) {
                    openedAt = Date.now();
                }
                if (banner) banner.classList.add('lss-hidden');
                if (backdrop && root.dataset.lssPosition === 'center') backdrop.classList.remove('lss-hidden');
                if (card) card.classList.remove('lss-hidden');
                if (openBtn) openBtn.setAttribute('aria-expanded', 'true');

                emitSurveyEvent('opened');

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
                        body: JSON.stringify({
                            url: window.location.pathname
                        })
                    }).catch(function () {});
                }
            }

            function closeCard() {
                if (isSubmitted) {
                    removeWidget();
                    return;
                }
                if (card) card.classList.add('lss-hidden');
                if (backdrop) backdrop.classList.add('lss-hidden');
                if (banner) banner.classList.remove('lss-hidden');
                if (openBtn) openBtn.setAttribute('aria-expanded', 'false');

                emitSurveyEvent('minimized', { durationMs: elapsedDurationMs() });
            }

            if (openBtn) {
                openBtn.addEventListener('click', openCard);
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', closeCard);
            }

            if (backdrop) {
                backdrop.addEventListener('click', closeCard);
            }

            document.addEventListener('keydown', function (event) {
                if (!root.isConnected) {
                    return;
                }
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
                    var source = btn.dataset.lssDismissSource || null;
                    var durationMs = elapsedDurationMs();
                    fetch(dismissUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken()
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            source: source,
                            url: window.location.pathname
                        })
                    }).then(function () {
                        emitSurveyEvent('dismissed', {
                            source: source,
                            durationMs: durationMs
                        });
                        root.remove();
                    }).catch(function () {
                        btn.disabled = false;
                    });
                });
            });

            if (form) {
                var fieldMap = {};
                var conditionMap = {};
                var hasConditionalFields = false;

                form.querySelectorAll('[data-lss-field]').forEach(function (fieldEl) {
                    var key = fieldEl.dataset.lssField;
                    if (!key) {
                        return;
                    }
                    fieldMap[key] = fieldEl;
                    if (fieldEl.dataset.lssConditions) {
                        try {
                            var parsed = JSON.parse(fieldEl.dataset.lssConditions);
                            if (Array.isArray(parsed) && parsed.length > 0) {
                                conditionMap[key] = parsed;
                                hasConditionalFields = true;
                            }
                        } catch (e) {}
                    }
                });

                function isUnansweredValue(val) {
                    return val === null || val === undefined || val === '' || (Array.isArray(val) && val.length === 0);
                }

                function isNumericScalar(val) {
                    if (typeof val === 'number') {
                        return Number.isFinite(val);
                    }
                    if (typeof val === 'string' && val.trim() !== '') {
                        return Number.isFinite(Number(val));
                    }
                    return false;
                }

                function toBooleanValue(val) {
                    if (typeof val === 'boolean') {
                        return val;
                    }
                    if (typeof val === 'number') {
                        if (val === 1) return true;
                        if (val === 0) return false;
                        return null;
                    }
                    if (typeof val === 'string') {
                        var lower = val.trim().toLowerCase();
                        if (lower === '1' || lower === 'true' || lower === 'yes' || lower === 'on') return true;
                        if (lower === '0' || lower === 'false' || lower === 'no' || lower === 'off') return false;
                    }
                    return null;
                }

                function valuesMatch(actual, expected) {
                    if (typeof actual === 'boolean' || typeof expected === 'boolean') {
                        var actualBool = toBooleanValue(actual);
                        var expectedBool = toBooleanValue(expected);
                        return actualBool !== null && expectedBool !== null && actualBool === expectedBool;
                    }
                    if ((typeof actual === 'number' || typeof expected === 'number') && isNumericScalar(actual) && isNumericScalar(expected)) {
                        return Number(actual) === Number(expected);
                    }
                    return String(actual) === String(expected);
                }

                function matchesAnyInList(actual, list) {
                    if (Array.isArray(actual)) {
                        return actual.some(function (actualItem) {
                            return list.some(function (expectedItem) {
                                return valuesMatch(actualItem, expectedItem);
                            });
                        });
                    }
                    return list.some(function (expectedItem) {
                        return valuesMatch(actual, expectedItem);
                    });
                }

                function answerContains(actual, expected) {
                    if (Array.isArray(actual)) {
                        return actual.some(function (item) {
                            return valuesMatch(item, expected);
                        });
                    }
                    if (typeof actual === 'string' && typeof expected === 'string') {
                        return expected !== '' && actual.indexOf(expected) !== -1;
                    }
                    return false;
                }

                function matchesCondition(answer, operator, expected) {
                    if (isUnansweredValue(answer)) {
                        return false;
                    }
                    switch (operator) {
                        case '=':
                            return !Array.isArray(answer) && !Array.isArray(expected) && valuesMatch(answer, expected);
                        case '!=':
                            return !Array.isArray(answer) && !Array.isArray(expected) && !valuesMatch(answer, expected);
                        case '>':
                            return isNumericScalar(answer) && isNumericScalar(expected) && Number(answer) > Number(expected);
                        case '>=':
                            return isNumericScalar(answer) && isNumericScalar(expected) && Number(answer) >= Number(expected);
                        case '<':
                            return isNumericScalar(answer) && isNumericScalar(expected) && Number(answer) < Number(expected);
                        case '<=':
                            return isNumericScalar(answer) && isNumericScalar(expected) && Number(answer) <= Number(expected);
                        case 'in':
                            return Array.isArray(expected) && matchesAnyInList(answer, expected);
                        case 'not_in':
                            return Array.isArray(expected) && !matchesAnyInList(answer, expected);
                        case 'contains':
                            return !Array.isArray(expected) && answerContains(answer, expected);
                        case 'not_contains':
                            return !Array.isArray(expected) && !answerContains(answer, expected);
                        default:
                            return false;
                    }
                }

                function readFieldValue(fieldEl) {
                    var type = fieldEl.dataset.lssQuestionType || 'text';
                    if (type === 'checkbox') {
                        var selected = [];
                        fieldEl.querySelectorAll('input[type="checkbox"]:checked').forEach(function (input) {
                            selected.push(input.value);
                        });
                        return selected;
                    }
                    if (type === 'radio' || type === 'rating' || type === 'boolean') {
                        var checked = fieldEl.querySelector('input[type="radio"]:checked');
                        if (!checked) {
                            return null;
                        }
                        if (type === 'boolean') {
                            return checked.value === '1' ? true : (checked.value === '0' ? false : null);
                        }
                        if (type === 'rating') {
                            var score = Number(checked.value);
                            return Number.isInteger(score) ? score : null;
                        }
                        return checked.value;
                    }
                    if (type === 'select') {
                        var select = fieldEl.querySelector('select');
                        return select && select.value !== '' ? select.value : null;
                    }
                    if (type === 'number') {
                        var numInput = fieldEl.querySelector('input');
                        if (!numInput || numInput.value === '') {
                            return null;
                        }
                        var parsedNum = Number(numInput.value);
                        return Number.isFinite(parsedNum) ? parsedNum : null;
                    }
                    var textInput = fieldEl.querySelector('input, textarea');
                    return textInput && textInput.value !== '' ? textInput.value : null;
                }

                function syncConditionalFields() {
                    if (!hasConditionalFields) {
                        return;
                    }
                    var memo = {};
                    var visiting = {};

                    function isVisible(key) {
                        if (Object.prototype.hasOwnProperty.call(memo, key)) {
                            return memo[key];
                        }
                        var conditions = conditionMap[key];
                        if (!conditions || conditions.length === 0) {
                            memo[key] = true;
                            return true;
                        }
                        if (visiting[key]) {
                            return false;
                        }
                        visiting[key] = true;
                        var visible = true;
                        for (var i = 0; i < conditions.length; i++) {
                            var cond = conditions[i];
                            var targetField = fieldMap[cond.question];
                            if (!targetField || !isVisible(cond.question)) {
                                visible = false;
                                break;
                            }
                            if (!matchesCondition(readFieldValue(targetField), cond.operator, cond.value)) {
                                visible = false;
                                break;
                            }
                        }
                        delete visiting[key];
                        memo[key] = visible;
                        return visible;
                    }

                    Object.keys(conditionMap).forEach(function (key) {
                        var fieldEl = fieldMap[key];
                        if (!fieldEl) {
                            return;
                        }
                        var visible = isVisible(key);
                        fieldEl.classList.toggle('lss-hidden', !visible);
                        if (visible) {
                            fieldEl.removeAttribute('aria-hidden');
                        } else {
                            fieldEl.setAttribute('aria-hidden', 'true');
                            var errorEl = fieldEl.querySelector('[data-lss-error]');
                            if (errorEl) {
                                errorEl.textContent = '';
                                errorEl.classList.add('lss-hidden');
                            }
                        }
                        fieldEl.querySelectorAll('input, select, textarea').forEach(function (control) {
                            control.disabled = !visible;
                        });
                    });
                }

                if (hasConditionalFields) {
                    syncConditionalFields();
                    form.addEventListener('input', syncConditionalFields);
                    form.addEventListener('change', syncConditionalFields);
                }

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
                        submitBtn.textContent = submittingLabel;
                    }

                    var durationMs = elapsedDurationMs();
                    var submitPayload = {
                        answers: answers,
                        url: window.location.pathname
                    };
                    if (durationMs !== null) {
                        submitPayload.duration_ms = durationMs;
                    }

                    fetch(submitUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken()
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(submitPayload)
                    }).then(function (response) {
                        return response.json().catch(function () {
                            return {};
                        }).then(function (data) {
                            return { ok: response.ok, status: response.status, data: data };
                        });
                    }).then(function (result) {
                        if (result.ok) {
                            isSubmitted = true;
                            emitSurveyEvent('submitted', {
                                answers: answers,
                                durationMs: durationMs
                            });
                            if (banner) {
                                banner.remove();
                            }
                            if (closeBtn) {
                                closeBtn.setAttribute('title', closeTitle);
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
                            showGeneralError((result.data && result.data.message) || genericErrorMessage);
                        }

                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalLabel;
                        }
                    }).catch(function () {
                        showGeneralError(networkErrorMessage);
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
