<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name', '勤怠管理') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
            max-width: 480px;
            width: 100%;
        }
        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-icon svg {
            width: 40px;
            height: 40px;
        }
        .error-code {
            font-size: 5rem;
            font-weight: 700;
            line-height: 1;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
        }
        .error-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        .error-message {
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .error-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s ease;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: #fff;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover {
            background: #f1f5f9;
        }
        .btn svg {
            width: 16px;
            height: 16px;
        }
        .divider {
            width: 48px;
            height: 3px;
            border-radius: 2px;
            margin: 0 auto 1.5rem;
        }

        /* Color themes */
        .theme-blue .error-icon { background: #eff6ff; }
        .theme-blue .error-icon svg { color: #3b82f6; }
        .theme-blue .error-code { color: #3b82f6; }
        .theme-blue .divider { background: #3b82f6; }

        .theme-amber .error-icon { background: #fffbeb; }
        .theme-amber .error-icon svg { color: #f59e0b; }
        .theme-amber .error-code { color: #f59e0b; }
        .theme-amber .divider { background: #f59e0b; }

        .theme-red .error-icon { background: #fef2f2; }
        .theme-red .error-icon svg { color: #ef4444; }
        .theme-red .error-code { color: #ef4444; }
        .theme-red .divider { background: #ef4444; }

        .theme-slate .error-icon { background: #f1f5f9; }
        .theme-slate .error-icon svg { color: #64748b; }
        .theme-slate .error-code { color: #64748b; }
        .theme-slate .divider { background: #94a3b8; }
    </style>
</head>
<body>
    <div class="error-container @yield('theme', 'theme-blue')">
        <div class="error-icon">
            @yield('icon')
        </div>
        <div class="error-code">@yield('code')</div>
        <div class="divider"></div>
        <h1 class="error-title">@yield('title')</h1>
        <p class="error-message">@yield('message')</p>
        <div class="error-actions">
            @yield('actions')
        </div>
    </div>
</body>
</html>
