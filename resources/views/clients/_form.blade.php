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
        <select id="type" name="type" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
            @foreach (\App\Models\Client::TYPES as $type)
                <option value="{{ $type }}" @selected(old('type', $client->type) === $type)>{{ str_replace('_', ' ', $type) }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('type')" />
    </div>
</div>
<div class="mt-6">
    <x-input-label for="profile" :value="__('Brand profile (YAML, same sections as config/clients/example.yaml)')" />
    {{-- shortcut: the nested profile is edited as YAML text; replace with per-field inputs when non-technical staff edit profiles --}}
    <textarea id="profile" name="profile" rows="32" class="mt-1 block w-full font-mono text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>{{ old('profile', $profileYaml) }}</textarea>
    <x-input-error class="mt-2" :messages="$errors->get('profile')" />
</div>
<div class="mt-6 flex items-center gap-4">
    <x-primary-button>{{ __('Save') }}</x-primary-button>
    <a href="{{ route('clients.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Cancel') }}</a>
    @if (session('status'))
        <p class="text-sm text-gray-600">{{ __('Saved.') }}</p>
    @endif
</div>
