<x-app-layout :client="$client">
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
                <h3 class="font-semibold text-lg text-gray-800">{{ __('Post from a prompt') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('Write what the post should be about. Claude combines your words with the brand profile and the latest trend research and puts one post on the calendar as a Draft, with captions in both languages. You get an email with the link; from the post page you can download it or send it for approval.') }}</p>
                @if (session('status') === 'post-started')
                    <p class="mt-2 text-sm text-green-600">{{ __('The post is being written. It appears on the calendar as a Draft in a minute or two, a few minutes more when the trends are researched again.') }}</p>
                @endif
                @if ($accounts === [])
                    <p class="mt-2 text-sm text-red-600">{{ __('Fill in at least one account handle in the profile above and save first.') }}</p>
                @else
                    <form method="POST" action="{{ route('clients.draft', $client) }}" class="mt-4 grid gap-4 sm:grid-cols-4">
                        @csrf
                        <div class="sm:col-span-4">
                            <x-input-label for="instruction" :value="__('What the post is about')" />
                            <textarea id="instruction" name="instruction" rows="3" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" placeholder="{{ __('e.g. A Reel about the sunset cocktails this weekend, using whatever is trending on Instagram right now') }}" required>{{ old('instruction') }}</textarea>
                        </div>
                        <div>
                            <x-input-label for="date" :value="__('Date (optional, otherwise tomorrow)')" />
                            <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" :value="old('date')" min="{{ now()->toDateString() }}" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label :value="__('Platforms')" />
                            @foreach ($accounts as $platform)
                                <label class="block text-sm mt-1"><input type="checkbox" name="platforms[]" value="{{ $platform }}" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" @checked(in_array($platform, old('platforms', $accounts)))> {{ ucfirst(str_replace('_', ' ', $platform)) }}</label>
                            @endforeach
                        </div>
                        <div>
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="fresh" value="1" class="mt-1 rounded border-gray-300 text-brand-600 focus:ring-brand-500" @checked(old('fresh'))> {{ __('Research the trends again (otherwise the brief from the last 7 days is used)') }}</label>
                        </div>
                        <details class="sm:col-span-4">
                            <summary class="cursor-pointer text-sm text-gray-600">{{ __('Paste your own trend brief instead (optional)') }}</summary>
                            <textarea name="brief" rows="3" class="mt-2 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">{{ old('brief') }}</textarea>
                        </details>
                        <div class="sm:col-span-4">
                            <x-primary-button>{{ __('Write the post') }}</x-primary-button>
                            <x-input-error class="mt-2" :messages="$errors->draft->all()" />
                        </div>
                    </form>
                @endif
            </div>

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

            <div class="bg-white p-6 shadow-sm sm:rounded-lg mt-6">
                <h3 class="font-semibold text-lg text-gray-800">{{ __('Import posts from a spreadsheet') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('One row per post, the same columns as the calendar\'s CSV export. Every row becomes a Draft on the calendar; if any row is wrong, nothing is imported and the errors are listed here.') }} <a href="{{ route('posts.import.template', $client) }}" class="text-brand-600 hover:underline">{{ __('Download the template') }}</a></p>
                <form method="POST" action="{{ route('posts.import', $client) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
                    @csrf
                    <input type="file" name="csv" accept=".csv,text/csv" required class="text-sm" />
                    <x-primary-button>{{ __('Import') }}</x-primary-button>
                </form>
                <x-input-error class="mt-2" :messages="$errors->get('csv')" />
            </div>
        </div>
    </div>
</x-app-layout>
