<x-guest-layout>
    <h1 class="text-lg font-semibold text-gray-900">{{ $review->client->name }}</h1>
    @php($pending = $posts->whereNull('pivot.decision'))
    <p class="mt-1 text-sm text-gray-600">
        @if ($pending->isEmpty())
            {{ __('All answered, thank you! We take it from here.') }}
        @else
            {{ __(':n posts to approve by :due. Approve each one, or tell us what to change.', ['n' => $pending->count(), 'due' => $review->due_at->format('D j M, H:i')]) }}
        @endif
    </p>

    @if (session('status'))
        <p class="mt-3 rounded-md bg-green-50 p-3 text-sm text-green-800">{{ __(session('status') === 'approved' ? 'Approved, thank you!' : 'Noted, we will make the changes.') }}</p>
    @endif
    @if ($errors->any())
        <ul class="mt-3 list-disc pl-5 text-sm text-red-600">
            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
    @endif

    <div class="mt-4 space-y-8">
        @foreach ($posts as $post)
            <section class="space-y-3">
                <p class="text-xs text-gray-500">
                    {{ $post->scheduled_at ? __('Planned for :date', ['date' => $post->scheduled_at->format('D j M, H:i')]) : __('Date to be set') }}
                    · {{ str_replace('_', ' ', $post->format) }}
                </p>
                @foreach ($post->platforms as $platform)
                    @include('portal._preview', ['post' => $post, 'platform' => $platform])
                @endforeach

                @if ($post->pivot->decision)
                    <p class="text-sm {{ $post->pivot->decision === 'approved' ? 'text-green-700' : 'text-orange-700' }}">
                        {{ __($post->pivot->decision === 'approved' ? 'Approved by :name' : 'Changes requested by :name', ['name' => $post->pivot->decided_by]) }}
                        @if ($post->pivot->comment) <span class="block text-gray-700">“{{ $post->pivot->comment }}”</span> @endif
                    </p>
                @else
                    <form method="POST" action="{{ request()->fullUrl() }}" class="space-y-2">
                        @csrf
                        <input type="hidden" name="post_id" value="{{ $post->id }}">
                        <x-text-input name="name" type="text" class="block w-full" :value="old('name', $name)" placeholder="{{ __('Your name') }}" required />
                        <textarea name="comment" rows="2" maxlength="2000" class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" placeholder="{{ __('Comment (needed when you ask for changes)') }}">{{ old('post_id') == $post->id ? old('comment') : '' }}</textarea>
                        <div class="flex gap-2">
                            <button type="submit" name="decision" value="approved" class="flex-1 py-3 rounded-md bg-green-600 text-white font-semibold">{{ __('Approve') }}</button>
                            <button type="submit" name="decision" value="changes_requested" class="flex-1 py-3 rounded-md border border-orange-400 text-orange-700 font-semibold">{{ __('Request changes') }}</button>
                        </div>
                    </form>
                @endif
            </section>
        @endforeach
    </div>
</x-guest-layout>
