@php($colors = ['idea' => 'bg-gray-100 text-gray-700', 'draft' => 'bg-gray-100 text-gray-700', 'in_review' => 'bg-yellow-100 text-yellow-800', 'changes_requested' => 'bg-orange-100 text-orange-800', 'approved' => 'bg-green-100 text-green-800', 'scheduled' => 'bg-blue-100 text-blue-800', 'published' => 'bg-green-200 text-green-900', 'failed' => 'bg-red-100 text-red-800', 'manual_needed' => 'bg-purple-100 text-purple-800'])
<a href="{{ route('posts.edit', $post) }}" draggable="{{ $post->state->value === 'published' ? 'false' : 'true' }}" data-id="{{ $post->id }}"
   class="chip block mt-1 px-1 py-0.5 rounded truncate {{ $colors[$post->state->value] }}" title="{{ $post->name }}: {{ $post->state->label() }}">
    <span class="font-mono">{{ $post->client->code }}</span>
    {{ str_replace('_', ' ', $post->format) }}
    @if ($post->scheduled_at) <span class="text-gray-500">{{ $post->scheduled_at->format('H:i') }}</span> @endif
    · {{ $post->state->label() }}
</a>
