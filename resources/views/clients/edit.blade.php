<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }} <span class="font-mono text-base text-gray-500">{{ $client->code }}</span></h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('clients.update', $client) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @method('PATCH')
                @include('clients._form')
            </form>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg mt-6">
                <h3 class="font-semibold text-lg text-gray-800">{{ __('Plan with Claude') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('Claude researches current trends for this client, follows the brand profile and puts the posts on the calendar as Drafts with captions in both languages. It takes a few minutes; you get an email when it is done. Nothing reaches the client without your review.') }}</p>
                @if (session('status') === 'plan-started')
                    <p class="mt-2 text-sm text-green-600">{{ __('Plan started. The Drafts appear on the calendar in a few minutes.') }}</p>
                @endif
                @if ($accounts === [])
                    <p class="mt-2 text-sm text-red-600">{{ __('Fill in at least one account handle in the profile above and save first.') }}</p>
                @else
                    <form method="POST" action="{{ route('clients.plan', $client) }}" class="mt-4 grid gap-4 sm:grid-cols-4">
                        @csrf
                        <div>
                            <x-input-label for="posts" :value="__('Posts')" />
                            <x-text-input id="posts" name="posts" type="number" min="1" max="{{ \App\Planning\PlanRequest::MAX_POSTS }}" class="mt-1 block w-full" :value="old('posts', 8)" />
                        </div>
                        <div>
                            <x-input-label for="from" :value="__('From')" />
                            <x-text-input id="from" name="from" type="date" class="mt-1 block w-full" :value="old('from', now()->addDay()->toDateString())" />
                        </div>
                        <div>
                            <x-input-label for="days" :value="__('Days')" />
                            <x-text-input id="days" name="days" type="number" min="1" max="92" class="mt-1 block w-full" :value="old('days', 30)" />
                        </div>
                        <div>
                            <x-input-label :value="__('Platforms')" />
                            @foreach ($accounts as $platform)
                                <label class="block text-sm mt-1"><input type="checkbox" name="platforms[]" value="{{ $platform }}" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" @checked(in_array($platform, old('platforms', $accounts)))> {{ ucfirst(str_replace('_', ' ', $platform)) }}</label>
                            @endforeach
                        </div>
                        <div class="sm:col-span-4">
                            <x-input-label for="brief" :value="__('Trend brief (optional)')" />
                            <textarea id="brief" name="brief" rows="3" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" placeholder="{{ __('Paste a trend brief here to skip the web research') }}">{{ old('brief') }}</textarea>
                        </div>
                        <div class="sm:col-span-4">
                            <x-primary-button>{{ __('Plan with Claude') }}</x-primary-button>
                            <x-input-error class="mt-2" :messages="$errors->all()" />
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
