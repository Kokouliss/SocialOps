<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Staff') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <p class="text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
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
                                <td class="px-6 py-3 font-medium text-gray-900">{{ $user->name }}</td>
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
            <div class="bg-white p-6 shadow-sm sm:rounded-lg max-w-xl">
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
