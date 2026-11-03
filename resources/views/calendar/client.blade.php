<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900">{{ $client->name }}: {{ __('your posts') }}</h1>
    <p class="mt-1 text-sm text-gray-600">{{ __('What is planned, waiting for you, scheduled and published this month and the next. Posts waiting for your approval come with their own email.') }}</p>

    @forelse ($days as $date => $posts)
        <h2 class="mt-5 text-sm font-semibold text-gray-800">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l j F') }}</h2>
        <ul class="mt-1 divide-y divide-gray-100 text-sm">
            @foreach ($posts as $post)
                <li class="py-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-gray-500">{{ $post->scheduled_at->format('H:i') }}</span>
                        <span class="font-medium">{{ collect($post->platforms)->map(fn ($p) => str_replace('_', ' ', ucfirst($p)))->join(', ') }}</span>
                        <span class="text-gray-500">{{ str_replace('_', ' ', $post->format) }}</span>
                        <span class="ml-auto rounded px-2 py-0.5 text-xs {{ $post->state->value === 'published' ? 'bg-green-100 text-green-800' : ($post->state->value === 'in_review' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700') }}">{{ __(\App\Http\Controllers\CalendarController::CLIENT_STATES[$post->state->value]) }}</span>
                    </div>
                    @if ($caption = $post->variants->firstWhere('lang', $lang)?->caption)
                        <p class="mt-1 text-gray-600 line-clamp-2">{{ $caption }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @empty
        <p class="mt-4 text-sm text-gray-500">{{ __('Nothing planned yet for this month and the next.') }}</p>
    @endforelse
</x-guest-layout>
