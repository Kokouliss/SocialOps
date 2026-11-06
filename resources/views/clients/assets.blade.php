<x-app-layout :client="$client">
    <x-slot name="header">
        <h2 class="text-2xl font-semibold tracking-tight text-gray-900">{{ $client->name }}: {{ __('assets') }}</h2>
    </x-slot>

    @php($btn = 'inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50')
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p class="text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif

            <div class="bg-white p-6 rounded-xl border border-gray-300">
                <x-input-label for="inbox-url" :value="__('Upload link for the client (no login, valid :days days, anyone with it can upload)', ['days' => \App\Http\Controllers\AssetController::LINK_DAYS])" />
                <x-text-input id="inbox-url" type="text" class="mt-1 block w-full font-mono text-xs" :value="$inboxUrl" readonly onclick="this.select()" />
            </div>

            <x-section title="Canva">
                <div class="py-5">
                @error('canva') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @if ($canva)
                    <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
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
                                        @if (($design['page_count'] ?? 1) > 1)<span class="text-gray-600">{{ $design['page_count'] }} {{ __('pages') }}</span>@endif
                                    </label>
                                @empty
                                    <p class="col-span-full text-sm text-gray-600">{{ __('No designs found.') }}</p>
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
                                    <span class="text-gray-600">{{ __('They appear in the list below within a minute or two; a failed import shows on the Jobs page and in Slack.') }}</span>
                                </div>
                            @endif
                        </form>
                    @endif
                @elseif ($canvaReady)
                    <p class="text-sm text-gray-600">{{ __('Bring finished designs straight into this inbox.') }} <a href="{{ route('canva.connect', $client) }}" class="{{ $btn }} ml-2">{{ __('Connect Canva') }}</a></p>
                @else
                    <p class="text-sm text-gray-600">{{ __('Add CANVA_CLIENT_ID and CANVA_CLIENT_SECRET to .env to import designs (docs/pilot-setup.md).') }}</p>
                @endif
                </div>
            </x-section>

            <x-section title="Google Drive">
                <div class="py-5">
                @error('drive') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @if ($drive)
                    <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
                        <span>{{ __('Watching the folder “:name”: new photos and videos land in this inbox every hour.', ['name' => $drive->name]) }}
                            @if ($drive->meta['pulled_at'] ?? null) {{ __('Last check :when, :n files in the folder.', ['when' => \Illuminate\Support\Carbon::parse($drive->meta['pulled_at'])->diffForHumans(), 'n' => $drive->meta['files'] ?? 0]) }} @endif</span>
                        <form method="POST" action="{{ route('folders.pull', [$client, 'drive']) }}">@csrf<button class="font-medium text-gray-900 hover:text-brand-600">{{ __('Check now') }}</button></form>
                        <form method="POST" action="{{ route('accounts.destroy', $drive) }}" onsubmit="return confirm('{{ __('Stop watching this folder?') }}')">@csrf @method('DELETE')<button class="text-red-600 hover:underline">{{ __('Stop watching') }}</button></form>
                    </div>
                @elseif ($driveEmail)
                    <p class="text-sm text-gray-600">{{ __('Share a Drive folder with :email (viewer is enough), then paste its link here. Photos and videos the client drops in it reach this inbox within the hour.', ['email' => $driveEmail]) }}</p>
                    <form method="POST" action="{{ route('drive.watch', $client) }}" class="mt-3 flex gap-2">@csrf
                        <x-text-input name="folder" type="text" class="block w-full text-sm" :value="old('folder')" placeholder="https://drive.google.com/drive/folders/…" />
                        <x-primary-button>{{ __('Watch folder') }}</x-primary-button>
                    </form>
                @else
                    <p class="text-sm text-gray-600">{{ __('Add GOOGLE_SERVICE_ACCOUNT_JSON to .env to pull files from a shared Drive folder (docs/pilot-setup.md).') }}</p>
                @endif
                </div>
            </x-section>

            <x-section title="Dropbox">
                <div class="py-5">
                @error('dropbox') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @if ($dropbox && ($dropbox->meta['folder'] ?? null))
                    <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
                        <span>{{ __('Watching the folder “:name”: new photos and videos land in this inbox every hour.', ['name' => $dropbox->meta['folder_name']]) }}
                            @if ($dropbox->meta['pulled_at'] ?? null) {{ __('Last check :when, :n files in the folder.', ['when' => \Illuminate\Support\Carbon::parse($dropbox->meta['pulled_at'])->diffForHumans(), 'n' => $dropbox->meta['files'] ?? 0]) }} @endif</span>
                        <form method="POST" action="{{ route('folders.pull', [$client, 'dropbox']) }}">@csrf<button class="font-medium text-gray-900 hover:text-brand-600">{{ __('Check now') }}</button></form>
                        <form method="POST" action="{{ route('accounts.destroy', $dropbox) }}" onsubmit="return confirm('{{ __('Stop watching this folder?') }}')">@csrf @method('DELETE')<button class="text-red-600 hover:underline">{{ __('Stop watching') }}</button></form>
                    </div>
                @elseif ($dropbox)
                    <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
                        <span>{{ __('Connected as :name.', ['name' => $dropbox->name]) }} {{ __('Which folder? Its path in that Dropbox, for example /Clients/Almyra. A folder the client shared sits at the top level.') }}</span>
                        <form method="POST" action="{{ route('accounts.destroy', $dropbox) }}" onsubmit="return confirm('{{ __('Disconnect Dropbox from this client?') }}')">@csrf @method('DELETE')<button class="text-red-600 hover:underline">{{ __('Disconnect') }}</button></form>
                    </div>
                    <form method="POST" action="{{ route('dropbox.watch', $client) }}" class="mt-3 flex gap-2">@csrf
                        <x-text-input name="folder" type="text" class="block w-full text-sm" :value="old('folder')" placeholder="/Clients/Almyra" />
                        <x-primary-button>{{ __('Watch folder') }}</x-primary-button>
                    </form>
                @elseif ($dropboxReady)
                    <p class="text-sm text-gray-600">{{ __('Bring the photos and videos a client drops in a shared Dropbox folder into this inbox.') }} <a href="{{ route('dropbox.connect', $client) }}" class="{{ $btn }} ml-2">{{ __('Connect Dropbox') }}</a></p>
                @else
                    <p class="text-sm text-gray-600">{{ __('Add DROPBOX_APP_KEY to .env to pull files from a Dropbox folder (docs/pilot-setup.md).') }}</p>
                @endif
                </div>
            </x-section>

            @if ($hasVideos)
                <x-section :title="__('Make a Reel')">
                    <div class="py-5">
                    @if ($reelReady)
                        <p class="text-sm text-gray-600">{{ __('Tick the clips below (2 to 10). They play in file-name order, each cut to the chosen seconds, vertical 1080×1920 without sound; add music in the Instagram app and give the result its final cut.') }}</p>
                        @if ($errors->reel->any())
                            <ul class="mt-2 list-disc pl-5 text-sm text-red-600">@foreach ($errors->reel->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        @endif
                        <form id="reel-form" method="POST" action="{{ route('assets.reel', $client) }}" class="mt-3 flex flex-wrap items-end gap-3 text-sm">
                            @csrf
                            <div>
                                <x-input-label for="reel-seconds" :value="__('Seconds per clip')" />
                                <select id="reel-seconds" name="seconds" class="mt-1 block border-gray-300 rounded-md shadow-sm text-sm">@foreach ([2, 3, 4, 5, 8, 10, 15] as $s)<option value="{{ $s }}" @selected((int) old('seconds', 3) === $s)>{{ $s }}</option>@endforeach</select>
                            </div>
                            <div class="grow">
                                <x-input-label for="reel-headline" :value="__('Headline on the first seconds (optional)')" />
                                <x-text-input id="reel-headline" name="headline" type="text" class="mt-1 block w-full text-sm" :value="old('headline')" maxlength="80" />
                            </div>
                            <div>
                                <x-input-label for="reel-pillar" :value="__('Pillar')" />
                                <select id="reel-pillar" name="pillar" class="mt-1 block border-gray-300 rounded-md shadow-sm text-sm"><option value="">—</option>@foreach (array_keys($client->profile['pillars']) as $pillar)<option value="{{ $pillar }}" @selected(old('pillar') === $pillar)>{{ ucfirst(str_replace('_', ' ', $pillar)) }}</option>@endforeach</select>
                            </div>
                            <x-primary-button>{{ __('Make the Reel') }}</x-primary-button>
                        </form>
                    @else
                        <p class="text-sm text-gray-600">{{ __('Install ffmpeg on the server to assemble Reels from clips (docs/pilot-setup.md).') }}</p>
                    @endif
                    </div>
                </x-section>
            @endif

            <x-section :title="__('Files').' ('.$assets->count().')'" flush>
                <form method="GET" action="{{ route('clients.assets', $client) }}" class="flex flex-wrap items-center gap-2 px-6 py-3 text-sm">
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
                <div class="grid grid-cols-2 gap-4 p-6 sm:grid-cols-3 lg:grid-cols-5">
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
                            <p class="text-gray-600">
                                {{ $asset->pillar ? str_replace('_', ' ', $asset->pillar) : __('no pillar') }} · {{ $asset->created_at->format('d-m-Y') }}
                                @if ($asset->season) · {{ $asset->season }} @endif
                                @if ($asset->people_consent) · <span class="text-green-700">{{ __('consent') }}</span> @endif
                                @if ($asset->ugc_permission) · <span class="text-green-700">{{ __('UGC ok') }}</span> @endif
                            </p>
                            @if ($asset->note) <p class="text-gray-600">{{ $asset->note }}</p> @endif
                            @if ($asset->kind === 'video' && $reelReady)
                                <label class="mt-1 block text-gray-700"><input type="checkbox" name="clips[]" value="{{ $asset->id }}" form="reel-form" class="rounded border-gray-300 text-brand-600" @checked(in_array($asset->id, old('clips', [])))> {{ __('In the Reel') }}</label>
                            @endif
                            <p class="mt-1">
                                @forelse ($asset->posts as $post)
                                    @if ($post->trashed())
                                        <a href="{{ route('clients.bin', $client) }}" class="block truncate text-gray-600 hover:text-brand-600" title="{{ $post->name }}">{{ __('In the recycle bin:') }} {{ $post->name }}</a>
                                    @else
                                        <a href="{{ route('posts.edit', $post) }}" class="block truncate font-medium text-gray-900 hover:text-brand-600" title="{{ $post->name }}">{{ __('In') }} {{ $post->name }}</a>
                                    @endif
                                @empty
                                    <span class="text-gray-500">{{ __('Not used in a post yet') }}</span>
                                @endforelse
                            </p>
                            <form method="POST" action="{{ route('assets.destroy', $asset) }}" class="mt-1" onsubmit="return confirm('{{ __('Delete this file?') }}')">
                                @csrf
                                @method('DELETE')
                                <a href="{{ route('assets.download', $asset) }}" class="font-medium text-gray-900 hover:text-brand-600">{{ __('Download') }}</a>
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
            </x-section>
        </div>
    </div>
</x-app-layout>
