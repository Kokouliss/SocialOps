<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Needs attention') }}</h2>
    </x-slot>

    @php($quiet = $week->isEmpty() && $drafts->isEmpty() && $stuck->isEmpty() && $waiting->isEmpty() && $unanswered->isEmpty() && $tokens->isEmpty() && ! $failedJobs)
    @php($line = fn ($post) => '<a class="text-brand-600 hover:underline" href="'.route('posts.edit', $post).'">'.e($post->name).'</a> <span class="text-gray-500">'.e($post->client->code.' · '.collect($post->platforms)->map(fn ($p) => str_replace('_', ' ', ucfirst($p)))->join(', ')).'</span>')
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4 text-sm">
            @if (session('status'))
                <p class="text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif
            @if ($quiet)
                <p class="bg-white p-6 shadow-sm sm:rounded-lg text-gray-500">{{ __('Nothing needs you right now.') }}</p>
            @endif

            <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
                <div class="space-y-4">
                    @if ($today->isNotEmpty() || $tomorrow->isNotEmpty())
                        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 class="font-semibold text-gray-900">{{ __('Going out') }}</h3>
                            @foreach ([__('Today') => $today, __('Tomorrow') => $tomorrow] as $label => $posts)
                                @if ($posts->isNotEmpty())
                                    <h4 class="mt-3 text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</h4>
                                    <ul class="mt-1 space-y-1">
                                        @foreach ($posts as $post)
                                            <li><span class="font-medium text-gray-700">{{ $post->scheduled_at->format('H:i') }}</span> {!! $line($post) !!}
                                                @if ($post->scheduled_at->lt(now()->subMinutes(10))) <span class="px-2 py-0.5 rounded text-xs bg-red-100 text-red-800">{{ __('late: is the queue running?') }}</span> @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    @if ($drafts->isNotEmpty())
                        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 class="font-semibold text-gray-900">{{ __('Due within 7 days, not sent for approval yet') }}</h3>
                            <ul class="mt-2 space-y-1">
                                @foreach ($drafts as $post)
                                    <li><span class="font-medium text-gray-700">{{ $post->scheduled_at->translatedFormat('D j M H:i') }}</span> {!! $line($post) !!} · {{ __($post->state->label()) }}</li>
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
                </div>

                <div class="space-y-4">
                    @if ($waiting->isNotEmpty())
                        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 class="font-semibold text-gray-900">{{ __('Waiting for the client') }}</h3>
                            <ul class="mt-2 space-y-1">
                                @foreach ($waiting as $review)
                                    <li><a class="text-brand-600 hover:underline" href="{{ route('clients.reviews', $review->client) }}">{{ $review->client->name }}</a> · {{ $review->pending_count }} {{ __('posts waiting') }},
                                        <span class="{{ $review->due_at->isPast() ? 'rounded bg-red-100 px-2 py-0.5 text-xs text-red-800' : 'text-gray-500' }}">{{ __('due') }} {{ $review->due_at->diffForHumans() }}</span>
                                    </li>
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
                </div>
            </div>

            @if ($clients->isNotEmpty())
                <div class="bg-white p-6 shadow-sm sm:rounded-lg overflow-x-auto">
                    <h3 class="font-semibold text-gray-900">{{ __('Per client, the next 7 days') }}</h3>
                    <table class="mt-2 w-full text-left">
                        <thead class="text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="py-1 pr-4 font-medium">{{ __('Client') }}</th><th class="py-1 pr-4 font-medium">{{ __('Going out') }}</th><th class="py-1 pr-4 font-medium">{{ __('Waiting for client') }}</th><th class="py-1 pr-4 font-medium">{{ __('Not sent yet') }}</th><th class="py-1 pr-4 font-medium">{{ __('Failed') }}</th><th class="py-1 font-medium">{{ __('Comments to answer') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($clients as $client)
                                @php($cells = [[$week->where('client_id', $client->id)->count(), route('calendar', ['client' => $client->slug]), ''], [$waiting->where('client_id', $client->id)->sum('pending_count'), route('clients.reviews', $client), 'text-yellow-700'], [$drafts->where('client_id', $client->id)->count(), route('calendar', ['client' => $client->slug]), ''], [$stuck->where('client_id', $client->id)->count(), route('calendar', ['client' => $client->slug]), 'text-red-700'], [$unanswered->firstWhere('client_id', $client->id)?->n ?? 0, route('clients.comments', $client), 'text-red-700']])
                                <tr>
                                    <td class="py-1.5 pr-4"><a class="text-brand-600 hover:underline" href="{{ route('calendar', ['client' => $client->slug]) }}">{{ $client->name }}</a></td>
                                    @foreach ($cells as [$n, $url, $color])
                                        <td class="py-1.5 pr-4 tabular-nums {{ $n ? $color : 'text-gray-400' }}">@if ($n)<a class="hover:underline" href="{{ $url }}">{{ $n }}</a>@else 0 @endif</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <form method="POST" action="{{ route('refresh') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                <x-primary-button>{{ __('Refresh after update') }}</x-primary-button>
                <p class="mt-2 text-gray-500">{{ __('Press this once after "Pull now" in Plesk Git: it runs new database migrations and rebuilds the caches.') }}</p>
            </form>
        </div>
    </div>
</x-app-layout>
