@csrf
@php($languages = array_merge($client->profile['languages'], $client->profile['extra_languages'] ?? []))
@php($selected = old('platforms', $post->platforms ?? []))
<div class="grid gap-6 md:grid-cols-2">
    <div>
        <x-input-label :value="__('Platforms')" />
        <div class="mt-2 flex flex-wrap gap-4 text-sm">
            @foreach (\App\Models\Post::PLATFORMS as $platform)
                <label class="flex items-center gap-1 {{ empty($client->profile['accounts'][$platform]) ? 'text-gray-400' : '' }}">
                    <input type="checkbox" name="platforms[]" value="{{ $platform }}" class="rounded border-gray-300" @checked(in_array($platform, $selected)) />
                    {{ str_replace('_', ' ', $platform) }}
                </label>
            @endforeach
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('platforms')" />
    </div>
    <div>
        <x-input-label for="format" :value="__('Format')" />
        <select id="format" name="format" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            @foreach (\App\Models\Post::FORMATS as $format)
                <option value="{{ $format }}" @selected(old('format', $post->format) === $format)>{{ str_replace('_', ' ', $format) }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('format')" />
    </div>
    <div>
        <x-input-label for="pillar" :value="__('Pillar')" />
        <select id="pillar" name="pillar" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
            <option value="">—</option>
            @foreach (array_keys($client->profile['pillars']) as $pillar)
                <option value="{{ $pillar }}" @selected(old('pillar', $post->pillar) === $pillar)>{{ str_replace('_', ' ', $pillar) }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('pillar')" />
    </div>
    <div>
        <x-input-label for="scheduled_at" :value="__('Scheduled for')" />
        <x-text-input id="scheduled_at" name="scheduled_at" type="datetime-local" class="mt-1 block w-full" :value="old('scheduled_at', $post->scheduled_at?->format('Y-m-d\TH:i'))" />
        <x-input-error class="mt-2" :messages="$errors->get('scheduled_at')" />
    </div>
</div>
<div class="mt-6">
    <x-input-label for="brief" :value="__('Brief (what the post is about, for the drafter and the reviewer)')" />
    <textarea id="brief" name="brief" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('brief', $post->brief) }}</textarea>
    <x-input-error class="mt-2" :messages="$errors->get('brief')" />
</div>

@if ($client->assets->isNotEmpty())
    <div class="mt-6">
        <x-input-label :value="__('Assets')" />
        @php($chosen = old('assets', $post->exists ? $post->assets->pluck('id')->map(fn ($id) => (string) $id)->all() : []))
        <div class="mt-2 grid gap-1 md:grid-cols-2 text-xs font-mono">
            @foreach ($client->assets as $asset)
                <label class="flex items-center gap-2"><input type="checkbox" name="assets[]" value="{{ $asset->id }}" class="rounded border-gray-300" @checked(in_array((string) $asset->id, $chosen)) /> {{ $asset->name }}</label>
            @endforeach
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('assets')" />
    </div>
@endif

<h3 class="mt-8 font-semibold text-gray-800">{{ __('Captions') }} <span class="text-sm font-normal text-gray-500">{{ __('(only the ticked platforms are saved; Greek is reviewed by a human before approval)') }}</span></h3>
@foreach (\App\Models\Post::PLATFORMS as $platform)
    <fieldset class="mt-4 border rounded-md p-4">
        <legend class="px-1 text-sm font-medium">{{ str_replace('_', ' ', $platform) }} <span class="text-gray-400">({{ __('max :n characters', ['n' => \App\Http\Requests\PostRequest::CAPTION_MAX[$platform]]) }})</span></legend>
        <div class="grid gap-4 md:grid-cols-{{ count($languages) }}">
            @foreach ($languages as $lang)
                @php($v = $variants->get("$platform.$lang"))
                <div>
                    <x-input-label :value="strtoupper($lang)" />
                    <textarea name="variants[{{ $platform }}][{{ $lang }}][caption]" rows="5" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">{{ old("variants.$platform.$lang.caption", $v?->caption) }}</textarea>
                    <x-input-error class="mt-1" :messages="$errors->get(\"variants.$platform.$lang.caption\")" />
                    @if ($platform !== 'google_business')
                        <x-text-input name="variants[{{ $platform }}][{{ $lang }}][hashtags]" type="text" class="mt-2 block w-full text-sm" placeholder="#chania #crete" :value="old(\"variants.$platform.$lang.hashtags\", implode(' ', $v?->hashtags ?? []))" />
                    @endif
                    @if ($platform !== 'tiktok')
                        <x-text-input name="variants[{{ $platform }}][{{ $lang }}][alt_text]" type="text" class="mt-2 block w-full text-sm" placeholder="{{ __('Alt text') }}" :value="old(\"variants.$platform.$lang.alt_text\", $v?->alt_text)" />
                    @endif
                </div>
            @endforeach
        </div>
    </fieldset>
@endforeach

<div class="mt-6 flex items-center gap-4">
    <x-primary-button>{{ __('Save') }}</x-primary-button>
    <a href="{{ route('calendar', ['client' => $client->slug]) }}" class="text-sm text-gray-600 hover:underline">{{ __('Back to calendar') }}</a>
</div>
