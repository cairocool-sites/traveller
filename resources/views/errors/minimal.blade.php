<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('public.errors.generic.title') }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f8fafc; color: #0f172a; }
        main { min-height: 100vh; display: grid; place-items: center; padding: 2rem; }
        section { max-width: 34rem; }
        .code { color: #64748b; font-size: .875rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        h1 { font-size: clamp(2rem, 5vw, 3.5rem); margin: .5rem 0 1rem; }
        p { color: #475569; line-height: 1.7; }
    </style>
</head>
<body>
    <main>
        <section>
            <div class="code">{{ $code ?? 'ERROR' }}</div>
            <h1>{{ $title ?? __('public.errors.generic.title') }}</h1>
            <p>{{ $message ?? __('public.errors.generic.message') }}</p>
            @if (request()->attributes->get('correlation_id'))
                <p>{{ __('public.errors.reference') }}: {{ request()->attributes->get('correlation_id') }}</p>
            @endif
        </section>
    </main>
</body>
</html>
