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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
</body>
</html>
