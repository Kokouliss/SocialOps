<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Clients') }}</h2>
            <a href="{{ route('clients.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">{{ __('New client') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="mb-4 text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-6 py-3">{{ __('Name') }}</th>
                            <th class="px-6 py-3">{{ __('Code') }}</th>
                            <th class="px-6 py-3">{{ __('Type') }}</th>
                            <th class="px-6 py-3">{{ __('Platforms') }}</th>
                            <th class="px-6 py-3">{{ __('Approval') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($clients as $client)
                            <tr>
                                <td class="px-6 py-3 font-medium text-gray-900">{{ $client->name }}<span class="block text-xs text-gray-500">{{ $client->location }}</span></td>
                                <td class="px-6 py-3 font-mono">{{ $client->code }}</td>
                                <td class="px-6 py-3">{{ str_replace('_', ' ', $client->type) }}</td>
                                <td class="px-6 py-3">{{ collect($client->profile['accounts'])->filter()->keys()->map(fn ($p) => str_replace('_', ' ', $p))->join(', ') ?: '—' }}</td>
                                <td class="px-6 py-3">{{ $client->profile['approval']['policy'] }}</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('clients.assets', $client) }}" class="text-indigo-600 hover:underline mr-3">{{ __('Assets') }}</a>
                                    <a href="{{ route('clients.reviews', $client) }}" class="text-indigo-600 hover:underline mr-3">{{ __('Approvals') }}</a>
                                    <a href="{{ route('clients.accounts', $client) }}" class="text-indigo-600 hover:underline mr-3">{{ __('Accounts') }}</a>
                                    <a href="{{ route('clients.edit', $client) }}" class="text-indigo-600 hover:underline">{{ __('Edit') }}</a>
                                    <form method="POST" action="{{ route('clients.destroy', $client) }}" class="inline ml-3" onsubmit="return confirm('{{ __('Delete this client and everything it owns?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-6 text-gray-500">{{ __('No clients yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
