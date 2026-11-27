@php
    $resolvedCspNonce = array_key_exists('cspNonce', get_defined_vars())
        ? (is_string($cspNonce) && trim($cspNonce) !== '' ? trim($cspNonce) : null)
        : app(\DanielCristea\SimpleSurveys\Services\SurveyCspNonceResolver::class)->resolve();
    $cspNonceAttr = $resolvedCspNonce !== null ? ' nonce="'.e($resolvedCspNonce).'"' : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Simple Surveys — Admin Results')</title>
    <script{!! $cspNonceAttr !!}>
        (function () {
            try {
                var stored = localStorage.getItem('lss-admin-theme') || 'system';
                var resolved = stored === 'system'
                    ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : stored;
                document.documentElement.setAttribute('data-theme-pref', stored);
                document.documentElement.setAttribute('data-theme', resolved === 'dark' ? 'dark' : 'light');
            } catch (e) {}
        })();
    </script>
    <style{!! $cspNonceAttr !!}>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --surface-subtle: #f1f5f9;
            --surface-hover: #f8fafc;
            --topbar-bg: rgba(255, 255, 255, 0.92);
            --table-head-bg: #f8fafc;
            --border: #e2e8f0;
            --border-strong: #cbd5e1;
            --text: #0f172a;
            --text-secondary: #334155;
            --muted: #64748b;
            --accent: #4f46e5;
            --accent-hover: #4338ca;
            --accent-soft: #eef2ff;
            --accent-ring: rgba(79, 70, 229, 0.16);
            --success-bg: #ecfdf5;
            --success-border: #a7f3d0;
            --success-text: #047857;
            --success-solid: #16a34a;
            --success-solid-hover: #15803d;
            --warn-bg: #fffbeb;
            --warn-border: #fde68a;
            --warn-text: #b45309;
            --warn-solid: #d97706;
            --warn-solid-hover: #b45309;
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --danger-text: #b91c1c;
            --danger-solid: #dc2626;
            --danger-solid-hover: #b91c1c;
            --slate-bg: #f1f5f9;
            --slate-text: #475569;
            --chart-bar: #4f46e5;
            --chart-bar-muted: #818cf8;
            --chart-yes: #10b981;
            --chart-no: #94a3b8;
            --chart-track: #e2e8f0;
            --chart-grid: #e2e8f0;
            --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.05);
            --shadow-md: 0 10px 28px -6px rgba(15, 23, 42, 0.12);
            --shadow-lg: 0 24px 48px -12px rgba(15, 23, 42, 0.25);
            --radius: 12px;
            --radius-sm: 8px;
            color-scheme: light;
        }

        html[data-theme="dark"] {
            --bg: #0b1120;
            --card: #111827;
            --surface-subtle: #1e293b;
            --surface-hover: #172133;
            --topbar-bg: rgba(17, 24, 39, 0.88);
            --table-head-bg: #0f172a;
            --border: #1e293b;
            --border-strong: #334155;
            --text: #f8fafc;
            --text-secondary: #cbd5e1;
            --muted: #94a3b8;
            --accent: #6366f1;
            --accent-hover: #818cf8;
            --accent-soft: rgba(99, 102, 241, 0.16);
            --accent-ring: rgba(99, 102, 241, 0.28);
            --success-bg: rgba(16, 185, 129, 0.14);
            --success-border: rgba(16, 185, 129, 0.35);
            --success-text: #34d399;
            --success-solid: #16a34a;
            --success-solid-hover: #15803d;
            --warn-bg: rgba(245, 158, 11, 0.14);
            --warn-border: rgba(245, 158, 11, 0.35);
            --warn-text: #fbbf24;
            --warn-solid: #d97706;
            --warn-solid-hover: #b45309;
            --danger-bg: rgba(239, 68, 68, 0.14);
            --danger-border: rgba(239, 68, 68, 0.35);
            --danger-text: #f87171;
            --danger-solid: #dc2626;
            --danger-solid-hover: #b91c1c;
            --slate-bg: #1e293b;
            --slate-text: #cbd5e1;
            --chart-bar: #818cf8;
            --chart-bar-muted: #4f46e5;
            --chart-yes: #34d399;
            --chart-no: #475569;
            --chart-track: #1e293b;
            --chart-grid: #1e293b;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 12px 30px -6px rgba(0, 0, 0, 0.45);
            --shadow-lg: 0 24px 52px -12px rgba(0, 0, 0, 0.65);
            color-scheme: dark;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.9em;
            background: var(--surface-subtle);
            color: var(--text-secondary);
            padding: 1px 5px;
            border-radius: 5px;
        }

        .muted { color: var(--muted); }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .tabular { font-variant-numeric: tabular-nums; }

        /* Topbar */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 40;
            background: var(--topbar-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            padding: 10px 24px;
        }
        .topbar-inner {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .topbar-left, .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }
        .brand {
            font-weight: 700;
            font-size: 15px;
            color: var(--text);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .brand:hover { text-decoration: none; }
        .brand-mark {
            width: 24px;
            height: 24px;
            border-radius: 7px;
            background: var(--accent);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .topbar-divider {
            width: 1px;
            height: 18px;
            background: var(--border);
        }
        .topbar-context {
            font-size: 12.5px;
            color: var(--muted);
            white-space: nowrap;
        }

        /* Theme switcher */
        .theme-switcher {
            display: inline-flex;
            align-items: center;
            padding: 2px;
            border-radius: 8px;
            background: var(--surface-subtle);
            border: 1px solid var(--border);
            gap: 2px;
        }
        .theme-btn {
            appearance: none;
            border: none;
            background: transparent;
            color: var(--muted);
            width: 28px;
            height: 26px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        .theme-btn:hover { color: var(--text); }
        .theme-btn.is-active {
            background: var(--card);
            color: var(--text);
            box-shadow: var(--shadow-sm);
        }

        /* Main container */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 20px 24px 56px;
        }

        /* Breadcrumb & Switcher */
        .breadcrumb-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
            font-size: 13px;
            color: var(--muted);
        }
        .breadcrumb-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: var(--muted);
            font-weight: 500;
        }
        .breadcrumb-link:hover {
            color: var(--text);
            text-decoration: none;
        }
        .breadcrumb-sep { color: var(--border-strong); }
        .survey-switcher {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--text);
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 600;
            padding: 4px 10px;
            cursor: pointer;
            max-width: 280px;
        }
        .survey-switcher:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }

        /* Page Header */
        .page-header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 16px;
        }
        .page-title-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .page-title {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.025em;
            line-height: 1.25;
        }
        .page-subtitle {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 13.5px;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Metadata Strip */
        .meta-strip {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
            font-size: 12px;
            color: var(--muted);
        }
        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 8px;
            border-radius: 6px;
            background: var(--surface-subtle);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }
        .meta-pill-accent {
            background: var(--accent-soft);
            border-color: transparent;
            color: var(--accent);
            font-weight: 600;
        }

        /* Priority Badge */
        .priority-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            padding: 2px 7px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            background: var(--surface-subtle);
            color: var(--muted);
            border: 1px solid var(--border);
        }
        .priority-badge.is-elevated {
            background: var(--accent-soft);
            color: var(--accent);
            border-color: transparent;
        }

        /* Cards */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px 18px;
            box-shadow: var(--shadow-sm);
        }
        .card-flush {
            padding: 0;
            overflow: hidden;
        }

        /* Compact Stat Strip */
        .stat-strip {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 16px;
        }
        .stat-strip-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        @media (max-width: 1024px) {
            .stat-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .stat-strip-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @media (max-width: 820px) {
            .stat-strip-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .stat-strip, .stat-strip-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 13px 15px;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-width: 0;
            position: relative;
            overflow: hidden;
        }
        .stat-label {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .stat-value-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            margin-top: 4px;
        }
        .stat-value {
            font-size: 23px;
            font-weight: 700;
            letter-spacing: -0.025em;
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
            color: var(--text);
        }
        .stat-sub {
            font-size: 11.5px;
            color: var(--muted);
            margin-top: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-variant-numeric: tabular-nums;
        }
        .stat-bar {
            height: 3px;
            width: 100%;
            background: var(--chart-track);
            border-radius: 9999px;
            margin-top: 8px;
            overflow: hidden;
        }
        .stat-bar-fill {
            height: 100%;
            border-radius: 9999px;
            background: var(--accent);
        }
        .stat-bar-fill-success { background: var(--chart-yes); }

        /* Analytics Two-Column Row */
        .analytics-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.35fr);
            gap: 14px;
            margin-bottom: 18px;
        }
        @media (max-width: 900px) {
            .analytics-row { grid-template-columns: 1fr; }
        }
        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }
        .panel-title {
            margin: 0;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text);
        }
        .panel-meta {
            font-size: 12px;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }

        /* Conversion Funnel */
        .funnel-steps {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .funnel-step-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12.5px;
            margin-bottom: 4px;
        }
        .funnel-step-name {
            font-weight: 600;
            color: var(--text-secondary);
        }
        .funnel-step-val {
            font-variant-numeric: tabular-nums;
            color: var(--muted);
        }
        .funnel-step-val strong {
            color: var(--text);
            font-weight: 700;
        }
        .funnel-track {
            height: 10px;
            border-radius: 9999px;
            background: var(--chart-track);
            overflow: hidden;
        }
        .funnel-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.25s ease;
        }
        .funnel-fill-seen { background: #64748b; }
        .funnel-fill-opened { background: var(--accent); }
        .funnel-fill-responses { background: var(--chart-yes); }
        .funnel-dropoff {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid var(--border);
            font-size: 12px;
            color: var(--muted);
        }
        .dropoff-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 6px;
            background: var(--surface-subtle);
            color: var(--text-secondary);
            font-variant-numeric: tabular-nums;
        }
        .dropoff-dot {
            width: 7px;
            height: 7px;
            border-radius: 9999px;
        }

        /* Trend SVG Chart */
        .trend-chart-wrap {
            position: relative;
            width: 100%;
        }
        .trend-svg {
            display: block;
            width: 100%;
            height: 148px;
            overflow: visible;
        }
        .trend-bar {
            fill: var(--chart-bar);
            opacity: 0.85;
            transition: opacity 0.12s ease;
            cursor: default;
        }
        .trend-bar:hover { opacity: 1; }
        .trend-bar-zero { fill: var(--chart-track); opacity: 0.7; }
        .trend-axis-label {
            fill: var(--muted);
            font-size: 10px;
            font-family: inherit;
        }
        .trend-grid-line {
            stroke: var(--chart-grid);
            stroke-width: 1;
            stroke-dasharray: 2 3;
        }
        .trend-readout {
            font-size: 12px;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }

        /* Locales strip */
        .locales-strip {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            padding: 10px 14px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 18px;
            box-shadow: var(--shadow-sm);
        }
        .locales-strip-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-right: 4px;
        }
        .locale-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 9px;
            border-radius: 9999px;
            border: 1px solid var(--border);
            background: var(--surface-subtle);
            color: var(--text);
            font-size: 12px;
            font-variant-numeric: tabular-nums;
            transition: border-color 0.15s ease, background-color 0.15s ease;
        }
        .locale-chip:hover {
            border-color: var(--accent);
            background: var(--accent-soft);
            text-decoration: none;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 9px;
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 600;
            text-transform: capitalize;
            white-space: nowrap;
        }
        .badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 9999px;
            background: currentColor;
        }
        .badge-active { background: var(--success-bg); color: var(--success-text); }
        .badge-scheduled { background: var(--accent-soft); color: var(--accent); }
        .badge-draft { background: var(--warn-bg); color: var(--warn-text); }
        .badge-closed { background: var(--slate-bg); color: var(--slate-text); }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 12px;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-weight: 600;
            font-size: 13px;
            line-height: 1.25;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--text);
            cursor: pointer;
            white-space: nowrap;
            transition: background-color 0.14s ease, border-color 0.14s ease, color 0.14s ease;
        }
        .btn:hover { background: var(--surface-subtle); text-decoration: none; }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .btn-icon-only {
            padding: 7px 9px;
        }
        .btn-primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #ffffff;
        }
        .btn-primary:hover { background: var(--accent-hover); border-color: var(--accent-hover); color: #ffffff; }
        .btn-danger {
            background: var(--danger-bg);
            border-color: var(--danger-border);
            color: var(--danger-text);
        }
        .btn-danger:hover { filter: brightness(0.96); }
        .btn-warning {
            background: var(--warn-bg);
            border-color: var(--warn-border);
            color: var(--warn-text);
        }
        .btn-warning:hover { filter: brightness(0.96); }
        .btn-success {
            background: var(--success-bg);
            border-color: var(--success-border);
            color: var(--success-text);
        }
        .btn-success:hover { filter: brightness(0.96); }
        .btn-danger-solid {
            background: var(--danger-solid);
            border-color: var(--danger-solid);
            color: #ffffff;
        }
        .btn-danger-solid:hover { background: var(--danger-solid-hover); color: #ffffff; }
        .btn-warning-solid {
            background: var(--warn-solid);
            border-color: var(--warn-solid);
            color: #ffffff;
        }
        .btn-warning-solid:hover { background: var(--warn-solid-hover); color: #ffffff; }
        .btn-success-solid {
            background: var(--success-solid);
            border-color: var(--success-solid);
            color: #ffffff;
        }
        .btn-success-solid:hover { background: var(--success-solid-hover); color: #ffffff; }

        /* Overflow Dropdown Menu */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            min-width: 196px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            padding: 5px;
            z-index: 60;
            display: none;
        }
        .dropdown.is-open .dropdown-menu { display: block; }
        .dropdown-item {
            width: 100%;
            appearance: none;
            border: none;
            background: transparent;
            color: var(--text);
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            padding: 8px 10px;
            border-radius: 7px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-align: left;
            text-decoration: none;
        }
        .dropdown-item:hover {
            background: var(--surface-subtle);
            text-decoration: none;
        }
        .dropdown-item-danger {
            color: var(--danger-text);
        }
        .dropdown-item-danger:hover {
            background: var(--danger-bg);
        }
        .dropdown-sep {
            height: 1px;
            background: var(--border);
            margin: 4px 0;
        }

        /* Alerts */
        .alert-status {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 13.5px;
            font-weight: 500;
        }
        .alert-error {
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 13.5px;
        }
        .alert-error ul { margin: 6px 0 0; padding-left: 18px; }
        .inline-form { display: inline-block; margin: 0; }

        /* Tabs Bar */
        .tabs-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            border-bottom: 1px solid var(--border);
            margin-bottom: 18px;
        }
        .tabs {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: -1px;
            overflow-x: auto;
        }
        .tab-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 14px;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--muted);
            border-bottom: 2px solid transparent;
            text-decoration: none;
            white-space: nowrap;
            transition: color 0.14s ease, border-color 0.14s ease;
        }
        .tab-link:hover { color: var(--text); text-decoration: none; }
        .tab-link.is-active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        .tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            padding: 1px 6px;
            border-radius: 9999px;
            background: var(--surface-subtle);
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }
        .tab-link.is-active .tab-count {
            background: var(--accent-soft);
            color: var(--accent);
        }

        /* Segmented controls (Date range / Preview) */
        .segmented {
            display: inline-flex;
            padding: 2px;
            border-radius: 8px;
            background: var(--surface-subtle);
            border: 1px solid var(--border);
            gap: 2px;
        }
        .segmented-btn {
            appearance: none;
            border: none;
            background: transparent;
            color: var(--muted);
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 9px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
        }
        .segmented-btn:hover { color: var(--text); text-decoration: none; }
        .segmented-btn.is-active {
            background: var(--card);
            color: var(--text);
            box-shadow: var(--shadow-sm);
        }

        /* Filter Toolbars */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            background: var(--card);
        }
        .filter-pills {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
        }
        .filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            border: 1px solid var(--border);
            background: var(--card);
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.14s ease;
        }
        .filter-pill:hover {
            color: var(--text);
            border-color: var(--border-strong);
            text-decoration: none;
        }
        .filter-pill.is-active {
            background: var(--accent-soft);
            border-color: transparent;
            color: var(--accent);
        }
        .filter-pill-count {
            font-size: 11px;
            opacity: 0.78;
            font-variant-numeric: tabular-nums;
        }
        .toolbar-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .search-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .search-icon {
            position: absolute;
            left: 10px;
            color: var(--muted);
            pointer-events: none;
            display: inline-flex;
        }
        .search-input {
            width: 240px;
            padding: 6px 10px 6px 31px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--bg);
            color: var(--text);
            font-family: inherit;
            font-size: 13px;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        }
        .search-input:focus {
            background: var(--card);
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }
        @media (max-width: 640px) {
            .search-input { width: 100%; min-width: 180px; }
        }
        .select-sm {
            padding: 6px 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--bg);
            color: var(--text);
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 500;
            outline: none;
            cursor: pointer;
        }
        .select-sm:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }
        .active-filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 12px;
            font-weight: 600;
        }
        .active-filter-chip a {
            color: var(--accent);
            font-weight: 700;
            line-height: 1;
            margin-left: 2px;
        }

        /* Tables */
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 11px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        th {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
            background: var(--table-head-bg);
            white-space: nowrap;
        }
        tbody tr {
            transition: background-color 0.1s ease;
        }
        tbody tr:hover {
            background: var(--surface-hover);
        }
        tr:last-child td { border-bottom: none; }

        .table-surveys th:first-child,
        .table-surveys td:first-child { padding-left: 18px; }
        .table-surveys th:last-child,
        .table-surveys td:last-child { padding-right: 18px; }
        .col-num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .col-center { text-align: center; white-space: nowrap; }
        .col-actions { text-align: right; white-space: nowrap; }

        .rate-cell {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 3px;
            min-width: 68px;
        }
        .mini-bar-track {
            width: 64px;
            height: 4px;
            border-radius: 9999px;
            background: var(--chart-track);
            overflow: hidden;
        }
        .mini-bar-fill {
            height: 100%;
            border-radius: 9999px;
            background: var(--accent);
        }
        .mini-bar-fill-success { background: var(--chart-yes); }

        /* Responses Table sticky & clickable */
        .table-responses th,
        .table-responses td {
            padding: 10px 12px;
            font-size: 13px;
        }
        .table-responses .sticky-col {
            position: sticky;
            left: 0;
            background: var(--card);
            z-index: 2;
            border-right: 1px solid var(--border);
        }
        .table-responses thead .sticky-col {
            background: var(--table-head-bg);
            z-index: 3;
        }
        .table-responses tbody tr:hover .sticky-col {
            background: var(--surface-hover);
        }
        .table-responses tbody tr.is-clickable {
            cursor: pointer;
        }
        .cell-truncate {
            max-width: 220px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Questions Grid & Cards */
        .questions-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .questions-heading {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .questions-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            align-items: stretch;
        }
        @media (max-width: 900px) {
            .questions-grid { grid-template-columns: 1fr; }
        }
        .question-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 15px 17px;
            box-shadow: var(--shadow-sm);
            scroll-margin-top: 72px;
            height: 260px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        @media (max-width: 900px) {
            .question-card {
                height: auto;
                max-height: 320px;
            }
        }
        .question-card.is-hidden-by-filter {
            display: none;
        }
        .question-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .question-body {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding-right: 4px;
            scrollbar-width: thin;
            scrollbar-color: var(--border-strong) transparent;
        }
        .question-body::-webkit-scrollbar {
            width: 5px;
        }
        .question-body::-webkit-scrollbar-track {
            background: transparent;
        }
        .question-body::-webkit-scrollbar-thumb {
            background: var(--border-strong);
            border-radius: 9999px;
        }
        .question-body-centered {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 100%;
        }
        .question-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 100%;
            gap: 6px;
            color: var(--muted);
            font-size: 13px;
            padding: 12px;
        }
        .question-index {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 6px;
            background: var(--surface-subtle);
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            margin-right: 6px;
            flex-shrink: 0;
        }
        .question-title {
            margin: 0;
            font-size: 14.5px;
            font-weight: 600;
            line-height: 1.35;
            color: var(--text);
        }
        .question-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 4px;
            font-size: 11.5px;
            color: var(--muted);
        }
        .question-type-pill {
            padding: 1px 6px;
            border-radius: 5px;
            background: var(--surface-subtle);
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .question-answer-count {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            padding: 2px 7px;
            border-radius: 6px;
            background: var(--surface-subtle);
        }

        /* Rating & Numeric Charts */
        .score-hero-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 8px;
            padding: 7px 10px;
            border-radius: 8px;
            background: var(--surface-subtle);
        }
        .score-hero-main {
            display: flex;
            align-items: baseline;
            gap: 6px;
        }
        .score-hero-value {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.02em;
            font-variant-numeric: tabular-nums;
            color: var(--text);
        }
        .score-hero-scale {
            font-size: 12px;
            color: var(--muted);
            font-weight: 500;
        }
        .stat-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .stat-pill {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 7px;
            padding: 3px 8px;
            font-size: 11.5px;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }
        .stat-pill strong {
            font-weight: 700;
            margin-left: 4px;
            color: var(--text);
        }

        .histogram-svg {
            display: block;
            width: 100%;
            height: 98px;
            overflow: visible;
        }
        .hist-bar {
            fill: var(--chart-bar);
            rx: 4;
            transition: opacity 0.14s ease;
        }
        .hist-bar-empty {
            fill: var(--chart-track);
        }
        .hist-bar-link:hover .hist-bar {
            opacity: 0.82;
        }
        .hist-label {
            fill: var(--muted);
            font-size: 10.5px;
            font-weight: 600;
            font-family: inherit;
        }
        .hist-count {
            fill: var(--text-secondary);
            font-size: 10px;
            font-weight: 600;
            font-family: inherit;
        }

        /* NPS Breakdown */
        .nps-box {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid var(--border);
        }
        .nps-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 6px;
        }
        .nps-score-badge {
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 9999px;
            font-variant-numeric: tabular-nums;
        }
        .nps-bar {
            display: flex;
            height: 9px;
            border-radius: 9999px;
            overflow: hidden;
            background: var(--chart-track);
            gap: 1px;
        }
        .nps-seg-det { background: #ef4444; }
        .nps-seg-pas { background: #f59e0b; }
        .nps-seg-pro { background: #10b981; }
        .nps-legend {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 6px;
            font-size: 11.5px;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }

        /* Boolean Donut + Split Bar */
        .boolean-visual {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .boolean-donut {
            width: 68px;
            height: 68px;
            flex-shrink: 0;
        }
        .boolean-bars {
            flex: 1;
            min-width: 0;
        }
        .split-bar {
            display: flex;
            height: 11px;
            border-radius: 9999px;
            overflow: hidden;
            background: var(--chart-track);
            margin-bottom: 10px;
        }
        .split-yes { background: var(--chart-yes); }
        .split-no { background: var(--chart-no); }
        .boolean-legend {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .boolean-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            color: var(--text);
            font-variant-numeric: tabular-nums;
            padding: 3px 8px;
            border-radius: 7px;
            background: var(--surface-subtle);
            text-decoration: none;
        }
        .boolean-item:hover {
            background: var(--accent-soft);
            text-decoration: none;
        }

        /* Choice / Checkbox Horizontal Bars */
        .bar-row {
            display: block;
            margin-bottom: 8px;
            padding: 4px 6px;
            margin-left: -6px;
            margin-right: -6px;
            border-radius: 7px;
            color: inherit;
            text-decoration: none;
            transition: background-color 0.12s ease;
        }
        .bar-row:last-child { margin-bottom: 0; }
        a.bar-row:hover {
            background: var(--surface-hover);
            text-decoration: none;
        }
        .bar-meta {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            font-size: 13px;
            margin-bottom: 4px;
        }
        .bar-label {
            color: var(--text);
            font-weight: 500;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .bar-stats {
            color: var(--muted);
            font-size: 12px;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .bar-stats strong { color: var(--text); font-weight: 600; }
        .bar-track {
            width: 100%;
            height: 7px;
            border-radius: 9999px;
            background: var(--chart-track);
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            border-radius: 9999px;
            background: var(--chart-bar);
        }
        .bar-fill-secondary {
            background: var(--chart-bar-muted);
            opacity: 0.78;
        }

        /* Text Question Scrollable Quote List */
        .quote-filter-input {
            width: 100%;
            padding: 6px 10px;
            margin-bottom: 8px;
            border: 1px solid var(--border);
            border-radius: 7px;
            background: var(--bg);
            color: var(--text);
            font-family: inherit;
            font-size: 12.5px;
            outline: none;
        }
        .quote-filter-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }
        .quote-list {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .quote-item {
            background: var(--surface-subtle);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 9px 11px;
            font-size: 13px;
            color: var(--text);
            word-break: break-word;
        }
        .quote-item.is-hidden { display: none; }
        .quote-time {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
            font-variant-numeric: tabular-nums;
        }
        .quote-footer {
            margin-top: 9px;
            font-size: 12px;
            display: flex;
            justify-content: flex-end;
        }

        .empty {
            text-align: center;
            padding: 36px 20px;
            color: var(--muted);
        }
        .empty-title {
            font-size: 14.5px;
            font-weight: 600;
            color: var(--text);
            margin: 0 0 4px;
        }
        .stack {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        /* Pagination Footer */
        .pagination-bar {
            padding: 12px 16px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .pagination-links {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
        }
        .page-num {
            min-width: 30px;
            height: 30px;
            padding: 0 8px;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--text);
            font-size: 12.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        .page-num:hover { background: var(--surface-subtle); text-decoration: none; }
        .page-num.is-active {
            background: var(--accent);
            border-color: var(--accent);
            color: #ffffff;
        }

        /* Confirm Modal */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(2, 6, 23, 0.56);
            backdrop-filter: blur(3px);
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
            box-shadow: var(--shadow-lg);
            padding: 20px;
            transform: scale(0.96);
            transition: transform 0.16s ease;
        }
        .modal-backdrop.is-open .modal-dialog { transform: scale(1); }
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
        .modal-icon-danger { background: var(--danger-bg); color: var(--danger-text); }
        .modal-icon-warning { background: var(--warn-bg); color: var(--warn-text); }
        .modal-icon-success { background: var(--success-bg); color: var(--success-text); }
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
            margin-top: 18px;
        }

        /* Slide-over Response Detail Drawer */
        .drawer-backdrop {
            position: fixed;
            inset: 0;
            z-index: 950;
            background: rgba(2, 6, 23, 0.52);
            backdrop-filter: blur(2px);
            display: flex;
            justify-content: flex-end;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.18s ease;
        }
        .drawer-backdrop.is-open {
            opacity: 1;
            pointer-events: auto;
        }
        .drawer-panel {
            width: 100%;
            max-width: 500px;
            height: 100%;
            background: var(--card);
            border-left: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.2s ease;
        }
        .drawer-backdrop.is-open .drawer-panel { transform: translateX(0); }
        .drawer-head {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .drawer-title {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }
        .drawer-body {
            padding: 18px 20px;
            overflow-y: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .drawer-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        .drawer-meta-item {
            padding: 9px 11px;
            border-radius: 8px;
            background: var(--surface-subtle);
            border: 1px solid var(--border);
        }
        .drawer-meta-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
        }
        .drawer-meta-val {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin-top: 2px;
            word-break: break-word;
        }
        .drawer-qa-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .drawer-qa-item {
            padding: 11px 13px;
            border-radius: 9px;
            border: 1px solid var(--border);
            background: var(--bg);
        }
        .drawer-qa-q {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .drawer-qa-a {
            font-size: 13.5px;
            color: var(--text);
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Appearance Editor Styles */
        .appearance-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(340px, 0.85fr);
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 960px) {
            .appearance-grid { grid-template-columns: 1fr; }
        }
        .section-heading {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
        }
        .section-desc {
            margin: 0 0 14px;
            font-size: 12.5px;
            color: var(--muted);
        }
        .collapsible-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .collapsible-summary {
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            cursor: pointer;
            user-select: none;
        }
        .collapsible-summary::-webkit-details-marker { display: none; }
        .collapsible-summary:hover { background: var(--surface-hover); }
        .collapsible-chevron {
            color: var(--muted);
            transition: transform 0.16s ease;
            flex-shrink: 0;
        }
        .collapsible-card[open] > .collapsible-summary .collapsible-chevron {
            transform: rotate(180deg);
        }
        .collapsible-body {
            padding: 0 18px 16px;
            border-top: 1px solid var(--border);
            padding-top: 14px;
        }
        .sticky-save-bar {
            position: sticky;
            bottom: 14px;
            z-index: 30;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 16px;
            border-radius: var(--radius);
            background: var(--card);
            border: 1px solid var(--border-strong);
            box-shadow: var(--shadow-md);
        }
        .unsaved-pill {
            display: none;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--warn-text);
            background: var(--warn-bg);
            padding: 3px 9px;
            border-radius: 9999px;
        }
        .unsaved-pill.is-visible { display: inline-flex; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
        }
        .form-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .form-field-full { grid-column: 1 / -1; }
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
            background: var(--surface-subtle);
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
            background: var(--bg);
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        }
        .form-control:focus {
            background: var(--card);
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-ring);
        }
        .form-error {
            font-size: 12px;
            color: var(--danger-text);
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
        .color-swatch.is-inherited { opacity: 0.45; }
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
        .clear-field-btn:hover { color: var(--text); background: var(--surface-subtle); }
        .preset-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 12px;
        }
        .preset-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border: 1px solid var(--border);
            border-radius: 9999px;
            background: var(--card);
            color: var(--text);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
        }
        .preset-chip:hover { background: var(--surface-subtle); border-color: var(--accent); }
        .preset-dot {
            width: 11px;
            height: 11px;
            border-radius: 9999px;
            border: 1px solid rgba(0, 0, 0, 0.16);
        }
        .icon-picker-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
            gap: 7px;
        }
        .icon-tile {
            appearance: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 9px 7px;
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
        .icon-tile:hover { background: var(--surface-hover); border-color: var(--border-strong); }
        .icon-tile.is-selected {
            border-color: var(--accent);
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 600;
            box-shadow: 0 0 0 2px var(--accent-ring);
        }
        .icon-tile-glyph {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: var(--surface-subtle);
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .icon-tile.is-selected .icon-tile-glyph {
            background: var(--accent-ring);
            color: var(--accent);
        }
        .icon-tile-glyph img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }
        .icon-custom-panel {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }
        .icon-custom-panel.is-hidden { display: none; }
        .icon-helper {
            margin: 6px 0 0;
            font-size: 12px;
            color: var(--muted);
        }
        .icon-preview-warning {
            margin-top: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            background: var(--warn-bg);
            border: 1px solid var(--warn-border);
            color: var(--warn-text);
            font-size: 12px;
            line-height: 1.45;
        }
        .icon-preview-warning.is-hidden { display: none; }
        .preview-sticky {
            position: sticky;
            top: 64px;
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
        .preview-canvas {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px 16px;
            min-height: 340px;
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

        :where(.stat-bar-fill, .mini-bar-fill, .funnel-fill, .bar-fill) { width: 0; }
        .m-0 { margin: 0; }
        .mb-6 { margin-bottom: 6px; }
        .mb-12 { margin: 0 0 12px; }
        .mt-2 { margin: 2px 0 0; }
        .mt-8 { margin-top: 8px; }
        .flex-1 { flex: 1; }
        .min-w-0 { min-width: 0; }
        .nowrap { white-space: nowrap; }
        .font-semibold { font-weight: 600; }
        .text-11 { font-size: 11px; }
        .text-115 { font-size: 11.5px; }
        .text-12 { font-size: 12px; }
        .text-125 { font-size: 12.5px; }
        .page-header-main { min-width: 0; flex: 1; }
        .survey-title-link { font-weight: 600; color: var(--text); }
        .survey-sub-meta { font-size: 12px; margin-top: 2px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .inline-actions { display: inline-flex; align-items: center; gap: 6px; }
        .question-head-main { display: flex; align-items: flex-start; min-width: 0; }
        .sticky-save-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .preset-dot-indigo { background: #4f46e5; }
        .preset-dot-emerald { background: #10b981; }
        .preset-dot-rose { background: #e11d48; }
        .preset-dot-amber { background: #d97706; }
        .preset-dot-sky { background: #0284c7; }
        .preset-dot-slate { background: #0f172a; }
        .dropoff-dot-abandoned { background: #f59e0b; }
        .dropoff-dot-dismissed { background: #94a3b8; }
        .dropoff-dot-yes { background: var(--chart-yes); }
        .dropoff-dot-no { background: var(--chart-no); }

        @media (max-width: 640px) {
            .topbar { padding: 10px 14px; }
            .container { padding: 16px 14px 48px; }
            .topbar-context, .topbar-divider { display: none; }
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="topbar-inner">
            <div class="topbar-left">
                <a href="{{ route('simple-surveys.admin.index') }}" class="brand">
                    <span class="brand-mark" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 11l3 3L22 4"></path>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                        </svg>
                    </span>
                    <span>Simple Surveys</span>
                </a>
                <span class="topbar-divider" aria-hidden="true"></span>
                <span class="topbar-context">Admin Results</span>
            </div>

            <div class="topbar-right">
                <div class="theme-switcher" role="group" aria-label="Dashboard color theme">
                    <button type="button" class="theme-btn" data-set-theme="light" title="Light theme" aria-label="Light theme">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="4"></circle>
                            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path>
                        </svg>
                    </button>
                    <button type="button" class="theme-btn" data-set-theme="system" title="System theme" aria-label="System theme">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21"></line>
                            <line x1="12" y1="17" x2="12" y2="21"></line>
                        </svg>
                    </button>
                    <button type="button" class="theme-btn" data-set-theme="dark" title="Dark theme" aria-label="Dark theme">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                    </button>
                </div>

                <a href="{{ url('/') }}" class="btn btn-sm">
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

    <script{!! $cspNonceAttr !!}>
        (function () {
            /* Theme switcher */
            var themeButtons = document.querySelectorAll('[data-set-theme]');
            var mediaQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

            function applyTheme(pref) {
                var resolved = pref === 'system'
                    ? (mediaQuery && mediaQuery.matches ? 'dark' : 'light')
                    : pref;
                document.documentElement.setAttribute('data-theme-pref', pref);
                document.documentElement.setAttribute('data-theme', resolved === 'dark' ? 'dark' : 'light');

                themeButtons.forEach(function (btn) {
                    var active = btn.dataset.setTheme === pref;
                    btn.classList.toggle('is-active', active);
                    btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }

            var initialPref = document.documentElement.getAttribute('data-theme-pref') || 'system';
            applyTheme(initialPref);

            themeButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var pref = btn.dataset.setTheme || 'system';
                    try {
                        localStorage.setItem('lss-admin-theme', pref);
                    } catch (e) {}
                    applyTheme(pref);
                });
            });

            if (mediaQuery && typeof mediaQuery.addEventListener === 'function') {
                mediaQuery.addEventListener('change', function () {
                    if ((document.documentElement.getAttribute('data-theme-pref') || 'system') === 'system') {
                        applyTheme('system');
                    }
                });
            }

            /* Dropdown menus */
            document.querySelectorAll('[data-dropdown-toggle]').forEach(function (toggle) {
                toggle.addEventListener('click', function (event) {
                    event.stopPropagation();
                    var container = toggle.closest('.dropdown');
                    if (!container) return;
                    var isOpen = container.classList.contains('is-open');
                    document.querySelectorAll('.dropdown.is-open').forEach(function (el) {
                        el.classList.remove('is-open');
                        var btn = el.querySelector('[data-dropdown-toggle]');
                        if (btn) btn.setAttribute('aria-expanded', 'false');
                    });
                    if (!isOpen) {
                        container.classList.add('is-open');
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                });
            });

            document.addEventListener('click', function (event) {
                if (!event.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown.is-open').forEach(function (el) {
                        el.classList.remove('is-open');
                        var btn = el.querySelector('[data-dropdown-toggle]');
                        if (btn) btn.setAttribute('aria-expanded', 'false');
                    });
                }
            });

            /* Survey switcher navigation */
            document.querySelectorAll('[data-survey-switcher]').forEach(function (select) {
                select.addEventListener('change', function () {
                    if (select.value) {
                        window.location.href = select.value;
                    }
                });
            });

            /* Auto-submit filter controls */
            document.querySelectorAll('[data-auto-submit]').forEach(function (control) {
                control.addEventListener('change', function () {
                    if (control.form) {
                        control.form.submit();
                    }
                });
            });

            /* Confirmation modal */
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
                    document.querySelectorAll('.dropdown.is-open').forEach(function (el) {
                        el.classList.remove('is-open');
                    });
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
                if (event.key === 'Escape') {
                    if (modal.classList.contains('is-open')) {
                        closeModal();
                    }
                    document.querySelectorAll('.dropdown.is-open').forEach(function (el) {
                        el.classList.remove('is-open');
                    });
                }
            });
        })();
    </script>
</body>
</html>
