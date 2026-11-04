<x-app-layout :client="$client">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }}: {{ __('approvals') }}</h2>
    </x-slot>

    @php($approval = $client->profile['approval'])
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <p class="text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif
            @error('posts') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <form method="POST" action="{{ route('reviews.store', $client) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                <h3 class="font-semibold text-gray-900">{{ __('Send posts for approval') }}</h3>
                <p class="mt-1 text-sm text-gray-600">
                    {{ __('Policy: :policy, :hours h to answer, approvers: :approvers.', ['policy' => $approval['policy'], 'hours' => $approval['sla_hours'], 'approvers' => collect($approval['approvers'])->map(fn ($a) => "{$a['name']} <{$a['email']}>")->join(', ') ?: '—']) }}
                    @if ($approval['auto_approve_on_timeout']) {{ __('Posts are approved automatically when the time runs out.') }} @endif
                </p>
                <div class="mt-4 space-y-2">
                    @forelse ($candidates as $post)
                        <label class="flex items-center gap-3 text-sm">
                            <input type="checkbox" name="posts[]" value="{{ $post->id }}" class="rounded border-gray-300" />
                            <span class="font-mono">{{ $post->name }}</span>
                            <x-state-badge :state="$post->state" />
                            <span class="text-gray-500">{{ $post->scheduled_at?->format('D j M, H:i') ?? __('not scheduled') }}</span>
                        </label>
                    @empty
                        <x-empty-state :action="__('New post')" :href="route('posts.create', $client)">{{ __('No draft posts waiting. A post shows here once it exists on the calendar; tick it and send it, the approvers get the link by email.') }}</x-empty-state>
                    @endforelse
                </div>
                @if ($candidates->isNotEmpty())
                    <x-primary-button class="mt-4">{{ __('Send for approval') }}</x-primary-button>
                @endif
            </form>

            @foreach ($reviews as $review)
                <div class="bg-white p-6 shadow-sm sm:rounded-lg text-sm">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="font-semibold text-gray-900">{{ __('Sent :at', ['at' => $review->created_at->format('D j M, H:i')]) }}</span>
                        <span class="text-gray-500">{{ __('due :at', ['at' => $review->due_at->format('D j M, H:i')]) }}</span>
                        @if ($review->completed_at)
                            <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-800">{{ __('Answered :at', ['at' => $review->completed_at->format('D j M, H:i')]) }}</span>
                        @elseif ($review->due_at->isPast())
                            <span class="px-2 py-0.5 rounded text-xs bg-red-100 text-red-800">{{ __('Overdue') }}</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-800">{{ __('Waiting') }}{{ $review->reminded_at ? ', '.__('reminded') : '' }}</span>
                        @endif
                    </div>
                    <x-text-input type="text" class="mt-2 block w-full font-mono text-xs" :value="$review->url()" readonly onclick="this.select()" />
                    <ul class="mt-3 divide-y divide-gray-100">
                        @foreach ($review->posts as $post)
                            <li class="py-2 flex flex-wrap items-baseline gap-3">
                                <a href="{{ route('posts.edit', $post) }}" class="font-mono text-brand-600 hover:underline">{{ $post->name }}</a>
                                @if ($post->pivot->decision)
                                    <span class="{{ $post->pivot->decision === 'approved' ? 'text-green-700' : 'text-orange-700' }}">{{ __($post->pivot->decision === 'approved' ? 'Approved' : 'Changes requested') }} · {{ $post->pivot->decided_by }}</span>
                                    @if ($post->pivot->comment) <span class="text-gray-700">“{{ $post->pivot->comment }}”</span> @endif
                                @else
                                    <span class="text-gray-400">{{ __('no answer yet') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
