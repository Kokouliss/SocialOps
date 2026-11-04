<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Clients') }}</h2>
            <a href="{{ route('clients.create') }}" class="inline-flex items-center px-4 py-2 bg-brand-500 border border-transparent rounded-full font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-600">{{ __('New client') }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="mb-4 text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif
            <div class="grid gap-4 md:grid-cols-2">
                @forelse ($clients as $client)
                    <div class="bg-white shadow-sm sm:rounded-lg p-5">
                        <div class="flex flex-wrap items-baseline gap-x-3">
                            <a href="{{ route('calendar', ['client' => $client->slug]) }}" class="text-lg font-semibold text-gray-900 hover:underline">{{ $client->name }}</a>
                            <span class="font-mono text-sm text-gray-500">{{ $client->code }}</span>
                            <span class="text-sm text-gray-500">{{ __(str_replace('_', ' ', $client->type)) }} · {{ $client->location }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ collect($client->profile['accounts'])->filter()->keys()->map(fn ($p) => str_replace('_', ' ', $p))->join(', ') ?: '—' }} · {{ __('approval') }} {{ $client->profile['approval']['policy'] }}</p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <a href="{{ route('calendar', ['client' => $client->slug]) }}" class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">{{ trans_choice('{0} nothing scheduled this week|{1} :count post this week|[2,*] :count posts this week', $client->week_count) }}</a>
                            <a href="{{ route('clients.reviews', $client) }}" class="rounded-full px-2.5 py-1 {{ $client->in_review_count ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-700' }}">{{ trans_choice('{0} nothing waiting for the client|{1} :count waiting for the client|[2,*] :count waiting for the client', $client->in_review_count) }}</a>
                            <a href="{{ route('clients.comments', $client) }}" class="rounded-full px-2.5 py-1 {{ $client->open_comments_count ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700' }}">{{ trans_choice('{0} no comments to answer|{1} :count comment to answer|[2,*] :count comments to answer', $client->open_comments_count) }}</a>
                            @if ($client->failed_count)
                                <a href="{{ route('calendar', ['client' => $client->slug]) }}" class="rounded-full bg-red-100 px-2.5 py-1 text-red-800">{{ trans_choice('{1} :count post failed or needs a human|[2,*] :count posts failed or need a human', $client->failed_count) }}</a>
                            @endif
                            @if ($client->expiring_count)
                                <a href="{{ route('clients.accounts', $client) }}" class="rounded-full bg-red-100 px-2.5 py-1 text-red-800">{{ trans_choice('{1} :count token expiring|[2,*] :count tokens expiring', $client->expiring_count) }}</a>
                            @endif
                        </div>
                        <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-sm border-t border-gray-100 pt-3">
                            <a href="{{ route('clients.assets', $client) }}" class="text-brand-600 hover:underline">{{ __('Assets') }}</a>
                            <a href="{{ route('clients.grid', $client) }}" class="text-brand-600 hover:underline">{{ __('Grid') }}</a>
                            <a href="{{ route('clients.reviews', $client) }}" class="text-brand-600 hover:underline">{{ __('Approvals') }}</a>
                            <a href="{{ route('clients.comments', $client) }}" class="text-brand-600 hover:underline">{{ __('Comments') }}</a>
                            <a href="{{ route('clients.accounts', $client) }}" class="text-brand-600 hover:underline">{{ __('Accounts') }}</a>
                            <a href="{{ route('clients.reports', $client) }}" class="text-brand-600 hover:underline">{{ __('Reports') }}</a>
                            <a href="{{ route('clients.edit', $client) }}" class="text-brand-600 hover:underline">{{ __('Profile') }}</a>
                            <a href="{{ route('posts.create', $client) }}" class="text-brand-600 hover:underline">{{ __('New post') }}</a>
                            <form method="POST" action="{{ route('clients.destroy', $client) }}" class="ml-auto" onsubmit="return confirm('{{ __('Delete this client and everything it owns?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <x-empty-state class="bg-white md:col-span-2" :action="__('Add the first client')" :href="route('clients.create')">{{ __('No clients yet. A client profile holds the accounts, the voice, the pillars and the approvers; everything else hangs off it.') }}</x-empty-state>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
