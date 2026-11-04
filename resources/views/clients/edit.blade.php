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

            @if (config('features.claude_api'))
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

            @else
            <div class="bg-white p-6 shadow-sm sm:rounded-lg mt-6" x-data="{ posts: 12, from: '{{ now()->addMonthNoOverflow()->startOfMonth()->toDateString() }}', days: 30, instruction: '', copied: false,
                text() { return `{{ __('Plan :posts posts for :client (client ":slug") from :from for :days days.', ['posts' => '${this.posts}', 'client' => $client->name, 'slug' => $client->slug, 'from' => '${this.from}', 'days' => '${this.days}']) }}` + (this.instruction.trim() ? ' {{ __('About:') }} ' + this.instruction.trim() + '.' : '') + ` {{ __('Use the Social Ops tools: get-planning-brief first, research the current trends for this client (the last30days skill or web search), plan the posts and write the captions in :languages, then save-plan. Publish nothing.', ['languages' => strtoupper(implode(' + ', array_merge($client->profile['languages'], $client->profile['extra_languages'] ?? [])))]) }}`; },
                copy() { navigator.clipboard.writeText(this.text()).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }) } }">
                <h3 class="font-semibold text-lg text-gray-800">{{ __('Plan with Claude') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('Claude plans through the Social Ops connector: it reads this profile, researches the current trends, plans the posts with captions and files them on the calendar as Drafts with a review sheet in Reports. Fill in the fields, copy the request and paste it to Claude.') }} <a href="{{ route('staff.index') }}" class="text-brand-600 hover:underline">{{ __('Connector key: Staff page, API tokens') }}</a></p>
                <div class="mt-4 grid gap-4 sm:grid-cols-4">
                    <div><x-input-label for="mcp-posts" :value="__('Posts')" /><x-text-input id="mcp-posts" type="number" min="1" max="{{ \App\Planning\PlanRequest::MAX_POSTS }}" class="mt-1 block w-full" x-model="posts" /></div>
                    <div><x-input-label for="mcp-from" :value="__('From')" /><x-text-input id="mcp-from" type="date" class="mt-1 block w-full" x-model="from" /></div>
                    <div><x-input-label for="mcp-days" :value="__('Days')" /><x-text-input id="mcp-days" type="number" min="1" max="92" class="mt-1 block w-full" x-model="days" /></div>
                    <div class="sm:col-span-4"><x-input-label for="mcp-instruction" :value="__('What the posts are about (optional)')" /><textarea id="mcp-instruction" rows="2" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" x-model="instruction" placeholder="{{ __('e.g. December: gifts, Christmas opening hours, the New Year party') }}"></textarea></div>
                    <div class="sm:col-span-4 rounded-md bg-gray-50 p-3 text-sm text-gray-700" x-text="text()"></div>
                    <div class="sm:col-span-4"><x-primary-button type="button" x-on:click="copy()"><span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy the request') }}'"></span></x-primary-button></div>
                </div>
            </div>
            @endif
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

            <div id="bio" class="bg-white p-6 shadow-sm sm:rounded-lg mt-6">
                <h3 class="font-semibold text-lg text-gray-800">{{ __('Link in bio') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('Put this address in the Instagram and TikTok bio. The buttons come from the profile placeholders that hold a link, a phone number or an e-mail (booking, site, menu, phone, whatsapp…) and from the accounts.') }}</p>
                <div class="mt-3 flex gap-2">
                    <x-text-input type="text" class="block w-full font-mono text-xs" :value="route('bio', $client)" readonly onclick="this.select()" />
                    <a href="{{ route('bio', $client) }}" target="_blank" class="inline-flex items-center px-3 py-1 border rounded text-xs hover:bg-gray-100">{{ __('Open') }}</a>
                </div>
                <div class="mt-4 border-t pt-4">
                    <p class="text-sm text-gray-600">{{ __('Or put the same buttons on a page of the WordPress site of the client: connect with a WordPress user that may edit pages and an application password made for Social Ops (WordPress → Users → Profile → Application Passwords), then press Publish; press it again after changing the profile.') }}</p>
                    @if (session('status') === 'wordpress-published')
                        <p class="mt-2 text-sm text-green-600">{{ __('The page is on WordPress.') }}</p>
                    @elseif (session('status') === 'wordpress-connected')
                        <p class="mt-2 text-sm text-green-600">{{ __('WordPress connected. Press Publish to make the page.') }}</p>
                    @elseif (session('status') === 'wordpress-disconnected')
                        <p class="mt-2 text-sm text-green-600">{{ __('WordPress disconnected.') }}</p>
                    @endif
                    <x-input-error class="mt-2" :messages="$errors->wordpress->all()" />
                    @if ($wordpress)
                        <p class="mt-2 text-sm text-gray-800">{{ __('Connected to :site as :user', ['site' => $wordpress->name, 'user' => $wordpress->meta['user']]) }}@if ($wordpress->meta['url'] ?? null) · <a href="{{ $wordpress->meta['url'] }}" target="_blank" class="underline">{{ $wordpress->meta['url'] }}</a>@endif</p>
                        <div class="mt-2 flex gap-2">
                            <form method="post" action="{{ route('wordpress.publish', $client) }}">@csrf<x-primary-button>{{ ($wordpress->meta['page_id'] ?? null) ? __('Update on WordPress') : __('Publish to WordPress') }}</x-primary-button></form>
                            <form method="post" action="{{ route('wordpress.disconnect', $client) }}">@csrf @method('delete')<button type="submit" class="inline-flex items-center px-3 py-2 border rounded text-xs hover:bg-gray-100">{{ __('Disconnect') }}</button></form>
                        </div>
                    @else
                        <form method="post" action="{{ route('wordpress.connect', $client) }}" class="mt-2 grid gap-2 sm:grid-cols-3">
                            @csrf
                            <div><x-input-label for="wp-site" :value="__('Site address')" /><x-text-input id="wp-site" name="site" type="text" class="mt-1 block w-full" :value="old('site')" placeholder="https://client.gr" required /></div>
                            <div><x-input-label for="wp-user" :value="__('WordPress user')" /><x-text-input id="wp-user" name="user" type="text" class="mt-1 block w-full" :value="old('user')" required /></div>
                            <div><x-input-label for="wp-password" :value="__('Application password')" /><x-text-input id="wp-password" name="password" type="password" class="mt-1 block w-full" required autocomplete="off" /></div>
                            <div class="sm:col-span-3"><x-primary-button>{{ __('Connect') }}</x-primary-button></div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
