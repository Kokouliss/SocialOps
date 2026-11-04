<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $client->name }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f6f5f2; color: #1f2937; display: flex; justify-content: center; }
        main { width: 100%; max-width: 420px; padding: 48px 20px; }
        h1 { font-size: 1.75rem; margin: 0 0 4px; text-align: center; }
        .sub { margin: 0 0 32px; text-align: center; opacity: .7; }
        .btn { display: block; padding: 16px; margin: 12px 0; border-radius: 14px; background: #1f2937; color: #fff; text-decoration: none; text-align: center; font-weight: 600; font-size: 1.05rem; }
        nav { display: flex; justify-content: center; gap: 20px; margin-top: 28px; }
        nav a { color: inherit; text-decoration: none; font-weight: 600; opacity: .8; }
        @media (prefers-color-scheme: dark) { body { background: #111827; color: #f3f4f6; } .btn { background: #f3f4f6; color: #111827; } }
    </style>
</head>
<body>
    <main>
        <h1>{{ $client->name }}</h1>
        <p class="sub">{{ __(str_replace('_', ' ', $client->type)) }}@if ($client->location) · {{ $client->location }}@endif</p>
        @foreach ($links as $link)
            @if ($link['kind'] !== 'social')
                <a class="btn" href="{{ $link['url'] }}" @if ($link['kind'] === 'link') target="_blank" rel="noopener" @endif>{{ $link['label'] }}</a>
            @endif
        @endforeach
        <nav>
            @foreach ($links as $link)
                @if ($link['kind'] === 'social')
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
                @endif
            @endforeach
        </nav>
    </main>
</body>
</html>
