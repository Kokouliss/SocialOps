{{-- The posts of the period in date order, one row each with a thumbnail: what phones get instead of the grid, and the "List" button on a desktop. --}}
@php($groups = $days->filter(fn ($d) => $posts->has($d->toDateString()) || isset($marks[$d->toDateString()]))->mapWithKeys(fn ($d) => [$d->toDateString() => $posts->get($d->toDateString(), collect())]))
@php($unscheduled->isEmpty() || $groups->put('', $unscheduled))
<div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100 text-sm {{ $class }}" data-view="list">
    @forelse ($groups as $key => $dayPosts)
        @php($day = $key ? \Illuminate\Support\Carbon::parse($key) : null)
        <div class="p-3">
            <div class="flex items-center gap-2 font-medium {{ $day?->isToday() ? 'text-brand-600' : 'text-gray-800' }}">
                {{ $day ? $day->translatedFormat('l j F') : __('No date yet') }}
                @foreach ($marks[$key] ?? [] as $mark)
                    <span class="font-normal text-amber-700">· {{ $mark }}</span>
                @endforeach
                @if ($day && $clientSlug)
                    <a href="{{ route('posts.create', [$clients->firstWhere('slug', $clientSlug), 'date' => $key]) }}" class="ml-auto text-gray-400 hover:text-gray-700" title="{{ __('New post') }}">+</a>
                @endif
            </div>
            @foreach ($dayPosts as $post)
                <div class="mt-2 flex items-center gap-3">
                    @if ($thumb = $post->assets->firstWhere('kind', 'image'))
                        <img src="{{ route('assets.thumb', $thumb) }}" alt="" loading="lazy" class="h-12 w-12 flex-none rounded object-cover">
                    @else
                        <span class="flex h-12 w-12 flex-none items-center justify-center rounded bg-gray-100 text-center text-[10px] uppercase leading-tight text-gray-400">{{ $post->assets->isEmpty() ? __('no media') : __('video') }}</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('posts.edit', $post) }}" class="block truncate font-mono text-xs text-gray-700 hover:underline">{{ $post->name }}</a>
                        @include('calendar._chip', ['post' => $post])
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <p class="p-3 text-gray-500">{{ __('Nothing scheduled here yet.') }}</p>
    @endforelse
</div>
