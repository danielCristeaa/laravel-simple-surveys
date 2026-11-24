<div data-lss-dynamic-mount data-lss-widget-url="{{ $widgetEndpointUrl }}" wire:ignore>
    @if ($activeSurvey !== null)
        @include('simple-surveys::widget')
    @endif
</div>

<script>
    (function () {
        if (window.__lssDynamicBound) {
            return;
        }
        window.__lssDynamicBound = true;

        function mountWidgetHtml(mount, html) {
            var temp = document.createElement('div');
            temp.innerHTML = html;

            var nextWidget = temp.querySelector('[data-lss-widget]');
            var currentWidget = mount.querySelector('[data-lss-widget]');

            if (nextWidget && currentWidget && nextWidget.id && nextWidget.id === currentWidget.id) {
                return;
            }

            mount.innerHTML = html;

            mount.querySelectorAll('script').forEach(function (oldScript) {
                var newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(function (attr) {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.textContent = oldScript.textContent;
                if (oldScript.parentNode) {
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                }
            });
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
