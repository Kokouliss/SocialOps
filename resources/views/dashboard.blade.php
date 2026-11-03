<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Needs attention') }}</h2>
    </x-slot>

    @php($quiet = $today->isEmpty() && $stuck->isEmpty() && $overdue->isEmpty() && $unanswered->isEmpty() && $tokens->isEmpty() && ! $failedJobs)
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4 text-sm">
            @if (session('status'))
                <p class="text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif
            @if ($quiet)
                <p class="bg-white p-6 shadow-sm sm:rounded-lg text-gray-500">{{ __('Nothing needs you right now.') }}</p>
            @endif

            @if ($today->isNotEmpty())
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="font-semibold text-gray-900">{{ __('Publishing today') }}</h3>
                    <ul class="mt-2 space-y-1">
                        @foreach ($today as $post)
                            <li><a class="text-brand-600 hover:underline" href="{{ route('posts.edit', $post) }}">{{ $post->name }}</a> · {{ $post->scheduled_at->format('H:i') }}
                                @if ($post->scheduled_at->lt(now()->subMinutes(10))) <span class="px-2 py-0.5 rounded text-xs bg-red-100 text-red-800">{{ __('late: is the queue running?') }}</span> @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($stuck->isNotEmpty())
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="font-semibold text-gray-900">{{ __('Failed or waiting for a human') }}</h3>
                    <ul class="mt-2 space-y-1">
                        @foreach ($stuck as $post)
                            <li><a class="text-brand-600 hover:underline" href="{{ route('posts.edit', $post) }}">{{ $post->name }}</a> · {{ $post->state->label() }} <span class="text-red-600">{{ Str::limit($post->publish_error, 120) }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($overdue->isNotEmpty())
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="font-semibold text-gray-900">{{ __('Reviews past their deadline') }}</h3>
                    <ul class="mt-2 space-y-1">
                        @foreach ($overdue as $review)
                            <li><a class="text-brand-600 hover:underline" href="{{ route('clients.reviews', $review->client) }}">{{ $review->client->name }}</a> · {{ __('due') }} {{ $review->due_at->diffForHumans() }}, {{ $review->pending()->count() }} {{ __('posts waiting') }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($unanswered->isNotEmpty())
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="font-semibold text-gray-900">{{ __('Comments and reviews without a reply') }}</h3>
                    <ul class="mt-2 space-y-1">
                        @foreach ($unanswered as $row)
                            <li><a class="text-brand-600 hover:underline" href="{{ route('clients.comments', $row->client) }}">{{ $row->client->name }}</a> · {{ $row->n }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($tokens->isNotEmpty())
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="font-semibold text-gray-900">{{ __('Tokens expiring') }}</h3>
                    <ul class="mt-2 space-y-1">
                        @foreach ($tokens as $account)
                            <li><a class="text-brand-600 hover:underline" href="{{ route('clients.accounts', $account->client) }}">{{ $account->client->name }} {{ $account->platform }}</a> · {{ $account->token_expires_at->isPast() ? __('expired') : __('expires').' '.$account->token_expires_at->diffForHumans() }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($failedJobs)
                <p class="bg-white p-6 shadow-sm sm:rounded-lg"><a class="text-brand-600 hover:underline" href="{{ route('jobs.index') }}">{{ $failedJobs }} {{ __('failed jobs') }}</a></p>
            @endif

            <form method="POST" action="{{ route('refresh') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                <x-primary-button>{{ __('Refresh after update') }}</x-primary-button>
                <p class="mt-2 text-gray-500">{{ __('Press this once after "Pull now" in Plesk Git: it runs new database migrations and rebuilds the caches.') }}</p>
            </form>
        </div>
    </div>
</x-app-layout>
