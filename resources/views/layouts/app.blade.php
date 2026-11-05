<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <script>if (localStorage.theme === 'dark' || (!localStorage.theme && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark')</script>
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-900">
        <div class="min-h-screen bg-paper md:flex">
            @include('layouts.navigation')

            <div class="min-w-0 flex-1">
                @isset($header)
                    <header class="px-4 pt-6 sm:px-6 lg:px-8">
                        <div class="mx-auto max-w-7xl">
                            {{ $header }}
                            @if ($client)
                                @include('layouts.client-nav')
                            @endif
                            @isset($actions)
                                <div class="mt-4 flex flex-wrap items-center gap-2">{{ $actions }}</div>
                            @endisset
                        </div>
                    </header>
                @endisset

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
