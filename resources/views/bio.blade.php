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
        @media (prefers-color-scheme: dark) { body { background: #111827; color: #f3f4f6; } .socialops-bio .btn { background: #f3f4f6; color: #111827; } }
    </style>
</head>
<body>
    <main>
        <h1>{{ $client->name }}</h1>
        <p class="sub">{{ __(str_replace('_', ' ', $client->type)) }}@if ($client->location) · {{ $client->location }}@endif</p>
        @include('bio.fragment')
    </main>
</body>
</html>
