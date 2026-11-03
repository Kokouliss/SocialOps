<x-app-layout>
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

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-6 py-3">{{ __('Name') }}</th>
                            <th class="px-6 py-3">{{ __('Kind') }}</th>
                            <th class="px-6 py-3">{{ __('Pillar') }}</th>
                            <th class="px-6 py-3">{{ __('Tags') }}</th>
                            <th class="px-6 py-3">{{ __('Uploaded') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($assets as $asset)
                            <tr>
                                <td class="px-6 py-3 font-mono text-xs text-gray-900">{{ $asset->name }}<span class="block text-gray-500">{{ $asset->original_name }} · {{ number_format($asset->size / 1048576, 1) }} MB</span></td>
                                <td class="px-6 py-3">{{ $asset->kind }}</td>
                                <td class="px-6 py-3">{{ $asset->pillar ? str_replace('_', ' ', $asset->pillar) : '—' }}</td>
                                <td class="px-6 py-3 text-xs">
                                    @if ($asset->season) <span class="mr-2">{{ $asset->season }}</span> @endif
                                    @if ($asset->people_consent) <span class="mr-2 text-green-700">{{ __('consent') }}</span> @endif
                                    @if ($asset->ugc_permission) <span class="mr-2 text-green-700">{{ __('UGC ok') }}</span> @endif
                                    @if ($asset->note) <span class="block text-gray-500">{{ $asset->note }}</span> @endif
                                </td>
                                <td class="px-6 py-3 whitespace-nowrap">{{ $asset->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('assets.download', $asset) }}" class="text-indigo-600 hover:underline">{{ __('Download') }}</a>
                                    <form method="POST" action="{{ route('assets.destroy', $asset) }}" class="inline ml-3" onsubmit="return confirm('{{ __('Delete this file?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-6 text-gray-500">{{ __('Nothing uploaded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
