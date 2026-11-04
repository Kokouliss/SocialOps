<x-app-layout :client="$client">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }}: {{ __('assets') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <p class="text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <x-input-label for="inbox-url" :value="__('Upload link for the client (no login, valid :days days, anyone with it can upload)', ['days' => \App\Http\Controllers\AssetController::LINK_DAYS])" />
                <x-text-input id="inbox-url" type="text" class="mt-1 block w-full font-mono text-xs" :value="$inboxUrl" readonly onclick="this.select()" />
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Canva</h3>
                @error('canva') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @if ($canva)
                    <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-gray-600">
                        <span>{{ __('Connected as :name.', ['name' => $canva->name]) }}</span>
                        <form method="POST" action="{{ route('accounts.destroy', $canva) }}" onsubmit="return confirm('{{ __('Disconnect Canva from this client?') }}')">@csrf @method('DELETE')<button class="text-red-600 hover:underline">{{ __('Disconnect') }}</button></form>
                    </div>
                    <form method="GET" action="{{ route('clients.assets', $client) }}" class="mt-3 flex gap-2">
                        <x-text-input name="canva" type="text" class="block w-full text-sm" :value="request('canva')" placeholder="{{ __('Search designs, or leave empty for the newest') }}" />
                        <x-primary-button>{{ __('Find') }}</x-primary-button>
                    </form>
                    @if ($canvaError) <p class="mt-2 text-sm text-red-600">{{ $canvaError }}</p> @endif
                    @if ($designs !== null)
                        <form method="POST" action="{{ route('canva.import', $client) }}" class="mt-4 space-y-3">
                            @csrf
                            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                                @forelse ($designs as $design)
                                    <label class="block border rounded p-2 text-xs cursor-pointer hover:bg-gray-50">
                                        <input type="checkbox" name="designs[]" value="{{ $design['id'] }}" class="rounded border-gray-300 text-brand-600">
                                        <input type="hidden" name="titles[{{ $design['id'] }}]" value="{{ $design['title'] ?? '' }}">
                                        <input type="hidden" name="vertical[{{ $design['id'] }}]" value="{{ ($design['thumbnail']['height'] ?? 0) > ($design['thumbnail']['width'] ?? 0) ? 1 : 0 }}">
                                        @if (! empty($design['thumbnail']['url']))<img src="{{ $design['thumbnail']['url'] }}" alt="" class="mt-1 w-full aspect-square object-cover rounded">@endif
                                        <span class="block mt-1 truncate text-gray-900">{{ $design['title'] ?? __('Untitled') }}</span>
                                        @if (($design['page_count'] ?? 1) > 1)<span class="text-gray-500">{{ $design['page_count'] }} {{ __('pages') }}</span>@endif
                                    </label>
                                @empty
                                    <p class="col-span-full text-sm text-gray-500">{{ __('No designs found.') }}</p>
                                @endforelse
                            </div>
                            @if ($designs)
                                <div class="flex flex-wrap items-end gap-3 text-sm">
                                    <div>
                                        <x-input-label for="canva-type" :value="__('Import as')" />
                                        <select id="canva-type" name="type" class="mt-1 block border-gray-300 rounded-md shadow-sm text-sm"><option value="png">{{ __('PNG, one file per page') }}</option><option value="jpg">JPG</option><option value="mp4">{{ __('MP4 video') }}</option></select>
                                    </div>
                                    <div>
                                        <x-input-label for="canva-pillar" :value="__('Pillar')" />
                                        <select id="canva-pillar" name="pillar" class="mt-1 block border-gray-300 rounded-md shadow-sm text-sm"><option value="">—</option>@foreach (array_keys($client->profile['pillars']) as $pillar)<option value="{{ $pillar }}">{{ ucfirst(str_replace('_', ' ', $pillar)) }}</option>@endforeach</select>
                                    </div>
                                    <x-primary-button>{{ __('Import ticked designs') }}</x-primary-button>
                                    <span class="text-gray-500">{{ __('They appear in the list below within a minute or two; a failed import shows on the Jobs page and in Slack.') }}</span>
                                </div>
                            @endif
                        </form>
                    @endif
                @elseif ($canvaReady)
                    <p class="mt-1 text-sm text-gray-600">{{ __('Bring finished designs straight into this inbox.') }} <a href="{{ route('canva.connect', $client) }}" class="ml-2 inline-flex items-center px-3 py-1 border rounded text-xs hover:bg-gray-100">{{ __('Connect Canva') }}</a></p>
                @else
                    <p class="mt-1 text-sm text-gray-500">{{ __('Add CANVA_CLIENT_ID and CANVA_CLIENT_SECRET to .env to import designs (docs/pilot-setup.md).') }}</p>
                @endif
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="GET" action="{{ route('clients.assets', $client) }}" class="flex flex-wrap items-center gap-2 text-sm">
                    <h3 class="font-semibold text-gray-900 mr-2">{{ __('Files') }} <span class="font-normal text-gray-500">({{ $assets->count() }})</span></h3>
                    <select name="kind" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">{{ __('Photos and videos') }}</option>
                        <option value="image" @selected(request('kind') === 'image')>{{ __('Photos') }}</option>
                        <option value="video" @selected(request('kind') === 'video')>{{ __('Videos') }}</option>
                    </select>
                    <select name="pillar" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                        <option value="">{{ __('Any pillar') }}</option>
                        @foreach (array_keys($client->profile['pillars']) as $pillar)
                            <option value="{{ $pillar }}" @selected(request('pillar') === $pillar)>{{ ucfirst(str_replace('_', ' ', $pillar)) }}</option>
                        @endforeach
                    </select>
                </form>
                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    @forelse ($assets as $asset)
                        <div class="text-xs" data-asset="{{ $asset->id }}">
                            <a href="{{ route('assets.download', $asset) }}" class="block aspect-square overflow-hidden rounded bg-gray-900" title="{{ $asset->original_name }} · {{ number_format($asset->size / 1048576, 1) }} MB">
                                @if ($asset->kind === 'image')
                                    <img src="{{ route('assets.thumb', $asset) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                @else
                                    <span class="flex h-full w-full items-center justify-center text-gray-300">▶ {{ __('video') }}</span>
                                @endif
                            </a>
                            <p class="mt-1 truncate font-mono text-gray-900" title="{{ $asset->name }}">{{ $asset->name }}</p>
                            <p class="text-gray-500">
                                {{ $asset->pillar ? str_replace('_', ' ', $asset->pillar) : __('no pillar') }} · {{ $asset->created_at->translatedFormat('j M') }}
                                @if ($asset->season) · {{ $asset->season }} @endif
                                @if ($asset->people_consent) · <span class="text-green-700">{{ __('consent') }}</span> @endif
                                @if ($asset->ugc_permission) · <span class="text-green-700">{{ __('UGC ok') }}</span> @endif
                            </p>
                            @if ($asset->note) <p class="text-gray-500">{{ $asset->note }}</p> @endif
                            <p class="mt-1">
                                @forelse ($asset->posts as $post)
                                    <a href="{{ route('posts.edit', $post) }}" class="block truncate text-brand-600 hover:underline" title="{{ $post->name }}">{{ __('In') }} {{ $post->name }}</a>
                                @empty
                                    <span class="text-gray-400">{{ __('Not used in a post yet') }}</span>
                                @endforelse
                            </p>
                            <form method="POST" action="{{ route('assets.destroy', $asset) }}" class="mt-1" onsubmit="return confirm('{{ __('Delete this file?') }}')">
                                @csrf
                                @method('DELETE')
                                <a href="{{ route('assets.download', $asset) }}" class="text-brand-600 hover:underline">{{ __('Download') }}</a>
                                <button type="submit" class="ml-2 text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    @empty
                        @if (request()->hasAny(['kind', 'pillar']))
                            <x-empty-state class="col-span-full" :action="__('Show everything')" :href="route('clients.assets', $client)">{{ __('Nothing matches these filters.') }}</x-empty-state>
                        @else
                            <x-empty-state class="col-span-full" :action="__('Upload link')" href="#inbox-url">{{ __('Nothing uploaded yet. Send the client the upload link, drop files in yourself, or import designs from Canva.') }}</x-empty-state>
                        @endif
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
