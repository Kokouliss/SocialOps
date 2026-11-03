<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $report->client->name }}: {{ $report->title }}</h2>
            <span class="px-2 py-0.5 rounded text-xs bg-gray-100">{{ __(\App\Models\Report::KINDS[$report->kind] ?? $report->kind) }}</span>
            <a class="px-3 py-1 border rounded text-xs hover:bg-gray-100" href="{{ route('reports.download', $report) }}">{{ __('Download .md') }}</a>
            <a class="text-xs text-gray-500 hover:underline" href="{{ route('clients.reports', $report->client) }}">{{ __('All reports') }}</a>
        </div>
    </x-slot>

    <style>
        .report h1 { font-size: 1.5rem; font-weight: 600; margin: 1.5rem 0 .5rem }
        .report h2 { font-size: 1.2rem; font-weight: 600; margin: 1.25rem 0 .5rem }
        .report h3 { font-weight: 600; margin: 1rem 0 .25rem }
        .report p, .report ul, .report ol, .report blockquote { margin: .5rem 0 }
        .report ul { list-style: disc; padding-left: 1.5rem }
        .report ol { list-style: decimal; padding-left: 1.5rem }
        .report a { color: #E12D00; text-decoration: underline }
        .report blockquote { border-left: 3px solid #d4d4d4; padding-left: .75rem; color: #737373 }
        .report code { font-family: ui-monospace, monospace; font-size: .85em }
    </style>
    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white p-6 shadow-sm sm:rounded-lg text-sm text-gray-800 report">{!! $html !!}</div>
        </div>
    </div>
</x-app-layout>
