<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }}: {{ __('comments and reviews') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6 text-sm">
            @if (session('status'))
                <p class="text-green-600">{{ str_starts_with(session('status'), 'Claude: ') ? session('status') : __(ucfirst(str_replace('-', ' ', session('status')))).'.' }}</p>
            @endif
            @error('comment') <p class="text-red-600">{{ $message }}</p> @enderror

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="font-semibold text-gray-900">{{ __('To answer') }} <span class="font-normal text-gray-500">({{ $open->count() }})</span></h3>
                <p class="mt-1 text-gray-500">{{ __('Comments under the last 30 days of Instagram and Facebook posts and Google reviews, fetched every hour. Claude drafts, you send.') }}</p>
                @forelse ($open as $comment)
                    <div id="comment-{{ $comment->id }}" class="mt-4 border-t pt-4 grid gap-3 md:grid-cols-2">
                        <div>
                            <p class="text-gray-500">{{ $comment->rating ? __('Google review') : ucfirst($comment->platform) }} · <span class="font-medium text-gray-800">{{ $comment->author ?? '?' }}</span>@if ($comment->rating) · <span class="text-brand-600">{{ str_repeat('★', $comment->rating) }}</span>{{ str_repeat('☆', 5 - $comment->rating) }}@endif · {{ $comment->posted_at->format('D j M, H:i') }}
                                @if ($comment->post) · <a href="{{ route('posts.edit', $comment->post) }}" class="font-mono text-xs text-brand-600 hover:underline">{{ $comment->post->name }}</a> @endif
                            </p>
                            <p class="mt-1 whitespace-pre-line">{{ $comment->text }}</p>
                            <div class="mt-2 flex gap-3">
                                <form method="POST" action="{{ route('comments.draft', $comment) }}" onsubmit="this.querySelector('button').disabled = true">@csrf<button class="text-brand-600 hover:underline">{{ __('Draft reply with Claude') }}</button></form>
                                @if ($comment->platform !== 'google_business')
                                    <form method="POST" action="{{ route('comments.hide', $comment) }}" onsubmit="return confirm('{{ __('Hide this comment on the platform?') }}')">@csrf<button class="text-gray-500 hover:underline">{{ __('Hide') }}</button></form>
                                @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('comments.reply', $comment) }}">
                            @csrf
                            <textarea name="reply" rows="3" class="block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm text-sm" placeholder="{{ __('Your reply') }}">{{ old('reply', $comment->reply) }}</textarea>
                            <x-primary-button class="mt-2">{{ __('Send reply') }}</x-primary-button>
                        </form>
                    </div>
                @empty
                    <p class="mt-3 text-gray-500">{{ __('Nothing to answer.') }}</p>
                @endforelse
            </div>

            @if ($done->isNotEmpty())
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="font-semibold text-gray-900">{{ __('Answered or hidden, last 30 days') }}</h3>
                    @foreach ($done as $comment)
                        <div id="comment-{{ $comment->id }}" class="mt-3 border-t pt-3">
                            <p class="text-gray-500">{{ $comment->rating ? __('Google review').' '.str_repeat('★', $comment->rating) : ucfirst($comment->platform) }} · <span class="font-medium text-gray-800">{{ $comment->author ?? '?' }}</span> · {{ $comment->posted_at->format('D j M, H:i') }} · {{ $comment->hidden_at ? __('hidden') : __('replied :when', ['when' => $comment->replied_at->format('D j M, H:i')]) }}</p>
                            <p class="mt-1 whitespace-pre-line">{{ $comment->text }}</p>
                            @if ($comment->replied_at)<p class="mt-1 pl-4 border-l-2 whitespace-pre-line text-gray-700">{{ $comment->reply }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
