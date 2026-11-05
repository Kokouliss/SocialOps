<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-semibold tracking-tight text-gray-900">{{ __('Jobs') }}</h2>
    </x-slot>

    @php($th = 'px-6 py-2 font-medium')
    @php($td = 'px-6 py-2')
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4 text-sm">
            @if (session('status'))
                <p class="text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif

            <x-section :title="__('Waiting')" :description="__('Publishing and planning run on the queue, which the per-minute scheduled task works through.')" flush>
                <table class="w-full">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="{{ $th }}">{{ __('Job') }}</th><th class="{{ $th }}">{{ __('Attempts') }}</th><th class="{{ $th }}">{{ __('Due') }}</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($waiting as $job)
                            <tr><td class="{{ $td }} font-mono">{{ $job->name }}@if ($job->running) <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{{ __('running') }}</span>@endif</td><td class="{{ $td }}">{{ $job->attempts }}</td><td class="{{ $td }}">{{ $job->at }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="{{ $td }} text-gray-500">{{ __('Nothing waiting.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-section>

            <x-section :title="__('Failed')" flush>
                <table class="w-full">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="{{ $th }}">{{ __('Job') }}</th><th class="{{ $th }}">{{ __('Error') }}</th><th class="{{ $th }}">{{ __('Failed at') }}</th><th></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($failed as $job)
                            <tr class="align-top">
                                <td class="{{ $td }} font-mono whitespace-nowrap">{{ $job->name }}</td>
                                <td class="{{ $td }} text-red-600">{{ Str::limit($job->error, 160) }}</td>
                                <td class="{{ $td }} whitespace-nowrap">{{ $job->at }}</td>
                                <td class="{{ $td }} whitespace-nowrap">
                                    <form method="POST" action="{{ route('jobs.retry', $job->uuid) }}" class="inline">@csrf<button class="font-medium text-gray-900 hover:text-brand-600">{{ __('Retry') }}</button></form>
                                    <form method="POST" action="{{ route('jobs.forget', $job->uuid) }}" class="inline ml-2">@csrf @method('DELETE')<button class="text-gray-500 hover:underline">{{ __('Remove') }}</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="{{ $td }} text-gray-500">{{ __('No failed jobs.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-section>

            @php($priced = \App\Models\ClaudeCall::cost(1, 1) !== null)
            <x-section :title="__('Claude, last 30 days')" :description="$priced ? null : __('Set ANTHROPIC_PRICE_INPUT and ANTHROPIC_PRICE_OUTPUT (USD per million tokens) in .env to see the cost column.')" flush>
                <table class="w-full">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="{{ $th }}">{{ __('Client') }}</th><th class="{{ $th }}">{{ __('Purpose') }}</th><th class="{{ $th }} text-right">{{ __('Calls') }}</th><th class="{{ $th }} text-right">{{ __('Tokens in') }}</th><th class="{{ $th }} text-right">{{ __('Tokens out') }}</th>@if ($priced)<th class="{{ $th }} text-right">{{ __('USD') }}</th>@endif</tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($usage as $row)
                            <tr>
                                <td class="{{ $td }}">{{ $row->client?->name ?? '—' }}</td>
                                <td class="{{ $td }}">{{ str_replace('_', ' ', $row->purpose) }}</td>
                                <td class="{{ $td }} text-right">{{ $row->calls }}</td>
                                <td class="{{ $td }} text-right">{{ number_format($row->input_tokens) }}</td>
                                <td class="{{ $td }} text-right">{{ number_format($row->output_tokens) }}</td>
                                @if ($priced)<td class="{{ $td }} text-right">{{ number_format(\App\Models\ClaudeCall::cost($row->input_tokens, $row->output_tokens), 2) }}</td>@endif
                            </tr>
                        @empty
                            <tr><td colspan="6" class="{{ $td }} text-gray-500">{{ __('No Claude calls yet.') }}</td></tr>
                        @endforelse
                        @if ($usage->isNotEmpty())
                            <tr class="font-semibold">
                                <td class="{{ $td }}" colspan="2">{{ __('Total') }}</td>
                                <td class="{{ $td }} text-right">{{ $usage->sum('calls') }}</td>
                                <td class="{{ $td }} text-right">{{ number_format($usage->sum('input_tokens')) }}</td>
                                <td class="{{ $td }} text-right">{{ number_format($usage->sum('output_tokens')) }}</td>
                                @if ($priced)<td class="{{ $td }} text-right">{{ number_format(\App\Models\ClaudeCall::cost((int) $usage->sum('input_tokens'), (int) $usage->sum('output_tokens')), 2) }}</td>@endif
                            </tr>
                        @endif
                    </tbody>
                </table>
            </x-section>
        </div>
    </div>
</x-app-layout>
