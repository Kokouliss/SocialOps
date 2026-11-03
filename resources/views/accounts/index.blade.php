<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }}: {{ __('connected accounts') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <p class="text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif
            @error('connect') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100 text-sm">
                @foreach ($providers as $provider => $platforms)
                    @foreach ($platforms as $platform)
                        @php($account = $accounts->get($platform))
                        <div class="px-6 py-4 flex flex-wrap items-center gap-4">
                            <span class="w-40 font-medium">{{ str_replace('_', ' ', ucfirst($platform)) }}</span>
                            @if ($account)
                                <span>{{ $account->name }}</span>
                                <span class="text-xs {{ $account->token_expires_at?->isPast() ? 'text-red-600' : 'text-gray-500' }}">
                                    {{ $account->token_expires_at ? __('token until :date', ['date' => $account->token_expires_at->format('j M Y')]) : __('token does not expire') }}
                                </span>
                                <form method="POST" action="{{ route('accounts.destroy', $account) }}" class="ml-auto" onsubmit="return confirm('{{ __('Disconnect this account?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">{{ __('Disconnect') }}</button>
                                </form>
                            @else
                                <span class="text-gray-400">{{ __('not connected') }}</span>
                            @endif
                            @if ($loop->first)
                                <a href="{{ route('accounts.connect', [$client, $provider]) }}" class="{{ $account ? '' : 'ml-auto' }} inline-flex items-center px-3 py-1 border rounded text-xs hover:bg-gray-100">{{ $account ? __('Reconnect') : __('Connect') }} {{ ['meta' => 'Facebook / Instagram', 'tiktok' => 'TikTok', 'google' => 'Google'][$provider] }}</a>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
            <p class="text-xs text-gray-500">{{ __('Connecting opens the platform\'s own login; we never see or store a password. The profile under "accounts" in the brand profile is only the handle for reference.') }}</p>
        </div>
    </div>
</x-app-layout>
