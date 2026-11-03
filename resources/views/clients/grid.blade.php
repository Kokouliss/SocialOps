<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }}: {{ __('Instagram grid') }}</h2>
            <span class="text-sm text-gray-500">{{ __('Newest first, as it will look. Drag a post on the calendar to change the order.') }}</span>
            <a class="ml-auto px-2 py-1 border rounded text-sm" href="{{ route('calendar', ['client' => $client->slug]) }}">{{ __('Calendar') }}</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-md mx-auto px-4">
            @if ($posts->isEmpty())
                <p class="text-sm text-gray-500">{{ __('No Instagram feed posts with a date yet.') }}</p>
            @endif
            <div class="grid grid-cols-3 gap-px bg-gray-200">
                @foreach ($posts as $post)
                    @php($asset = $post->assets->first())
                    <a href="{{ route('posts.edit', $post) }}" class="relative block aspect-square bg-gray-900 overflow-hidden" title="{{ $post->name }}: {{ $post->state->label() }}">
                        @if ($asset?->kind === 'image')
                            <img src="{{ route('assets.thumb', $asset) }}" alt="" class="w-full h-full object-cover" loading="lazy">
                        @else
                            <span class="absolute inset-0 flex items-center justify-center text-xs text-gray-300">{{ $asset ? __('video') : __('no image yet') }}</span>
                        @endif
                        @if ($post->format !== 'feed')
                            <span class="absolute top-1 right-1 rounded bg-black/60 px-1 text-[10px] text-white">{{ $post->format === 'reel' ? '▶' : '▣' }}</span>
                        @endif
                        <span class="absolute bottom-0 inset-x-0 bg-black/60 px-1 py-0.5 text-[10px] text-white truncate">{{ $post->scheduled_at->format('j M') }} · {{ $post->state->label() }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
