<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $report->client->name }}: {{ $report->title }}</h2>
            <span class="px-2 py-0.5 rounded text-xs bg-gray-100">{{ __(\App\Models\Report::KINDS[$report->kind] ?? $report->kind) }}</span>
            <a class="px-3 py-1 border rounded text-xs hover:bg-gray-100" href="{{ route('reports.download', $report) }}">{{ __('Download .md') }}</a>
            @if ($report->kind === 'monthly')
                <form method="POST" action="{{ route('reports.send', $report) }}" onsubmit="return confirm('{{ __('Mail this report to the client\'s approvers, with the numbers as a CSV?') }}')">@csrf<button class="px-3 py-1 border border-brand-300 rounded text-xs text-brand-700 hover:bg-brand-50">{{ __('Email to client') }}</button></form>
            @endif
            <a class="text-xs text-gray-500 hover:underline" href="{{ route('clients.reports', $report->client) }}">{{ __('All reports') }}</a>
        </div>
    </x-slot>

    @include('reports._body')
</x-app-layout>
