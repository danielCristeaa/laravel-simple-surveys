@php
    $resolvedCspNonce = array_key_exists('cspNonce', get_defined_vars())
        ? (is_string($cspNonce) && trim($cspNonce) !== '' ? trim($cspNonce) : null)
        : app(\DanielCristea\SimpleSurveys\Services\SurveyCspNonceResolver::class)->resolve();
    $cspNonceAttr = $resolvedCspNonce !== null ? ' nonce="'.e($resolvedCspNonce).'"' : '';
    $dynamicNonceDataAttr = $resolvedCspNonce !== null ? ' data-lss-nonce="'.e($resolvedCspNonce).'"' : '';
@endphp

<div data-lss-dynamic-mount data-lss-widget-url="{{ $widgetEndpointUrl }}"{!! $dynamicNonceDataAttr !!} wire:ignore>
    @if ($activeSurvey !== null)
        @include('simple-surveys::widget', ['cspNonce' => $resolvedCspNonce])
    @endif
</div>

<script{!! $cspNonceAttr !!}>
    (function () {
        if (window.__lssDynamicBound) {
            return;
        }
        window.__lssDynamicBound = true;

        var hostScript = document.currentScript;
        var hostNonce = hostScript ? (hostScript.nonce || hostScript.getAttribute('nonce') || '') : '';

        function applyNonce(el, nonce) {
            if (!nonce) {
                return;
            }
            el.setAttribute('nonce', nonce);
            el.nonce = nonce;
        }

        function mountWidgetHtml(mount, html) {
            var temp = document.createElement('div');
            temp.innerHTML = html;

            var nextWidget = temp.querySelector('[data-lss-widget]');
            var currentWidget = mount.querySelector('[data-lss-widget]');

            if (
                nextWidget &&
                currentWidget &&
                nextWidget.id &&
                nextWidget.id === currentWidget.id &&
                (nextWidget.dataset.lssRespondent || '') === (currentWidget.dataset.lssRespondent || '')
            ) {
                return;
            }

            var activeNonce = mount.dataset.lssNonce || hostNonce || '';

            if (activeNonce) {
                temp.querySelectorAll('style').forEach(function (oldStyle) {
                    var newStyle = document.createElement('style');
                    Array.from(oldStyle.attributes).forEach(function (attr) {
                        newStyle.setAttribute(attr.name, attr.value);
                    });
                    applyNonce(newStyle, activeNonce);
                    newStyle.textContent = oldStyle.textContent;
                    if (oldStyle.parentNode) {
                        oldStyle.parentNode.replaceChild(newStyle, oldStyle);
                    }
                });
            }

            temp.querySelectorAll('script').forEach(function (oldScript) {
                var newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(function (attr) {
                    newScript.setAttribute(attr.name, attr.value);
                });
                applyNonce(newScript, activeNonce);
                newScript.textContent = oldScript.textContent;
                if (oldScript.parentNode) {
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                }
            });

            mount.innerHTML = '';
            while (temp.firstChild) {
                mount.appendChild(temp.firstChild);
            }
        }

        function refreshMount(mount) {
            var baseUrl = mount.dataset.lssWidgetUrl;
            if (!baseUrl) {
                return;
            }

            var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
            var url = baseUrl + separator + 'url=' + encodeURIComponent(window.location.pathname);

            fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }).then(function (response) {
                if (response.status === 204) {
                    mount.innerHTML = '';
                    return null;
                }
                if (!response.ok) {
                    return null;
                }
                return response.text();
            }).then(function (html) {
                if (html === null) {
                    return;
                }

                if (!html.trim()) {
                    mount.innerHTML = '';
                    return;
                }

                mountWidgetHtml(mount, html);
            }).catch(function () {});
        }

        function refreshAll() {
            document.querySelectorAll('[data-lss-dynamic-mount]').forEach(refreshMount);
        }

        document.addEventListener('inertia:navigate', refreshAll);
        document.addEventListener('livewire:navigated', refreshAll);
        document.addEventListener('simple-surveys:refresh', refreshAll);
        window.addEventListener('simple-surveys:refresh', refreshAll);
    })();
</script>
