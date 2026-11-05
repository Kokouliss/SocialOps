<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold tracking-tight text-gray-900">{{ __('Staff') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <p class="text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif
            <div class="bg-white overflow-hidden rounded-xl border border-gray-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-6 py-3">{{ __('Name') }}</th>
                            <th class="px-6 py-3">{{ __('Email') }}</th>
                            <th class="px-6 py-3">{{ __('Clients') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr>
                                <td class="px-6 py-3 font-medium text-gray-900">{{ $user->name }}@if ($user->is_admin) <span class="ml-1 text-xs font-normal text-gray-500">{{ __('admin') }}</span>@endif</td>
                                <td class="px-6 py-3">{{ $user->email }}</td>
                                <td class="px-6 py-3">
                                    {{ $user->client_ids === null ? __('all') : $clients->whereIn('id', $user->client_ids)->pluck('name')->join(', ') }}
                                    @unless ($user->is(auth()->user()))
                                        <details class="mt-1 text-xs">
                                            <summary class="cursor-pointer text-brand-600">{{ __('Limit') }}</summary>
                                            <form method="POST" action="{{ route('staff.update', $user) }}" class="mt-2 space-y-2">
                                                @csrf
                                                @method('PATCH')
                                                @foreach ($clients as $client)
                                                    <label class="flex items-center gap-1"><input type="checkbox" name="client_ids[]" value="{{ $client->id }}" class="rounded border-gray-300" @checked(in_array($client->id, $user->client_ids ?? [])) /> {{ $client->name }}</label>
                                                @endforeach
                                                @can('admin')
                                                    <label class="flex items-center gap-1 pt-1 border-t"><input type="checkbox" name="is_admin" value="1" class="rounded border-gray-300" @checked($user->is_admin) /> {{ __('Admin: sees the Settings page and can make other admins') }}</label>
                                                @endcan
                                                <button type="submit" class="px-2 py-1 border rounded hover:bg-gray-50">{{ __('Save (none ticked: every client)') }}</button>
                                            </form>
                                        </details>
                                    @endunless
                                </td>
                                <td class="px-6 py-3 text-right">
                                    @if ($user->is(auth()->user()))
                                        <span class="text-gray-500">{{ __('you') }}</span>
                                    @else
                                        <form method="POST" action="{{ route('staff.destroy', $user) }}" class="inline" onsubmit="return confirm('{{ __('Remove this login?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">{{ __('Remove') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (session('token'))
                <p class="text-sm text-yellow-800 bg-yellow-50 rounded-md p-3">{{ __('New API token, copy it now, it is not shown again:') }} <code class="font-mono select-all">{{ session('token') }}</code></p>
            @endif
            <div class="bg-white p-6 rounded-xl border border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">{{ __('API tokens') }} <span class="text-sm font-normal text-gray-500">({{ __('for scripts and other tools, see docs/api.md') }})</span></h3>
                <table class="mt-3 min-w-full text-sm">
                    <thead class="text-left text-gray-600"><tr><th class="py-1 pr-6">{{ __('Name') }}</th><th class="py-1 pr-6">{{ __('Client') }}</th><th class="py-1 pr-6">{{ __('Last used') }}</th><th></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($tokens as $token)
                            <tr>
                                <td class="py-2 pr-6 font-medium text-gray-900">{{ $token->name }}</td>
                                <td class="py-2 pr-6">{{ $token->client?->name ?? __('all') }}</td>
                                <td class="py-2 pr-6 text-gray-500">{{ $token->last_used_at?->diffForHumans() ?? __('never') }}</td>
                                <td class="py-2 text-right"><form method="POST" action="{{ route('staff.tokens.destroy', $token) }}" onsubmit="return confirm('{{ __('Revoke this token? Scripts using it stop working at once.') }}')">@csrf @method('DELETE')<button class="text-red-600 hover:underline">{{ __('Revoke') }}</button></form></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-2 text-gray-500">{{ __('None yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <form method="POST" action="{{ route('staff.tokens.store') }}" class="mt-4 flex flex-wrap items-end gap-3 text-sm">
                    @csrf
                    <div><x-input-label for="token-name" :value="__('Token name')" /><x-text-input id="token-name" name="name" type="text" class="mt-1 block" required maxlength="60" placeholder="{{ __('e.g. Zapier, reporting script') }}" /></div>
                    <div><x-input-label for="token-client" :value="__('Limited to')" />
                        <select id="token-client" name="client_id" class="mt-1 block border-gray-300 rounded-md shadow-sm text-sm"><option value="">{{ __('All clients') }}</option>@foreach ($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach</select></div>
                    <x-primary-button>{{ __('Create token') }}</x-primary-button>
                </form>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 max-w-xl">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Add a login') }}</h3>
                <form method="POST" action="{{ route('staff.store') }}" class="mt-4 space-y-4">
                    @csrf
                    @include('staff._fields')
                    <x-primary-button>{{ __('Add') }}</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
