<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Simple Surveys — Admin Results')</title>
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --accent: #4f46e5;
            --accent-soft: #eef2ff;
            --success-bg: #dcfce7;
            --success-text: #166534;
            --warn-bg: #fef3c7;
            --warn-text: #92400e;
            --slate-bg: #f1f5f9;
            --slate-text: #475569;
            --radius: 12px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            line-height: 1.5;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .topbar {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
        }
        .topbar-inner {
            max-width: 1120px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .brand {
            font-weight: 700;
            font-size: 16px;
            color: var(--text);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .brand:hover { text-decoration: none; }
        .brand-dot {
            width: 10px;
            height: 10px;
            border-radius: 9999px;
            background: var(--accent);
        }
        .container {
            max-width: 1120px;
            margin: 0 auto;
            padding: 28px 24px 64px;
        }
        .page-header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }
        .page-title {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .page-subtitle {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 14px;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
        }
        .kpi-label {
            font-size: 12.5px;
            font-weight: 500;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .kpi-value {
            font-size: 28px;
            font-weight: 700;
            margin-top: 6px;
            letter-spacing: -0.02em;
        }
        .kpi-hint {
            font-size: 11.5px;
            color: var(--muted);
            margin-top: 4px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 9px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        .badge-active { background: var(--success-bg); color: var(--success-text); }
        .badge-scheduled { background: var(--accent-soft); color: var(--accent); }
        .badge-draft { background: var(--warn-bg); color: var(--warn-text); }
        .badge-closed { background: var(--slate-bg); color: var(--slate-text); }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--text);
            cursor: pointer;
        }
        .btn:hover { background: var(--bg); text-decoration: none; }
        .btn-primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #ffffff;
        }
        .btn-primary:hover { background: #4338ca; color: #ffffff; }
        .btn-danger {
            background: #fef2f2;
            border-color: #fecaca;
            color: #b91c1c;
        }
        .btn-danger:hover { background: #fee2e2; color: #991b1b; }
        .btn-warning {
            background: #fffbeb;
            border-color: #fde68a;
            color: #b45309;
        }
        .btn-warning:hover { background: #fef3c7; color: #92400e; }
        .btn-success {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }
        .btn-success:hover { background: #dcfce7; color: #14532d; }
        .alert-status {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .inline-form {
            display: inline-block;
            margin: 0;
        }
        .modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.16s ease;
        }
        .modal-backdrop.is-open {
            opacity: 1;
            pointer-events: auto;
        }
        .modal-dialog {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.22);
            padding: 22px;
            transform: scale(0.96);
            transition: transform 0.16s ease;
        }
        .modal-backdrop.is-open .modal-dialog {
            transform: scale(1);
        }
        .modal-head {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .modal-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .modal-icon-danger {
            background: #fef2f2;
            color: #dc2626;
        }
        .modal-icon-warning {
            background: #fffbeb;
            color: #d97706;
        }
        .modal-icon-success {
            background: #f0fdf4;
            color: #16a34a;
        }
        .modal-title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }
        .modal-message {
            margin: 6px 0 0;
            font-size: 13.5px;
            color: var(--muted);
            line-height: 1.5;
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 20px;
        }
        .btn-danger-solid {
            background: #dc2626;
            border-color: #dc2626;
            color: #ffffff;
        }
        .btn-danger-solid:hover { background: #b91c1c; color: #ffffff; }
        .btn-warning-solid {
            background: #d97706;
            border-color: #d97706;
            color: #ffffff;
        }
        .btn-warning-solid:hover { background: #b45309; color: #ffffff; }
        .btn-success-solid {
            background: #16a34a;
            border-color: #16a34a;
            color: #ffffff;
        }
        .btn-success-solid:hover { background: #15803d; color: #ffffff; }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }
        th {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
            background: #f8fafc;
        }
        tr:last-child td { border-bottom: none; }
        .muted { color: var(--muted); }
        .bar-row {
            margin-bottom: 10px;
        }
        .bar-row:last-child { margin-bottom: 0; }
        .bar-meta {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 4px;
        }
        .bar-track {
            width: 100%;
            height: 8px;
            border-radius: 9999px;
            background: var(--slate-bg);
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            border-radius: 9999px;
            background: var(--accent);
        }
        .stat-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }
        .stat-pill {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
        }
        .stat-pill strong { font-weight: 700; margin-left: 4px; }
        .quote-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .quote-item {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13.5px;
        }
        .quote-time {
            font-size: 11.5px;
            color: var(--muted);
            margin-top: 4px;
        }
        .empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--muted);
        }
        .stack {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .pagination {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13.5px;
        }
        .alert-error ul {
            margin: 6px 0 0;
            padding-left: 18px;
        }
        .tabs {
            display: flex;
            align-items: center;
            gap: 4px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
        }
        .tab-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 14px;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--muted);
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            text-decoration: none;
            transition: color 0.15s ease, border-color 0.15s ease;
        }
        .tab-link:hover {
            color: var(--text);
            text-decoration: none;
        }
        .tab-link.is-active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        .appearance-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(340px, 0.85fr);
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 960px) {
            .appearance-grid {
                grid-template-columns: 1fr;
            }
        }
        .section-heading {
            margin: 0 0 4px;
            font-size: 15.5px;
            font-weight: 600;
        }
        .section-desc {
            margin: 0 0 16px;
            font-size: 13px;
            color: var(--muted);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        .form-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .form-field-full {
            grid-column: 1 / -1;
        }
        .form-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
        }
        .form-label {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text);
        }
        .token-tag {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 11px;
            color: var(--muted);
            background: var(--slate-bg);
            padding: 1px 6px;
            border-radius: 5px;
        }
        .form-control {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            color: var(--text);
            background: var(--card);
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }
        .form-error {
            font-size: 12px;
            color: #dc2626;
            margin: 0;
        }
        .color-input-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .color-swatch {
            width: 34px;
            height: 34px;
            padding: 2px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--card);
            cursor: pointer;
            flex-shrink: 0;
        }
        .color-swatch.is-inherited {
            opacity: 0.45;
        }
        .clear-field-btn {
            appearance: none;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--muted);
            width: 30px;
            height: 34px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            line-height: 1;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .clear-field-btn:hover {
            color: var(--text);
            background: var(--slate-bg);
        }
        .preset-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .preset-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border: 1px solid var(--border);
            border-radius: 9999px;
            background: var(--card);
            color: var(--text);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
        }
        .preset-chip:hover {
            background: var(--bg);
            border-color: var(--accent);
        }
        .preset-dot {
            width: 11px;
            height: 11px;
            border-radius: 9999px;
            border: 1px solid rgba(0, 0, 0, 0.12);
        }
        .icon-picker-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(92px, 1fr));
            gap: 8px;
        }
        .icon-tile {
            appearance: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 8px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--card);
            color: var(--text);
            font-family: inherit;
            font-size: 11.5px;
            font-weight: 500;
            cursor: pointer;
            text-align: center;
            transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
        }
        .icon-tile:hover {
            background: var(--bg);
            border-color: #cbd5e1;
        }
        .icon-tile.is-selected {
            border-color: var(--accent);
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 600;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.12);
        }
        .icon-tile-glyph {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: var(--slate-bg);
            color: var(--slate-text);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .icon-tile.is-selected .icon-tile-glyph {
            background: rgba(79, 70, 229, 0.12);
            color: var(--accent);
        }
        .icon-tile-glyph img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .icon-custom-panel {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }
        .icon-custom-panel.is-hidden {
            display: none;
        }
        .icon-helper {
            margin: 6px 0 0;
            font-size: 12px;
            color: var(--muted);
        }
        .icon-preview-warning {
            margin-top: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            font-size: 12px;
            line-height: 1.45;
        }
        .icon-preview-warning.is-hidden {
            display: none;
        }
        .preview-sticky {
            position: sticky;
            top: 20px;
        }
        .preview-toolbar {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 12px;
        }
        .preview-toolbar-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }
        .preview-placement-badge {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
        }
        .segmented {
            display: inline-flex;
            padding: 3px;
            border-radius: 8px;
            background: var(--slate-bg);
            border: 1px solid var(--border);
            gap: 2px;
        }
        .segmented-btn {
            appearance: none;
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
        }
        .segmented-btn.is-active {
            background: var(--card);
            color: var(--text);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }
        .preview-canvas {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px 16px;
            min-height: 360px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: auto;
            background-color: #f1f5f9;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 16px 16px;
            transition: background-color 0.15s ease;
        }
        .preview-canvas.is-dark-canvas {
            background-color: #020617;
            background-image: radial-gradient(#1e293b 1px, transparent 1px);
        }
        .preview-canvas .lss-root {
            position: static !important;
            inset: auto !important;
            transform: none !important;
            z-index: auto !important;
            max-width: 100% !important;
            width: auto !important;
            pointer-events: auto !important;
        }
        .preview-canvas .lss-pos-center .lss-banner,
        .preview-canvas .lss-pos-center .lss-card {
            position: static !important;
            top: auto !important;
            bottom: auto !important;
            left: auto !important;
            transform: none !important;
        }
        .code-snippet {
            margin: 8px 0 0;
            padding: 12px;
            border-radius: 8px;
            background: #0f172a;
            color: #e2e8f0;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            line-height: 1.55;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a href="{{ route('simple-surveys.admin.index') }}" class="brand">
                <span class="brand-dot"></span>
                <span>Simple Surveys</span>
            </a>
            <div style="display: flex; align-items: center; gap: 14px;">
                <span class="muted" style="font-size: 12.5px;">Admin Results</span>
                <a href="{{ url('/') }}" class="btn" style="padding: 5px 10px; font-size: 12.5px;">
                    &larr; Back to App
                </a>
            </div>
        </div>
    </header>

    <main class="container">
        @if (session('simple_surveys_admin_status'))
            <div class="alert-status" role="status">
                {{ session('simple_surveys_admin_status') }}
            </div>
        @endif

        @yield('content')
    </main>

    <div class="modal-backdrop" id="lss-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="lss-modal-title" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-head">
                <div class="modal-icon modal-icon-danger" id="lss-modal-icon" aria-hidden="true"></div>
                <div>
                    <h2 class="modal-title" id="lss-modal-title">Confirm Action</h2>
                    <p class="modal-message" id="lss-modal-message"></p>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn" id="lss-modal-cancel">Cancel</button>
                <button type="button" class="btn btn-danger-solid" id="lss-modal-confirm">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('lss-confirm-modal');
            var titleEl = document.getElementById('lss-modal-title');
            var messageEl = document.getElementById('lss-modal-message');
            var iconEl = document.getElementById('lss-modal-icon');
            var cancelBtn = document.getElementById('lss-modal-cancel');
            var confirmBtn = document.getElementById('lss-modal-confirm');
            var pendingForm = null;

            var dangerSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
            var warningSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
            var successSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';

            function openModal(form) {
                pendingForm = form;
                var variant = form.dataset.confirmVariant || 'danger';

                titleEl.textContent = form.dataset.confirmTitle || 'Confirm Action';
                messageEl.textContent = form.dataset.confirmMessage || 'Are you sure you want to continue?';
                confirmBtn.textContent = form.dataset.confirmButton || 'Confirm';

                if (variant === 'success') {
                    iconEl.className = 'modal-icon modal-icon-success';
                    iconEl.innerHTML = successSvg;
                    confirmBtn.className = 'btn btn-success-solid';
                } else if (variant === 'warning') {
                    iconEl.className = 'modal-icon modal-icon-warning';
                    iconEl.innerHTML = warningSvg;
                    confirmBtn.className = 'btn btn-warning-solid';
                } else {
                    iconEl.className = 'modal-icon modal-icon-danger';
                    iconEl.innerHTML = dangerSvg;
                    confirmBtn.className = 'btn btn-danger-solid';
                }

                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                confirmBtn.focus();
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                pendingForm = null;
            }

            document.querySelectorAll('form[data-confirm-message]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (form.dataset.confirmed === '1') {
                        return;
                    }
                    event.preventDefault();
                    openModal(form);
                });
            });

            cancelBtn.addEventListener('click', closeModal);

            confirmBtn.addEventListener('click', function () {
                if (!pendingForm) return;
                var formToSubmit = pendingForm;
                formToSubmit.dataset.confirmed = '1';
                closeModal();
                formToSubmit.submit();
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                    closeModal();
                }
            });
        })();
    </script>
</body>
</html>
