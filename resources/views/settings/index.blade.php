<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold tracking-tight text-gray-900">{{ __('Settings') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status') === 'settings-saved')
                <p class="text-sm text-green-600">{{ __('Saved. The app runs on the new values from the next page; the queue from its next minute.') }}</p>
            @endif
            <x-input-error class="text-sm" :messages="$errors->all()" />
            <x-section :title="__('Server configuration')" :description="__('Every value of the server configuration (.env). FEATURE_ switches take true or false. A wrong database or APP_URL value locks the app; the previous file is kept on the server as .env.backup. APP_KEY cannot be changed here.')">
                <form method="POST" action="{{ route('settings.update') }}" class="divide-y divide-gray-200">
                    @csrf
                    @foreach ($values as $name => $value)
                        <div class="grid gap-1 py-2 sm:grid-cols-3 sm:items-center">
                            <label for="env-{{ $name }}" class="font-mono text-xs text-gray-700">{{ $name }}</label>
                            @if ($name === 'APP_KEY')
                                <x-text-input id="env-{{ $name }}" type="text" class="sm:col-span-2 block w-full text-sm" value="••••••••" disabled />
                            @else
                                <x-text-input id="env-{{ $name }}" name="values[{{ $name }}]" type="{{ preg_match('/KEY|SECRET|PASSWORD|TOKEN|WEBHOOK/', $name) ? 'password' : 'text' }}" class="sm:col-span-2 block w-full text-sm" :value="$value" autocomplete="off" />
                            @endif
                        </div>
                    @endforeach
                    <div class="grid gap-1 py-3 sm:grid-cols-3 sm:items-center">
                        <x-text-input name="new_name" type="text" class="block w-full font-mono text-xs" placeholder="NEW_VARIABLE" :value="old('new_name')" />
                        <x-text-input name="new_value" type="text" class="sm:col-span-2 block w-full text-sm" :placeholder="__('value')" :value="old('new_value')" />
                    </div>
                    <div class="py-4"><x-primary-button>{{ __('Save') }}</x-primary-button></div>
                </form>
            </x-section>
        </div>
    </div>
</x-app-layout>
