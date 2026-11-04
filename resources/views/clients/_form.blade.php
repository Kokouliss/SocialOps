@csrf
@php($profile = $client->exists ? $client->profile : \App\Clients\ClientProfile::template())
@php($rows = fn ($items) => array_merge(array_values($items), [[]]))
@php($input = 'mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm text-sm')
@php($check = 'rounded border-gray-300 text-brand-600 focus:ring-brand-500')
@php($p = fn ($key, $default = '') => old('p.'.$key, data_get($profile, $key, $default)))
@php($text = fn ($key, $glue = ', ') => is_array($v = $p($key)) ? implode($glue, $v) : (string) $v)
@php($flag = fn ($key) => $errors->any() ? (bool) old('p.'.$key) : (bool) data_get($profile, $key))
@php($platforms = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'google_business' => 'Google Business Profile'])

@if ($errors->any())
    <div class="mb-6 rounded-md bg-red-50 p-4 text-sm text-red-700">
        <p class="font-medium">{{ __('Please check these and save again:') }}</p>
        <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid gap-6 md:grid-cols-3">
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $client->name)" required />
    </div>
    <div>
        <x-input-label for="type" :value="__('Type')" />
        <select id="type" name="type" class="{{ $input }}" required>
            @foreach (\App\Models\Client::TYPES as $type)
                <option value="{{ $type }}" @selected(old('type', $client->type) === $type)>{{ __(str_replace('_', ' ', $type)) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-input-label for="location" :value="__('Location')" />
        <x-text-input id="location" name="location" type="text" class="mt-1 block w-full" :value="old('location', $client->location)" placeholder="Chania, Crete" required />
    </div>
</div>
<details class="mt-4" {{ $errors->hasAny(['slug', 'code']) ? 'open' : '' }}>
    <summary class="cursor-pointer text-sm text-gray-600">{{ __('Name in links and code in file names') }}@if ($client->exists): <span class="font-mono">{{ $client->slug }}</span>, <span class="font-mono">{{ $client->code }}</span>@endif</summary>
    <div class="mt-2 grid gap-6 md:grid-cols-3">
        <div>
            <x-input-label for="slug" :value="__('In links')" />
            <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full font-mono" :value="old('slug', $client->slug)" placeholder="almyra-beach-lounge" />
            <p class="mt-1 text-xs text-gray-500">{{ __('Lowercase letters, digits and dashes. Made from the name when left empty; changing it later breaks links already sent.') }}</p>
        </div>
        <div>
            <x-input-label for="code" :value="__('In file and post names')" />
            <x-text-input id="code" name="code" type="text" class="mt-1 block w-full font-mono uppercase" :value="old('code', $client->code)" placeholder="ALMYRA" />
            <p class="mt-1 text-xs text-gray-500">{{ __('2 to 12 capital letters or digits. Made from the name when left empty.') }}</p>
        </div>
    </div>
</details>

<h3 class="mt-8 font-semibold text-gray-800">{{ __('Accounts') }}</h3>
<p class="text-sm text-gray-500">{{ __('The name of each account as the client knows it. Connecting them happens on the Accounts page.') }}</p>
<div class="mt-2 grid gap-4 md:grid-cols-4">
    @foreach ($platforms as $platform => $label)
        <div>
            <x-input-label for="p-accounts-{{ $platform }}" :value="$label" />
            <x-text-input id="p-accounts-{{ $platform }}" name="p[accounts][{{ $platform }}]" type="text" class="mt-1 block w-full" :value="$p('accounts.'.$platform)" placeholder="{{ $platform === 'google_business' ? 'Almyra Beach Lounge, Chania' : '@almyra.chania' }}" />
        </div>
    @endforeach
</div>

<h3 class="mt-8 font-semibold text-gray-800">{{ __('Languages') }}</h3>
<div class="mt-2 grid gap-4 md:grid-cols-2">
    <div>
        <x-input-label for="p-languages" :value="__('Caption languages, the main one first (e.g. el, en)')" />
        <x-text-input id="p-languages" name="p[languages]" type="text" class="mt-1 block w-full" :value="$text('languages')" required />
    </div>
    <div>
        <x-input-label for="p-extra" :value="__('Extra languages drafted on request (e.g. de)')" />
        <x-text-input id="p-extra" name="p[extra_languages]" type="text" class="mt-1 block w-full" :value="$text('extra_languages')" />
    </div>
</div>

<h3 class="mt-8 font-semibold text-gray-800">{{ __('Voice') }}</h3>
<p class="text-sm text-gray-500">{{ __('How the captions sound. Claude follows this on every post, reply and report.') }}</p>
<div class="mt-2 grid gap-4 md:grid-cols-2">
    <div class="md:col-span-2">
        <x-input-label for="p-tone" :value="__('Tone')" />
        <x-text-input id="p-tone" name="p[voice][tone]" type="text" class="mt-1 block w-full" :value="$p('voice.tone')" placeholder="{{ __('relaxed, warm, confident; premium without sounding formal') }}" required />
    </div>
    <div>
        <x-input-label for="p-person" :value="__('Speak as')" />
        <x-text-input id="p-person" name="p[voice][person]" type="text" class="mt-1 block w-full" :value="$p('voice.person')" placeholder="{{ __('we, or the name of the place') }}" required />
    </div>
    <div>
        <x-input-label for="p-emoji" :value="__('Emoji')" />
        <x-text-input id="p-emoji" name="p[voice][emoji]" type="text" class="mt-1 block w-full" :value="$p('voice.emoji')" placeholder="{{ __('max 1 per caption, never in the first line') }}" required />
    </div>
    <div class="md:col-span-2">
        <x-input-label for="p-cta" :value="__('Call to action')" />
        <x-text-input id="p-cta" name="p[voice][cta_style]" type="text" class="mt-1 block w-full" :value="$p('voice.cta_style')" placeholder="{{ __('soft (book a table, link in bio), never pushy') }}" required />
    </div>
    <div>
        <x-input-label for="p-facts" :value="__('Facts to keep consistent (one per line)')" />
        <textarea id="p-facts" name="p[voice][facts]" rows="3" class="{{ $input }}" placeholder="{{ __('Open daily 10:00 to midnight, May to October') }}">{{ $text('voice.facts', "\n") }}</textarea>
    </div>
    <div>
        <x-input-label for="p-banned" :value="__('Phrases never to use (one per line)')" />
        <textarea id="p-banned" name="p[voice][banned_phrases]" rows="3" class="{{ $input }}">{{ $text('voice.banned_phrases', "\n") }}</textarea>
    </div>
    <div class="md:col-span-2">
        <x-input-label for="p-notes" :value="__('Extra information')" />
        <textarea id="p-notes" name="p[notes]" rows="4" class="{{ $input }}" placeholder="{{ __('Anything else the team and Claude should know: the story of the place, what the owner cares about, what to avoid.') }}">{{ $p('notes') }}</textarea>
    </div>
</div>

<h3 class="mt-8 font-semibold text-gray-800">{{ __('Content pillars') }}</h3>
<p class="text-sm text-gray-500">{{ __('The topics the posts rotate through and the share of posts each gets. The shares add up to 100.') }}</p>
@if ($client->exists)
    @php($mix = \App\Planning\PillarMix::for($client))
    <p class="mt-1 text-sm text-gray-500">{{ __('Posts per pillar, :days days back and ahead (:n posts):', ['days' => \App\Planning\PillarMix::DAYS, 'n' => $mix['posts']]) }}
        @foreach ($mix['rows'] as $pillar => $row)<span class="{{ in_array($pillar, $mix['drifted']) ? 'font-medium text-red-600' : '' }}">{{ str_replace('_', ' ', $pillar) }} {{ $row['actual'] }}%</span>@if (! $loop->last) · @endif @endforeach
    </p>
@endif
@php($pillars = old('p.pillars', collect($profile['pillars'] ?? [])->map(fn ($share, $name) => ['name' => str_replace('_', ' ', $name), 'share' => $share])->values()->all()))
<div class="mt-2 grid gap-2 md:grid-cols-3">
    @foreach ($rows($pillars) as $i => $row)
        <div class="flex gap-2">
            <x-text-input name="p[pillars][{{ $i }}][name]" type="text" class="block w-full text-sm" :value="$row['name'] ?? ''" placeholder="{{ __('food and drink') }}" />
            <x-text-input name="p[pillars][{{ $i }}][share]" type="number" min="0" max="100" class="w-20 text-sm" :value="$row['share'] ?? ''" placeholder="%" />
        </div>
    @endforeach
</div>

<h3 class="mt-8 font-semibold text-gray-800">{{ __('Hashtags') }}</h3>
<div class="mt-2 grid gap-4 md:grid-cols-3">
    <div>
        <x-input-label for="p-always" :value="__('On every post')" />
        <x-text-input id="p-always" name="p[hashtags][always]" type="text" class="mt-1 block w-full" :value="$text('hashtags.always', ' ')" placeholder="#chania #crete" />
    </div>
    <div>
        <x-input-label for="p-pool" :value="__('Pool Claude picks from')" />
        <textarea id="p-pool" name="p[hashtags][pool]" rows="2" class="{{ $input }}" placeholder="#chaniabeach #cretesummer">{{ $text('hashtags.pool', ' ') }}</textarea>
    </div>
    <div>
        <x-input-label for="p-max" :value="__('Per post, at most')" />
        <x-text-input id="p-max" name="p[hashtags][max_per_post]" type="number" min="0" max="30" class="mt-1 w-28" :value="$p('hashtags.max_per_post', 5)" required />
        <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="p[utm]" value="1" class="{{ $check }}" @checked($flag('utm'))> {{ __('Tag links so Google Analytics shows which post brought the visit') }}</label>
    </div>
</div>

<h3 class="mt-8 font-semibold text-gray-800">{{ __('Approval') }}</h3>
<div class="mt-2 grid gap-4 md:grid-cols-2">
    <div>
        <x-input-label for="p-policy" :value="__('Who approves the posts, and the hours the client has to answer')" />
        <div class="mt-1 flex gap-2">
            <select id="p-policy" name="p[approval][policy]" class="{{ $input }} mt-0">
                @foreach (['none' => __('None: staff approve'), 'optional' => __('Optional: the client may answer'), 'required' => __('Required: the client must approve'), 'multi_level' => __('Multi-level')] as $value => $label)
                    <option value="{{ $value }}" @selected($p('approval.policy', 'required') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-text-input name="p[approval][sla_hours]" type="number" min="1" max="720" class="w-28" :value="$p('approval.sla_hours', 48)" title="{{ __('Hours the client has to answer') }}" required />
        </div>
        <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="p[approval][auto_approve_on_timeout]" value="1" class="{{ $check }}" @checked($flag('approval.auto_approve_on_timeout'))> {{ __('Approve by itself when the client does not answer in time (only with "Optional")') }}</label>
        <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="p[approval][email_monthly_report]" value="1" class="{{ $check }}" @checked($flag('approval.email_monthly_report'))> {{ __('Email every monthly report to the approvers as soon as it is written') }}</label>
    </div>
    <div>
        <x-input-label :value="__('Who gets the approval links (name, email, phone for reminders)')" />
        @foreach ($rows(old('p.approval.approvers', $profile['approval']['approvers'] ?? [])) as $i => $row)
            <div class="mt-1 grid grid-cols-3 gap-2">
                <x-text-input name="p[approval][approvers][{{ $i }}][name]" type="text" class="block w-full text-sm" :value="$row['name'] ?? ''" placeholder="{{ __('Name') }}" />
                <x-text-input name="p[approval][approvers][{{ $i }}][email]" type="email" class="block w-full text-sm" :value="$row['email'] ?? ''" placeholder="email" />
                <x-text-input name="p[approval][approvers][{{ $i }}][phone]" type="text" class="block w-full text-sm" :value="$row['phone'] ?? ''" placeholder="+30 …" />
            </div>
        @endforeach
    </div>
</div>

<h3 class="mt-8 font-semibold text-gray-800">{{ __('When to post') }}</h3>
@php($days = ['mon' => __('Monday'), 'tue' => __('Tuesday'), 'wed' => __('Wednesday'), 'thu' => __('Thursday'), 'fri' => __('Friday'), 'sat' => __('Saturday'), 'sun' => __('Sunday')])
@php($slots = old('p.slots', array_map(fn ($s) => array_combine(['day', 'time'], explode(' ', $s) + [1 => '']), $profile['slots'] ?? [])))
<div class="mt-2 grid gap-4 md:grid-cols-2">
    <div>
        <x-input-label :value="__('Weekly posting slots (the post form suggests the next free one)')" />
        @foreach ($rows($slots) as $i => $row)
            <div class="mt-1 flex gap-2">
                <select name="p[slots][{{ $i }}][day]" class="{{ $input }} mt-0 w-40">
                    <option value="">—</option>
                    @foreach ($days as $value => $label)<option value="{{ $value }}" @selected(strtolower($row['day'] ?? '') === $value)>{{ $label }}</option>@endforeach
                </select>
                <x-text-input name="p[slots][{{ $i }}][time]" type="time" class="text-sm" :value="$row['time'] ?? ''" />
            </div>
        @endforeach
    </div>
    <div>
        <x-input-label :value="__('Key dates (openings, events, anniversaries)')" />
        @foreach ($rows(old('p.key_dates', $profile['key_dates'] ?? [])) as $i => $row)
            <div class="mt-1 flex gap-2">
                <x-text-input name="p[key_dates][{{ $i }}][date]" type="date" class="text-sm" :value="$row['date'] ?? ''" />
                <x-text-input name="p[key_dates][{{ $i }}][label]" type="text" class="block w-full text-sm" :value="$row['label'] ?? ''" placeholder="{{ __('Season opening') }}" />
            </div>
        @endforeach
    </div>
</div>

<h3 class="mt-8 font-semibold text-gray-800">{{ __('Research') }}</h3>
<p class="text-sm text-gray-500">{{ __('Steers the trend research, the planner and the weekly listening report.') }}</p>
<div class="mt-2 grid gap-4 md:grid-cols-3">
    <div>
        <x-input-label for="p-sources" :value="__('Sites to search first (one per line)')" />
        <textarea id="p-sources" name="p[research][sources]" rows="2" class="{{ $input }}" placeholder="cretalive.gr">{{ $text('research.sources', "\n") }}</textarea>
    </div>
    <div>
        <x-input-label for="p-excluded-domains" :value="__('Sites never to cite (one per line)')" />
        <textarea id="p-excluded-domains" name="p[research][excluded_domains]" rows="2" class="{{ $input }}">{{ $text('research.excluded_domains', "\n") }}</textarea>
    </div>
    <div>
        <x-input-label for="p-excluded-topics" :value="__('Topics never to post about (one per line)')" />
        <textarea id="p-excluded-topics" name="p[research][excluded_topics]" rows="2" class="{{ $input }}" placeholder="{{ __('politics') }}">{{ $text('research.excluded_topics', "\n") }}</textarea>
    </div>
    <div>
        <x-input-label for="p-watch" :value="__('Hashtags to watch every week (up to 30)')" />
        <x-text-input id="p-watch" name="p[research][hashtags_to_watch]" type="text" class="mt-1 block w-full" :value="$text('research.hashtags_to_watch', ' ')" placeholder="chaniabeach cretefood" />
    </div>
    <div>
        <x-input-label for="p-competitors" :value="__('Competitor Instagram accounts to watch')" />
        <x-text-input id="p-competitors" name="p[research][competitors]" type="text" class="mt-1 block w-full" :value="$text('research.competitors', ' ')" placeholder="@otherbeachbar" />
    </div>
</div>

<h3 class="mt-8 font-semibold text-gray-800">{{ __('Text added to every post') }}</h3>
<div class="mt-2 grid gap-4 md:grid-cols-2">
    <div>
        <x-input-label :value="__('Signature line under every caption, per platform (optional)')" />
        @foreach ($platforms as $platform => $label)
            <x-text-input name="p[signatures][{{ $platform }}]" type="text" class="mt-1 block w-full text-sm" :value="$p('signatures.'.$platform)" placeholder="{{ $label }}" />
        @endforeach
    </div>
    <div>
        <x-input-label :value="__('Placeholders for captions, written as :tag and filled at publish time', ['tag' => '{'.'{phone}}'])" />
        @foreach ($rows(collect(old('p.fields', collect($profile['fields'] ?? [])->map(fn ($v, $k) => ['key' => $k, 'value' => $v])->values()->all()))->all()) as $i => $row)
            <div class="mt-1 grid grid-cols-3 gap-2">
                <x-text-input name="p[fields][{{ $i }}][key]" type="text" class="block w-full font-mono text-sm" :value="$row['key'] ?? ''" placeholder="phone" />
                <x-text-input name="p[fields][{{ $i }}][value]" type="text" class="col-span-2 block w-full text-sm" :value="$row['value'] ?? ''" placeholder="+30 28210 00000" />
            </div>
        @endforeach
    </div>
</div>

<h3 class="mt-8 font-semibold text-gray-800">{{ __('Comment replies') }}</h3>
<x-input-label class="mt-2" :value="__('Saved replies for the comment inbox (label, text, and keywords that trigger it on a new Instagram or Facebook comment)')" />
@foreach ($rows(old('p.saved_replies', $profile['saved_replies'] ?? [])) as $i => $row)
    <div class="mt-1 grid gap-2 md:grid-cols-6">
        <x-text-input name="p[saved_replies][{{ $i }}][label]" type="text" class="block w-full text-sm" :value="$row['label'] ?? ''" placeholder="{{ __('Opening hours') }}" />
        <x-text-input name="p[saved_replies][{{ $i }}][text]" type="text" class="md:col-span-4 block w-full text-sm" :value="$row['text'] ?? ''" placeholder="{{ __('Ανοιχτά κάθε μέρα 10:00–02:00 / Open daily 10:00–02:00') }}" />
        <x-text-input name="p[saved_replies][{{ $i }}][keywords]" type="text" class="block w-full text-sm" :value="is_array($row['keywords'] ?? null) ? implode(', ', $row['keywords']) : ($row['keywords'] ?? '')" placeholder="{{ __('ώρες, hours') }}" />
    </div>
@endforeach
<p class="mt-1 text-xs text-gray-500">{{ __('An empty row is ignored; save to get a new empty row.') }}</p>

<details class="mt-8" {{ $errors->any() || ($profile['webhooks'] ?? []) !== [] ? 'open' : '' }}>
    <summary class="cursor-pointer text-sm font-medium text-gray-700">{{ __('For developers: webhooks') }}</summary>
    <p class="mt-1 text-xs text-gray-500">{{ __('An https address that receives a signed JSON message when a post is published, fails or needs a human (docs/api.md).') }}</p>
    @foreach ($rows(old('p.webhooks', $profile['webhooks'] ?? [])) as $i => $row)
        <div class="mt-1 grid gap-2 md:grid-cols-6">
            <x-text-input name="p[webhooks][{{ $i }}][url]" type="url" pattern="https://.*" class="md:col-span-3 block w-full font-mono text-sm" :value="$row['url'] ?? ''" placeholder="https://example.com/hook" />
            <x-text-input name="p[webhooks][{{ $i }}][secret]" type="text" class="block w-full font-mono text-sm" :value="$row['secret'] ?? ''" placeholder="{{ __('secret') }}" />
            <div class="md:col-span-2 flex flex-wrap items-center gap-3 text-xs">
                @foreach (\App\Webhooks\Webhooks::EVENTS as $event)
                    <label class="flex items-center gap-1"><input type="checkbox" name="p[webhooks][{{ $i }}][events][]" value="{{ $event }}" class="{{ $check }}" @checked(in_array($event, $row['events'] ?? []))> {{ $event }}</label>
                @endforeach
            </div>
        </div>
    @endforeach
</details>

<div class="mt-6 flex items-center gap-4">
    <x-primary-button>{{ __('Save') }}</x-primary-button>
    <a href="{{ route('clients.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Cancel') }}</a>
    @if (session('status'))
        <p class="text-sm text-gray-600">{{ __('Saved.') }}</p>
    @endif
</div>
