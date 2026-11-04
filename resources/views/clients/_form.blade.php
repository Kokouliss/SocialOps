@csrf
<div class="grid gap-6 md:grid-cols-2">
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $client->name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>
    <div>
        <x-input-label for="location" :value="__('Location')" />
        <x-text-input id="location" name="location" type="text" class="mt-1 block w-full" :value="old('location', $client->location)" required />
        <x-input-error class="mt-2" :messages="$errors->get('location')" />
    </div>
    <div>
        <x-input-label for="slug" :value="__('Slug (lowercase, used in URLs and file names)')" />
        <x-text-input id="slug" name="slug" type="text" class="mt-1 block w-full" :value="old('slug', $client->slug)" required />
        <x-input-error class="mt-2" :messages="$errors->get('slug')" />
    </div>
    <div>
        <x-input-label for="code" :value="__('Code (uppercase, used in asset and post names)')" />
        <x-text-input id="code" name="code" type="text" class="mt-1 block w-full font-mono uppercase" :value="old('code', $client->code)" required />
        <x-input-error class="mt-2" :messages="$errors->get('code')" />
    </div>
    <div>
        <x-input-label for="type" :value="__('Type')" />
        <select id="type" name="type" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" required>
            @foreach (\App\Models\Client::TYPES as $type)
                <option value="{{ $type }}" @selected(old('type', $client->type) === $type)>{{ str_replace('_', ' ', $type) }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('type')" />
    </div>
</div>
@php($profile = $client->exists ? $client->profile : \App\Clients\ClientProfile::parse($profileYaml))
@php($rows = fn ($items) => array_merge(array_values($items), [[]]))
@php($input = 'mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm text-sm')
<div class="mt-8 grid gap-6 md:grid-cols-2">
    <div>
        <x-input-label for="p-languages" :value="__('Languages (first is the primary, e.g. el, en)')" />
        <x-text-input id="p-languages" name="p[languages]" type="text" class="mt-1 block w-full" :value="old('p.languages', implode(', ', $profile['languages'] ?? []))" required />
        <x-input-error class="mt-2" :messages="$errors->get('profile')" />
    </div>
    <div>
        <x-input-label for="p-extra" :value="__('Extra languages drafted on request (e.g. de)')" />
        <x-text-input id="p-extra" name="p[extra_languages]" type="text" class="mt-1 block w-full" :value="old('p.extra_languages', implode(', ', $profile['extra_languages'] ?? []))" />
    </div>
    <div>
        <x-input-label for="p-policy" :value="__('Approval')" />
        <div class="mt-1 flex gap-2">
            <select id="p-policy" name="p[approval][policy]" class="{{ $input }} mt-0">
                @foreach (['none' => __('None: staff approve'), 'optional' => __('Optional: the client may answer'), 'required' => __('Required: the client must approve'), 'multi_level' => __('Multi-level')] as $value => $label)
                    <option value="{{ $value }}" @selected(old('p.approval.policy', $profile['approval']['policy'] ?? 'required') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-text-input name="p[approval][sla_hours]" type="number" min="1" max="720" class="w-28" :value="old('p.approval.sla_hours', $profile['approval']['sla_hours'] ?? 48)" title="{{ __('Hours the client has to answer') }}" />
        </div>
    </div>
    <div>
        <x-input-label :value="__('Approvers (name, email, phone for reminders)')" />
        @foreach ($rows(old('p.approval.approvers', $profile['approval']['approvers'] ?? [])) as $i => $row)
            <div class="mt-1 grid grid-cols-3 gap-2">
                <x-text-input name="p[approval][approvers][{{ $i }}][name]" type="text" class="block w-full text-sm" :value="$row['name'] ?? ''" placeholder="{{ __('Name') }}" />
                <x-text-input name="p[approval][approvers][{{ $i }}][email]" type="email" class="block w-full text-sm" :value="$row['email'] ?? ''" placeholder="email" />
                <x-text-input name="p[approval][approvers][{{ $i }}][phone]" type="text" class="block w-full text-sm" :value="$row['phone'] ?? ''" placeholder="+30 …" />
            </div>
        @endforeach
    </div>
    <div>
        <x-input-label for="p-always" :value="__('Hashtags on every post')" />
        <x-text-input id="p-always" name="p[hashtags][always]" type="text" class="mt-1 block w-full" :value="old('p.hashtags.always', implode(' ', $profile['hashtags']['always'] ?? []))" placeholder="#chania #crete" />
        <x-input-label for="p-pool" class="mt-3" :value="__('Hashtag pool (Claude picks from these)')" />
        <textarea id="p-pool" name="p[hashtags][pool]" rows="2" class="{{ $input }}">{{ old('p.hashtags.pool', implode(' ', $profile['hashtags']['pool'] ?? [])) }}</textarea>
        <x-input-label for="p-max" class="mt-3" :value="__('Hashtags per post, at most')" />
        <x-text-input id="p-max" name="p[hashtags][max_per_post]" type="number" min="0" max="30" class="mt-1 w-28" :value="old('p.hashtags.max_per_post', $profile['hashtags']['max_per_post'] ?? 5)" />
    </div>
    <div>
        <x-input-label :value="__('Signature line under every caption, per platform (optional)')" />
        @foreach (\App\Clients\ClientProfile::PLATFORMS as $platform)
            <x-text-input name="p[signatures][{{ $platform }}]" type="text" class="mt-1 block w-full text-sm" :value="old('p.signatures.'.$platform, $profile['signatures'][$platform] ?? '')" placeholder="{{ ucfirst(str_replace('_', ' ', $platform)) }}" />
        @endforeach
        <x-input-label class="mt-3" :value="__('Placeholders for captions, written as {{phone}} and filled at publish time')" />
        @foreach ($rows(collect(old('p.fields', collect($profile['fields'] ?? [])->map(fn ($v, $k) => ['key' => $k, 'value' => $v])->values()->all()))->all()) as $i => $row)
            <div class="mt-1 grid grid-cols-3 gap-2">
                <x-text-input name="p[fields][{{ $i }}][key]" type="text" class="block w-full font-mono text-sm" :value="$row['key'] ?? ''" placeholder="phone" />
                <x-text-input name="p[fields][{{ $i }}][value]" type="text" class="col-span-2 block w-full text-sm" :value="$row['value'] ?? ''" placeholder="+30 28210 00000" />
            </div>
        @endforeach
    </div>
    <div class="md:col-span-2">
        <x-input-label :value="__('Saved replies for the comment inbox (label, text, and keywords that trigger it on a new Instagram or Facebook comment)')" />
        @foreach ($rows(old('p.saved_replies', $profile['saved_replies'] ?? [])) as $i => $row)
            <div class="mt-1 grid gap-2 md:grid-cols-6">
                <x-text-input name="p[saved_replies][{{ $i }}][label]" type="text" class="block w-full text-sm" :value="$row['label'] ?? ''" placeholder="{{ __('Opening hours') }}" />
                <x-text-input name="p[saved_replies][{{ $i }}][text]" type="text" class="md:col-span-4 block w-full text-sm" :value="$row['text'] ?? ''" placeholder="{{ __('Ανοιχτά κάθε μέρα 10:00–02:00 / Open daily 10:00–02:00') }}" />
                <x-text-input name="p[saved_replies][{{ $i }}][keywords]" type="text" class="block w-full text-sm" :value="is_array($row['keywords'] ?? null) ? implode(', ', $row['keywords']) : ($row['keywords'] ?? '')" placeholder="{{ __('ώρες, hours') }}" />
            </div>
        @endforeach
        <p class="mt-1 text-xs text-gray-500">{{ __('An empty row is ignored; save to get a new empty row.') }}</p>
    </div>
</div>
<details class="mt-6" {{ $errors->has('profile') ? 'open' : '' }}>
    <summary class="cursor-pointer text-sm font-medium text-gray-700">{{ __('Everything else: voice, pillars, accounts, research, slots, webhooks, key dates (YAML, same sections as config/clients/example.yaml)') }}</summary>
    <textarea id="profile" name="profile" rows="32" class="mt-2 block w-full font-mono text-sm border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" required>{{ old('profile', $profileYaml) }}</textarea>
    <p class="mt-1 text-xs text-gray-500">{{ __('The fields above win over the same sections here.') }}</p>
    <x-input-error class="mt-2" :messages="$errors->get('profile')" />
</details>
<div class="mt-6 flex items-center gap-4">
    <x-primary-button>{{ __('Save') }}</x-primary-button>
    <a href="{{ route('clients.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Cancel') }}</a>
    @if (session('status'))
        <p class="text-sm text-gray-600">{{ __('Saved.') }}</p>
    @endif
</div>
