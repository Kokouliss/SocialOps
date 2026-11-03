<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900">{{ $client->name }}</h1>
    <p class="mt-1 text-sm text-gray-600">{{ __('Connect your social accounts so we can publish for you. Each button opens the platform\'s own login: you log in there, we never see your password, and you tick which page or profile is this business\'s.') }}</p>

    @if (session('status'))
        <p class="mt-4 rounded-md bg-green-50 p-3 text-sm text-green-800">{{ __('Connected, thank you!') }}</p>
    @endif
    @error('connect') <p class="mt-4 text-sm text-red-600">{{ $message }}</p> @enderror

    <ul class="mt-4 divide-y divide-gray-100 text-sm">
        @foreach ($providers as $provider => $platforms)
            <li class="py-3 flex flex-wrap items-center gap-3">
                <span class="w-40 font-medium">{{ ['meta' => 'Facebook / Instagram', 'tiktok' => 'TikTok', 'google' => 'Google Business Profile'][$provider] }}</span>
                @php($connected = collect($platforms)->map(fn ($p) => $accounts->get($p)?->name)->filter())
                <span class="text-gray-500">{{ $connected->isNotEmpty() ? $connected->join(', ') : __('not connected') }}</span>
                <a href="{{ $links[$provider] }}" class="ml-auto inline-flex items-center px-3 py-1 border rounded text-xs hover:bg-gray-100">{{ $connected->isNotEmpty() ? __('Reconnect') : __('Connect') }}</a>
            </li>
        @endforeach
    </ul>
</x-guest-layout>
