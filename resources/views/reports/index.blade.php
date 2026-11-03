<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }}: {{ __('reports') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg text-sm">
                <p class="text-gray-500">{{ __('Every trend brief, content plan and monthly report for this client, newest first. Monthly reports are built on the 1st for last month; ask for another month here.') }}</p>
                @if (session('status') === 'report-queued')
                    <p class="mt-2 text-green-600">{{ __('Report queued. It appears below in a few minutes.') }}</p>
                @endif
                <form method="POST" action="{{ route('reports.monthly', $client) }}" class="mt-3 flex items-end gap-3">
                    @csrf
                    <div>
                        <x-input-label for="month" :value="__('Month')" />
                        <x-text-input id="month" name="month" type="month" max="{{ now()->subMonth()->format('Y-m') }}" class="mt-1 block" :value="old('month', now()->subMonth()->format('Y-m'))" />
                    </div>
                    <x-primary-button>{{ __('Monthly report') }}</x-primary-button>
                    <x-input-error :messages="$errors->get('month')" />
                </form>
                <table class="mt-4 w-full">
                    <thead><tr class="text-left text-gray-500"><th class="py-1">{{ __('Date') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Title') }}</th></tr></thead>
                    <tbody>
                        @forelse ($reports as $report)
                            <tr class="border-t">
                                <td class="py-2 whitespace-nowrap">{{ $report->created_at->format('Y-m-d H:i') }}</td>
                                <td class="pr-4">{{ __(\App\Models\Report::KINDS[$report->kind] ?? $report->kind) }}</td>
                                <td><a href="{{ route('reports.show', $report) }}" class="text-brand-600 hover:underline">{{ $report->title }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-2 text-gray-500">{{ __('No reports yet. Run "Plan with Claude" on the client page.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
