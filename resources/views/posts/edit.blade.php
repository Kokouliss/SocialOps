<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }} <span class="font-mono text-sm text-gray-500">{{ $post->name }}</span></h2>
            <span class="px-2 py-0.5 rounded text-xs bg-gray-100">{{ $post->state->label() }}</span>
            @foreach ($post->state->next() as $next)
                <form method="POST" action="{{ route('posts.transition', $post) }}">
                    @csrf
                    <input type="hidden" name="state" value="{{ $next->value }}">
                    <button type="submit" class="px-3 py-1 border rounded text-xs hover:bg-gray-100">{{ __($next->action()) }}</button>
                </form>
            @endforeach
            @if ($post->state !== \App\Posts\PostState::Published)
                <form method="POST" action="{{ route('posts.draft', $post) }}" onsubmit="this.querySelector('button').disabled = true">
                    @csrf
                    <button type="submit" class="px-3 py-1 border border-indigo-300 rounded text-xs text-indigo-700 hover:bg-indigo-50">{{ __('Draft captions with Claude') }}</button>
                </form>
                <form method="POST" action="{{ route('posts.destroy', $post) }}" class="ml-auto" onsubmit="return confirm('{{ __('Delete this post?') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('Delete') }}</button>
                </form>
            @endif
        </div>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p class="text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif
            @error('state') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('draft') <p class="text-sm text-red-600">{{ __('Drafting failed') }}: {{ $message }}</p> @enderror
            @if (session('notes'))
                <p class="text-sm text-blue-800 bg-blue-50 rounded-md p-3">{{ __('Notes from the drafter, check before sending for review') }}: {{ session('notes') }}</p>
            @endif
            @if ($post->publish_error)
                <p class="text-sm text-red-800 bg-red-50 rounded-md p-3">{{ __('Publishing') }}: {{ $post->publish_error }}</p>
            @endif
            @if ($post->external_ids)
                <p class="text-sm text-green-800 bg-green-50 rounded-md p-3">{{ __('Published') }}: {{ collect($post->external_ids)->map(fn ($id, $platform) => str_replace('_', ' ', $platform)." ($id)")->join(', ') }}</p>
            @endif
            @if ($post->state->isLocked())
                <p class="text-sm text-yellow-800 bg-yellow-50 rounded-md p-3">{{ $post->state === \App\Posts\PostState::Published ? __('This post is published and can no longer be edited.') : __('This post is approved and locked. Saving a change sends it back to review.') }}</p>
            @endif
            @foreach ($post->reviews()->wherePivotNotNull('comment')->get() as $review)
                <p class="text-sm text-orange-800 bg-orange-50 rounded-md p-3"><span class="font-semibold">{{ $review->pivot->decided_by }}</span> ({{ $review->pivot->decided_at }}): {{ $review->pivot->comment }}</p>
            @endforeach
            @if ($post->state !== \App\Posts\PostState::Published)
                <form method="POST" action="{{ route('posts.update', $post) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                    @method('PATCH')
                    @include('posts._form')
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
