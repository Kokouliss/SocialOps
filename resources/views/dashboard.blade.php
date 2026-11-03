<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    @if (session('status'))
                        <p class="text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
                    @endif
                    <p>{{ __("You're logged in!") }}</p>
                    <form method="POST" action="{{ route('refresh') }}">
                        @csrf
                        <x-primary-button>{{ __('Refresh after update') }}</x-primary-button>
                        <p class="mt-2 text-sm text-gray-500">{{ __('Press this once after "Pull now" in Plesk Git: it runs new database migrations and rebuilds the caches.') }}</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
