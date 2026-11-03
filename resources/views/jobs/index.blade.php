<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Jobs') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 text-sm">
            @if (session('status'))
                <p class="text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-semibold text-gray-900">{{ __('Waiting') }}</h3>
                <p class="mt-1 text-gray-500">{{ __('Publishing and planning run on the queue, which the per-minute scheduled task works through.') }}</p>
                <table class="mt-3 w-full">
                    <thead><tr class="text-left text-gray-500"><th class="py-1">{{ __('Job') }}</th><th>{{ __('Attempts') }}</th><th>{{ __('Due') }}</th></tr></thead>
                    <tbody>
                        @forelse ($waiting as $job)
                            <tr class="border-t"><td class="py-1 font-mono">{{ $job->name }}@if ($job->running) <span class="px-2 py-0.5 rounded text-xs bg-gray-100">{{ __('running') }}</span>@endif</td><td>{{ $job->attempts }}</td><td>{{ $job->at }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-1 text-gray-500">{{ __('Nothing waiting.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-semibold text-gray-900">{{ __('Failed') }}</h3>
                <table class="mt-3 w-full">
                    <thead><tr class="text-left text-gray-500"><th class="py-1">{{ __('Job') }}</th><th>{{ __('Error') }}</th><th>{{ __('Failed at') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($failed as $job)
                            <tr class="border-t align-top">
                                <td class="py-1 font-mono whitespace-nowrap">{{ $job->name }}</td>
                                <td class="py-1 pr-3 text-red-600">{{ Str::limit($job->error, 160) }}</td>
                                <td class="py-1 whitespace-nowrap">{{ $job->at }}</td>
                                <td class="py-1 whitespace-nowrap">
                                    <form method="POST" action="{{ route('jobs.retry', $job->uuid) }}" class="inline">@csrf<button class="text-brand-600 hover:underline">{{ __('Retry') }}</button></form>
                                    <form method="POST" action="{{ route('jobs.forget', $job->uuid) }}" class="inline ml-2">@csrf @method('DELETE')<button class="text-gray-500 hover:underline">{{ __('Remove') }}</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-1 text-gray-500">{{ __('No failed jobs.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-semibold text-gray-900">{{ __('Claude, last 30 days') }}</h3>
                @php($priced = \App\Models\ClaudeCall::cost(1, 1) !== null)
                @unless ($priced)
                    <p class="mt-1 text-gray-500">{{ __('Set ANTHROPIC_PRICE_INPUT and ANTHROPIC_PRICE_OUTPUT (USD per million tokens) in .env to see the cost column.') }}</p>
                @endunless
                <table class="mt-3 w-full">
                    <thead><tr class="text-left text-gray-500"><th class="py-1">{{ __('Client') }}</th><th>{{ __('Purpose') }}</th><th class="text-right">{{ __('Calls') }}</th><th class="text-right">{{ __('Tokens in') }}</th><th class="text-right">{{ __('Tokens out') }}</th>@if ($priced)<th class="text-right">{{ __('USD') }}</th>@endif</tr></thead>
                    <tbody>
                        @forelse ($usage as $row)
                            <tr class="border-t">
                                <td class="py-1">{{ $row->client?->name ?? '—' }}</td>
                                <td>{{ str_replace('_', ' ', $row->purpose) }}</td>
                                <td class="text-right">{{ $row->calls }}</td>
                                <td class="text-right">{{ number_format($row->input_tokens) }}</td>
                                <td class="text-right">{{ number_format($row->output_tokens) }}</td>
                                @if ($priced)<td class="text-right">{{ number_format(\App\Models\ClaudeCall::cost($row->input_tokens, $row->output_tokens), 2) }}</td>@endif
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-1 text-gray-500">{{ __('No Claude calls yet.') }}</td></tr>
                        @endforelse
                        @if ($usage->isNotEmpty())
                            <tr class="border-t font-semibold">
                                <td class="py-1" colspan="2">{{ __('Total') }}</td>
                                <td class="text-right">{{ $usage->sum('calls') }}</td>
                                <td class="text-right">{{ number_format($usage->sum('input_tokens')) }}</td>
                                <td class="text-right">{{ number_format($usage->sum('output_tokens')) }}</td>
                                @if ($priced)<td class="text-right">{{ number_format(\App\Models\ClaudeCall::cost((int) $usage->sum('input_tokens'), (int) $usage->sum('output_tokens')), 2) }}</td>@endif
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
