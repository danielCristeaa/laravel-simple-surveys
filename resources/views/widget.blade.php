@php
    use DanielCristea\SimpleSurveys\Enums\QuestionType;
    use DanielCristea\SimpleSurveys\Services\WidgetStyles;
    /** @var \DanielCristea\SimpleSurveys\Survey $activeSurvey */
    $widgetId = 'lss-widget-' . \Illuminate\Support\Str::slug($activeSurvey->key());
    $resolvedPosition = $position ?? 'bottom-right';
    $resolvedTheme = $theme ?? 'light';
    $resolvedInlineStyle = $inlineStyle ?? '';
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
    data-lss-thank-you="{{ $activeSurvey->getThankYouMessage() }}"
>
    <style>{!! WidgetStyles::css() !!}</style>

    <div class="lss-backdrop lss-hidden" data-lss-backdrop aria-hidden="true"></div>

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

            function trackImpression() {
                if (!impressionUrl) {
                    return;
                }

                var storageKey = 'lss_seen_' + (root.dataset.lssSurvey || '');

                try {
                    if (window.sessionStorage && window.sessionStorage.getItem(storageKey) === '1') {
                        return;
                    }
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
                    body: JSON.stringify({})
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
                if (banner) banner.classList.add('lss-hidden');
                if (backdrop && root.dataset.lssPosition === 'center') backdrop.classList.remove('lss-hidden');
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
                if (backdrop) backdrop.classList.add('lss-hidden');
                if (banner) banner.classList.remove('lss-hidden');
                if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
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
