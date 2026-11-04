<x-app-layout :client="$client">
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }} <span class="font-mono text-sm text-gray-500">{{ $post->name }}</span></h2>
            <span class="px-2 py-0.5 rounded text-xs bg-gray-100">{{ $post->state->label() }}</span>
            @foreach ($post->state->next() as $next)
                <form method="POST" action="{{ route('posts.transition', $post) }}" @if ($next === \App\Posts\PostState::Withdrawn) onsubmit="return confirm('{{ __('Take this post down on every platform? Instagram and TikTok have no API for it, so those stay until deleted by hand.') }}')" @endif>
                    @csrf
                    <input type="hidden" name="state" value="{{ $next->value }}">
                    <button type="submit" class="px-3 py-1 border rounded text-xs hover:bg-gray-100">{{ __($next->action()) }}</button>
                </form>
            @endforeach
            <a class="px-3 py-1 border rounded text-xs hover:bg-gray-100" href="{{ route('posts.export', $post) }}" title="{{ __('Media files plus a captions.txt, to post by hand or design in Canva') }}">{{ __('Export ZIP') }}</a>
            <details class="relative">
                <summary class="cursor-pointer list-none px-3 py-1 border rounded text-xs hover:bg-gray-100">{{ __('Duplicate') }}</summary>
                <form method="POST" action="{{ route('posts.duplicate', $post) }}" class="absolute left-0 z-10 mt-1 w-72 space-y-2 rounded-md border border-gray-200 bg-white p-3 text-sm shadow-lg">
                    @csrf
                    <select name="client_id" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                        @foreach (auth()->user()->clients()->get() as $other)
                            <option value="{{ $other->id }}" @selected($other->is($client))>{{ $other->name }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="date" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">
                    <p class="text-xs text-gray-500">{{ __('A Draft with the captions and settings; the media comes along only within the same client.') }}</p>
                    <x-primary-button>{{ __('Duplicate') }}</x-primary-button>
                </form>
            </details>
            @if ($post->assets->firstWhere('kind', 'image'))
                @foreach (array_keys(array_filter($post->settings['design'] ?? [])) as $lang)
                    <a class="px-3 py-1 border rounded text-xs hover:bg-gray-100" href="{{ route('posts.image', [$post, $lang]) }}" title="{{ __('The first photo with the design text burned in, ready to post') }}">{{ __('Image :lang', ['lang' => strtoupper($lang)]) }}</a>
                @endforeach
            @endif
            @if ($post->state !== \App\Posts\PostState::Published)
                <form method="POST" action="{{ route('posts.draft', $post) }}" onsubmit="this.querySelector('button').disabled = true">
                    @csrf
                    <button type="submit" class="px-3 py-1 border border-brand-300 rounded text-xs text-brand-700 hover:bg-brand-50">{{ __('Draft captions with Claude') }}</button>
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
            <div class="lg:grid lg:grid-cols-3 lg:gap-6 lg:items-start">
            <div class="lg:col-span-2">
            @if ($post->state !== \App\Posts\PostState::Published)
                <form id="post-form" method="POST" action="{{ route('posts.update', $post) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                    @method('PATCH')
                    @include('posts._form')
                </form>
            @endif
            </div>

            <div class="mt-4 lg:mt-0 space-y-4 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto">
            @include('posts._preview')
            <div id="notes" class="bg-white p-6 shadow-sm sm:rounded-lg text-sm">
                <h3 class="font-medium">{{ __('Team notes') }} <span class="text-gray-400 font-normal">({{ __('internal, the client never sees them; @FirstName pings a colleague on Slack') }})</span></h3>
                @foreach ($post->notes as $note)
                    <p class="mt-3 border-l-2 border-gray-200 pl-3 whitespace-pre-line"><span class="font-semibold">{{ $note->user?->name ?? __('removed login') }}</span> <span class="text-xs text-gray-500">{{ $note->created_at->format('j M H:i') }}</span><br>{{ $note->body }}</p>
                @endforeach
                <form method="POST" action="{{ route('posts.note', $post) }}" class="mt-4">
                    @csrf
                    <textarea name="body" rows="2" required maxlength="2000" class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm text-sm" placeholder="{{ __('e.g. @Maria can you check the Greek?') }}">{{ old('body') }}</textarea>
                    <x-input-error class="mt-1" :messages="$errors->get('body')" />
                    <x-primary-button class="mt-2">{{ __('Add note') }}</x-primary-button>
                </form>
            </div>
            </div>
            </div>
        </div>
    </div>
</x-app-layout>
