<x-mail::message>
# {{ \App\Mail\LinkRequested::title($kind, $client->name) }}

{{ __('Someone opened this link after it had expired, or without its full address, and pressed "Ask for a new link":') }}

`{{ $path }}`

{{ \App\Mail\LinkRequested::hint($kind) }}

<x-mail::button :url="$staffUrl">{{ __('Open the page') }}</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
