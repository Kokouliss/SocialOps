<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Clients') }}</h2>
            <a href="{{ route('clients.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-500 border border-transparent rounded-full font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-600">{{ __('New client') }}</a>
        </div>
    </x-slot>

    @php($colors = ['active' => 'bg-green-100 text-green-800', 'waiting_dates' => 'bg-gray-100 text-gray-700', 'waiting' => 'bg-yellow-100 text-yellow-800', 'seasonal' => 'bg-blue-100 text-blue-800', 'stopped' => 'bg-red-100 text-red-800'])
    @php($priorities = ['high' => 'text-red-600 font-medium', 'medium' => 'text-orange-800', 'low' => 'text-gray-500'])
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="mb-4 text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif
            @if ($clients->isEmpty())
                <x-empty-state class="bg-white" :action="__('Add the first client')" :href="route('clients.create')">{{ __('No clients yet. A client profile holds the accounts, the voice, the pillars and the approvers; everything else hangs off it.') }}</x-empty-state>
            @else
            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3">{{ __('Client') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3">{{ __('Priority') }}</th>
                            <th class="px-4 py-3">{{ __('Next plan due') }}</th>
                            <th class="px-4 py-3">{{ __('Rhythm') }}</th>
                            <th class="px-4 py-3">{{ __('Scheduled until') }}</th>
                            <th class="px-4 py-3">{{ __('Pending') }}</th>
                            <th class="px-4 py-3">{{ __('Links') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($clients as $client)
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <a href="{{ route('calendar', ['client' => $client->slug]) }}" class="font-semibold text-gray-900 hover:underline">{{ $client->name }}</a>
                                    <span class="font-mono text-xs text-gray-500">{{ $client->code }}</span>
                                    <p class="text-xs text-gray-500">{{ __(str_replace('_', ' ', $client->type)) }} · {{ collect($client->profile['accounts'])->filter()->keys()->map(fn ($p) => str_replace('_', ' ', $p))->join(', ') ?: '—' }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                        <a href="{{ route('calendar', ['client' => $client->slug]) }}" class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">{{ trans_choice('{0} nothing scheduled this week|{1} :count post this week|[2,*] :count posts this week', $client->week_count) }}</a>
                                        <a href="{{ route('clients.reviews', $client) }}" class="rounded-full px-2.5 py-1 {{ $client->in_review_count ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700' }}">{{ trans_choice('{0} nothing waiting for the client|{1} :count waiting for the client|[2,*] :count waiting for the client', $client->in_review_count) }}</a>
                                        @if (config('features.publishing'))
                                        <a href="{{ route('clients.comments', $client) }}" class="rounded-full px-2.5 py-1 {{ $client->open_comments_count ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700' }}">{{ trans_choice('{0} no comments to answer|{1} :count comment to answer|[2,*] :count comments to answer', $client->open_comments_count) }}</a>
                                        @if ($client->failed_count)
                                            <a href="{{ route('calendar', ['client' => $client->slug]) }}" class="rounded-full bg-red-100 px-2.5 py-1 text-red-800">{{ trans_choice('{1} :count post failed or needs a human|[2,*] :count posts failed or need a human', $client->failed_count) }}</a>
                                        @endif
                                        @if ($client->expiring_count)
                                            <a href="{{ route('clients.accounts', $client) }}" class="rounded-full bg-red-100 px-2.5 py-1 text-red-800">{{ trans_choice('{1} :count token expiring|[2,*] :count tokens expiring', $client->expiring_count) }}</a>
                                        @endif
                                        @endif
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                        <a href="{{ route('clients.edit', $client) }}" class="text-brand-600 hover:underline">{{ __('Profile') }}</a>
                                        <a href="{{ route('clients.assets', $client) }}" class="text-brand-600 hover:underline">{{ __('Assets') }}</a>
                                        <a href="{{ route('clients.grid', $client) }}" class="text-brand-600 hover:underline">{{ __('Grid') }}</a>
                                        <a href="{{ route('clients.reviews', $client) }}" class="text-brand-600 hover:underline">{{ __('Approvals') }}</a>
                                        @if (config('features.publishing'))
                                        <a href="{{ route('clients.comments', $client) }}" class="text-brand-600 hover:underline">{{ __('Comments') }}</a>
                                        <a href="{{ route('clients.accounts', $client) }}" class="text-brand-600 hover:underline">{{ __('Accounts') }}</a>
                                        @endif
                                        <a href="{{ route('clients.reports', $client) }}" class="text-brand-600 hover:underline">{{ __('Reports') }}</a>
                                        <a href="{{ route('posts.create', $client) }}" class="text-brand-600 hover:underline">{{ __('New post') }}</a>
                                        <form method="POST" action="{{ route('clients.destroy', $client) }}" onsubmit="return confirm('{{ __('Delete this client and everything it owns?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-block whitespace-nowrap rounded px-2 py-0.5 text-xs {{ $colors[$client->status] ?? 'bg-gray-100 text-gray-700' }}">{{ __(\App\Models\Client::STATUSES[$client->status] ?? $client->status) }}</span>
                                    @if ($client->owner)<p class="mt-1 text-xs text-gray-500">{{ $client->owner }}</p>@endif
                                </td>
                                <td class="px-4 py-3 {{ $priorities[$client->priority] ?? '' }}">{{ $client->priority ? __(\App\Models\Client::PRIORITIES[$client->priority]) : '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($client->plan_due)<span class="{{ $client->plan_due->isPast() ? 'text-red-600 font-medium' : '' }}">{{ $client->plan_due->format('d-m-Y') }}</span>@else — @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-700">
                                    @if ($client->posts_per_week !== null || $client->stories_per_week !== null)<p>{{ __(':posts posts, :stories stories a week', ['posts' => $client->posts_per_week ?? 0, 'stories' => $client->stories_per_week ?? 0]) }}</p>@endif
                                    @if ($client->tiktoks_per_month !== null)<p>{{ __(':n TikToks a month', ['n' => $client->tiktoks_per_month]) }}</p>@endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $client->scheduled_until ? \Carbon\Carbon::parse($client->scheduled_until)->format('d-m-Y') : '—' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-700 whitespace-pre-line max-w-sm">{{ $client->pending }}</td>
                                <td class="px-4 py-3 text-xs">
                                    @foreach ($client->links ?? [] as $link)
                                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="block text-brand-600 hover:underline">{{ $link['label'] ?: parse_url($link['url'], PHP_URL_HOST) }}</a>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
