<x-guest-layout>
    <h1 class="text-lg font-semibold">{{ $report->client->name }}: {{ $report->title }}</h1>
    <p class="mt-1 text-xs text-gray-500">{{ __('Written by :agency on :date.', ['agency' => config('app.name'), 'date' => $report->created_at->format('d-m-Y')]) }}</p>
    <div class="mt-4 text-sm text-gray-800 report -mx-6 sm:mx-0">@include('reports._body')</div>
</x-guest-layout>
