<x-mail::message>
# {{ $post->client->name }}: `{{ $post->name }}`

{{ $kind === 'manual' ? __('The post is approved and its time has come, but part of it has to be posted by hand:') : __('Publishing failed after three attempts:') }}

> {{ $post->publish_error }}

@if ($post->external_ids)
{{ __('Already published: :platforms.', ['platforms' => implode(', ', array_keys($post->external_ids))]) }}
@endif

<x-mail::button :url="route('posts.edit', $post)">{{ __('Open the post') }}</x-mail::button>

{{ $kind === 'manual' ? __('Mark it published on the post page once it is up.') : __('Fix the cause, then press Schedule again.') }}

{{ config('app.name') }}
</x-mail::message>
