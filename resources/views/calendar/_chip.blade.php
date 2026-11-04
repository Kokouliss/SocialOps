<a href="{{ route('posts.edit', $post) }}" draggable="{{ $post->state->value === 'published' ? 'false' : 'true' }}" data-id="{{ $post->id }}"
   class="chip block mt-1 px-1 py-0.5 rounded truncate {{ $post->state->color() }}" title="{{ $post->name }}: {{ $post->state->label() }}">
    @if ($thumb = $post->assets->firstWhere('kind', 'image'))<img src="{{ route('assets.thumb', $thumb) }}" alt="" loading="lazy" class="inline h-4 w-4 rounded object-cover align-text-bottom">@endif
    <span class="font-mono">{{ $post->client->code }}</span>
    {{ str_replace('_', ' ', $post->format) }}
    @if ($post->scheduled_at) <span class="text-gray-500">{{ $post->scheduled_at->format('H:i') }}</span> @endif
    · {{ $post->state->label() }}@if ($post->settings['repeat'] ?? null) ↻@endif
</a>
