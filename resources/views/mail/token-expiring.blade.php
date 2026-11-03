<x-mail::message>
# {{ $account->client->name }}: {{ str_replace('_', ' ', $account->platform) }}

{{ $account->token_expires_at->isPast()
    ? __('The login for :name expired :when. Scheduled posts for it will fail until it is reconnected.', ['name' => $account->name, 'when' => $account->token_expires_at->diffForHumans()])
    : __('The login for :name expires :when. Reconnect it before then so scheduled posts keep going out.', ['name' => $account->name, 'when' => $account->token_expires_at->diffForHumans()]) }}

<x-mail::button :url="route('clients.accounts', $account->client_id)">{{ __('Reconnect') }}</x-mail::button>

{{ __('It takes a minute: log in as the client\'s page admin, tick the same account, done.') }}

{{ config('app.name') }}
</x-mail::message>
