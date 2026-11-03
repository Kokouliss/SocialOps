<x-mail::message>
# {{ $report->client->name }}: {{ $report->title }}

{{ __('The month in numbers and what we read in them. The numbers are also attached as a spreadsheet (CSV).') }}

<x-mail::button :url="$url">{{ __('Read the report') }}</x-mail::button>

{{ __('The link is personal and valid until :until. Please do not forward it.', ['until' => now()->addDays(\App\Reporting\MonthlyReport::LINK_DAYS)->format('j M Y')]) }}

{{ config('app.name') }}
</x-mail::message>
