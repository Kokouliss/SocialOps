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
        <select id="format" name="format" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
            @foreach (\App\Models\Post::FORMATS as $format)
                <option value="{{ $format }}" @selected(old('format', $post->format) === $format)>{{ str_replace('_', ' ', $format) }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('format')" />
    </div>
    <div>
        <x-input-label for="pillar" :value="__('Pillar')" />
        <select id="pillar" name="pillar" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
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
        <select name="settings[repeat]" class="mt-2 block w-full border-gray-300 rounded-md shadow-sm text-sm" title="{{ __('After it is published, the next occurrence is drafted and goes through approval again') }}">
            <option value="">{{ __('Does not repeat') }}</option>
            @foreach (['weekly', 'monthly'] as $repeat)
                <option value="{{ $repeat }}" @selected(old('settings.repeat', $post->settings['repeat'] ?? '') === $repeat)>{{ __('Repeats '.$repeat) }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="mt-6">
    <x-input-label for="brief" :value="__('Brief (what the post is about, for the drafter and the reviewer)')" />
    <textarea id="brief" name="brief" rows="3" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">{{ old('brief', $post->brief) }}</textarea>
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

@php($tiktok = $client->accounts->firstWhere('platform', 'tiktok'))
@php($tt = old('settings.tiktok', $post->settings['tiktok'] ?? []))
<fieldset class="mt-6 border rounded-md p-4 text-sm">
    <legend class="px-1 text-sm font-medium">TikTok {{ $tiktok ? __('(posting as :name)', ['name' => $tiktok->name]) : '' }}</legend>
    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <x-input-label for="tt-privacy" :value="__('Who can see it')" />
            <select id="tt-privacy" name="settings[tiktok][privacy_level]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                @foreach ($tiktok->meta['privacy_level_options'] ?? \App\Publishing\TikTokPublisher::PRIVACY as $level)
                    <option value="{{ $level }}" @selected(($tt['privacy_level'] ?? 'PUBLIC_TO_EVERYONE') === $level)>{{ ucfirst(strtolower(str_replace('_', ' ', $level))) }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-1">
            <input type="hidden" name="settings[tiktok][allow_comment]" value="0"><input type="hidden" name="settings[tiktok][allow_duet]" value="0"><input type="hidden" name="settings[tiktok][allow_stitch]" value="0">
            <label class="flex items-center gap-2"><input type="checkbox" name="settings[tiktok][allow_comment]" value="1" class="rounded border-gray-300" @checked($tt['allow_comment'] ?? true) /> {{ __('Allow comments') }}</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="settings[tiktok][allow_duet]" value="1" class="rounded border-gray-300" @checked($tt['allow_duet'] ?? true) /> {{ __('Allow duet') }}</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="settings[tiktok][allow_stitch]" value="1" class="rounded border-gray-300" @checked($tt['allow_stitch'] ?? true) /> {{ __('Allow stitch') }}</label>
        </div>
        <div>
            <x-input-label for="tt-commercial" :value="__('Commercial content')" />
            <select id="tt-commercial" name="settings[tiktok][commercial]" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                <option value="none" @selected(($tt['commercial'] ?? 'none') === 'none')>{{ __('None') }}</option>
                <option value="your_brand" @selected(($tt['commercial'] ?? '') === 'your_brand')>{{ __('Your brand (promotes the client\'s own business)') }}</option>
                <option value="branded_content" @selected(($tt['commercial'] ?? '') === 'branded_content')>{{ __('Branded content (paid partnership with a third party)') }}</option>
            </select>
        </div>
    </div>
    <label class="mt-3 flex items-start gap-2">
        <input type="hidden" name="settings[tiktok][consent]" value="0">
        <input type="checkbox" name="settings[tiktok][consent]" value="1" class="mt-1 rounded border-gray-300" @checked($tt['consent'] ?? false) />
        <span>{{ __('The creator agrees to TikTok\'s') }} <a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" class="underline">Music Usage Confirmation</a> {{ __('and, for branded content, the') }} <a href="https://www.tiktok.com/legal/page/global/bc-policy/en" target="_blank" class="underline">Branded Content Policy</a>. {{ __('The creator sees the preview in the approval link before it goes out.') }}</span>
    </label>
    <x-input-error class="mt-1" :messages="$errors->get('settings.tiktok.consent')" />
</fieldset>

@php($gbp = old('settings.gbp', $post->settings['gbp'] ?? []))
<fieldset class="mt-6 border rounded-md p-4 text-sm">
    <legend class="px-1 text-sm font-medium">Google Business Profile <span class="text-gray-400">({{ __('title and dates for events and offers') }})</span></legend>
    <div class="grid gap-4 md:grid-cols-5">
        <div><x-input-label for="gbp-title" :value="__('Title')" /><x-text-input id="gbp-title" name="settings[gbp][title]" type="text" maxlength="58" class="mt-1 block w-full" :value="$gbp['title'] ?? ''" /><x-input-error class="mt-1" :messages="$errors->get('settings.gbp.title')" /></div>
        <div><x-input-label for="gbp-start" :value="__('Starts')" /><x-text-input id="gbp-start" name="settings[gbp][start]" type="date" class="mt-1 block w-full" :value="$gbp['start'] ?? ''" /></div>
        <div><x-input-label for="gbp-end" :value="__('Ends')" /><x-text-input id="gbp-end" name="settings[gbp][end]" type="date" class="mt-1 block w-full" :value="$gbp['end'] ?? ''" /><x-input-error class="mt-1" :messages="$errors->get('settings.gbp.end')" /></div>
        <div><x-input-label for="gbp-link" :value="__('Learn more link')" /><x-text-input id="gbp-link" name="settings[gbp][link]" type="url" class="mt-1 block w-full" :value="$gbp['link'] ?? ''" /><x-input-error class="mt-1" :messages="$errors->get('settings.gbp.link')" /></div>
        <div><x-input-label for="gbp-coupon" :value="__('Coupon code (offers)')" /><x-text-input id="gbp-coupon" name="settings[gbp][coupon]" type="text" class="mt-1 block w-full" :value="$gbp['coupon'] ?? ''" /></div>
    </div>
</fieldset>

<h3 class="mt-8 font-semibold text-gray-800">{{ __('First comment') }} <span class="text-sm font-normal text-gray-500">{{ __('(Instagram and Facebook: posted under the post right after it goes out, for hashtags or a link; also in the ZIP and the CSV)') }}</span></h3>
<textarea name="settings[first_comment]" rows="2" class="mt-2 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm text-sm">{{ old('settings.first_comment', $post->settings['first_comment'] ?? '') }}</textarea>
<x-input-error class="mt-1" :messages="$errors->get('settings.first_comment')" />

@php($design = old('settings.design', $post->settings['design'] ?? []))
<h3 class="mt-8 font-semibold text-gray-800">{{ __('Design text') }} <span class="text-sm font-normal text-gray-500">{{ __('(what goes on the graphic or in the video: headline, slide texts or Reel script; it travels with the ZIP and the Canva CSV)') }}</span></h3>
<div class="mt-2 grid gap-4 md:grid-cols-{{ count($languages) }}">
    @foreach ($languages as $lang)
        <div>
            <x-input-label :value="strtoupper($lang)" />
            <textarea name="settings[design][{{ $lang }}]" rows="4" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm text-sm">{{ $design[$lang] ?? '' }}</textarea>
        </div>
    @endforeach
</div>

<h3 class="mt-8 font-semibold text-gray-800">{{ __('Captions') }} <span class="text-sm font-normal text-gray-500">{{ __('(only the ticked platforms are saved; Greek is reviewed by a human before approval)') }}</span></h3>
@foreach (\App\Models\Post::PLATFORMS as $platform)
    <fieldset class="mt-4 border rounded-md p-4">
        <legend class="px-1 text-sm font-medium">{{ str_replace('_', ' ', $platform) }} <span class="text-gray-400">({{ __('max :n characters', ['n' => \App\Http\Requests\PostRequest::CAPTION_MAX[$platform]]) }})</span></legend>
        <div class="grid gap-4 md:grid-cols-{{ count($languages) }}">
            @foreach ($languages as $lang)
                @php($v = $variants->get("$platform.$lang"))
                <div>
                    <x-input-label :value="strtoupper($lang)" />
                    <textarea name="variants[{{ $platform }}][{{ $lang }}][caption]" rows="5" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm text-sm">{{ old("variants.$platform.$lang.caption", $v?->caption) }}</textarea>
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
