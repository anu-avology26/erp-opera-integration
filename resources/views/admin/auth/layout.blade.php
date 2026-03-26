<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') – {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <style>
        :root {
            --auth-bg: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0d9488 100%);
            --auth-card: #ffffff;
            --auth-card-shadow: 0 25px 50px -12px rgba(0,0,0,.25);
            --auth-primary: #0d9488;
            --auth-primary-hover: #0f766e;
            --auth-text: #0f172a;
            --auth-muted: #64748b;
            --auth-border: #e2e8f0;
            --auth-error: #dc2626;
            --auth-success: #059669;
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; background: var(--auth-bg); color: var(--auth-text); display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .auth-box { width: 100%; max-width: 420px; background: var(--auth-card); border-radius: 1rem; box-shadow: var(--auth-card-shadow); padding: 2rem; }
        .auth-logo { font-size: 1.5rem; font-weight: 700; color: var(--auth-primary); margin-bottom: 0.5rem; }
        .auth-title { font-size: 1.25rem; font-weight: 600; margin: 0 0 0.25rem 0; }
        .auth-subtitle { color: var(--auth-muted); font-size: 0.9rem; margin: 0 0 1.5rem 0; }
        .auth-form .form-group { margin-bottom: 1.25rem; }
        .auth-form label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.35rem; color: var(--auth-text); }
        .auth-form input[type="email"], .auth-form input[type="text"], .auth-form input[type="password"] {
            width: 100%; padding: 0.6rem 0.75rem; border: 1px solid var(--auth-border); border-radius: 0.5rem; font-size: 1rem; font-family: inherit;
        }
        .auth-form input:focus { outline: none; border-color: var(--auth-primary); box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.2); }
        .auth-form .error { font-size: 0.8rem; color: var(--auth-error); margin-top: 0.25rem; }
        .auth-form .checkbox { display: flex; align-items: center; gap: 0.5rem; }
        .auth-form .checkbox input { width: auto; }
        .btn-auth { width: 100%; padding: 0.75rem 1rem; background: var(--auth-primary); color: #fff; border: none; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; font-family: inherit; transition: background .2s; }
        .btn-auth:hover { background: var(--auth-primary-hover); }
        .auth-footer { margin-top: 1.5rem; text-align: center; font-size: 0.875rem; }
        .auth-footer a { color: var(--auth-primary); text-decoration: none; }
        .auth-footer a:hover { text-decoration: underline; }
        .auth-message { padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem; }
        .auth-message.success { background: #d1fae5; color: var(--auth-success); }
        .auth-message.error { background: #fee2e2; color: var(--auth-error); }
    </style>
    @stack('head')
</head>
<body>
    <div class="auth-box">
        <div class="auth-logo">ERP ↔ Opera</div>
        @yield('content')
    </div>
</body>
</html>
