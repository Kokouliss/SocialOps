@php
    // Same tab row on every page of a client; the switcher keeps the page when the route is client-scoped.
    $name = request()->route()->getName();
    $switch = fn ($c) => in_array($name, ['clients.assets', 'clients.grid', 'clients.reviews', 'clients.deadlines', 'clients.comments', 'clients.accounts', 'clients.reports', 'clients.edit', 'posts.create']) ? route($name, $c) : route('calendar', ['client' => $c->slug]);
    $tabs = [
        [__('Calendar'), route('calendar', ['client' => $client->slug]), request()->routeIs('calendar', 'posts.*'), null],
        [__('Assets'), route('clients.assets', $client), request()->routeIs('clients.assets'), null],
        [__('Grid'), route('clients.grid', $client), request()->routeIs('clients.grid'), null],
        [__('Approvals'), route('clients.reviews', $client), request()->routeIs('clients.reviews'), $client->posts()->where('state', \App\Posts\PostState::InReview)->count()],
        [__('Deadlines'), route('clients.deadlines', $client), request()->routeIs('clients.deadlines'), $client->deadlines()->dueSoon()->count()],
        ...(config('features.publishing') ? [
            [__('Comments'), route('clients.comments', $client), request()->routeIs('clients.comments'), $client->comments()->open()->count()],
            [__('Accounts'), route('clients.accounts', $client), request()->routeIs('clients.accounts'), null],
        ] : []),
        [__('Reports'), route('clients.reports', $client), request()->routeIs('clients.reports', 'reports.show'), null],
        [__('Profile'), route('clients.edit', $client), request()->routeIs('clients.edit'), null],
    ];
@endphp
<nav class="mt-4 -mb-6 flex items-center gap-1 overflow-x-auto text-sm" aria-label="{{ $client->name }}">
    <select onchange="location.href = this.value" class="mr-2 mb-2 rounded-md border-gray-300 py-1 text-sm font-medium" aria-label="{{ __('Client') }}">
        @foreach (auth()->user()->clients()->get() as $other)
            <option value="{{ $switch($other) }}" @selected($other->is($client))>{{ $other->name }}</option>
        @endforeach
    </select>
    @foreach ($tabs as [$label, $url, $active, $count])
        <a href="{{ $url }}" class="whitespace-nowrap px-3 py-2 border-b-2 {{ $active ? 'border-brand-500 text-gray-900 font-medium' : 'border-transparent text-gray-500 hover:text-gray-900' }}">{{ $label }}@if ($count)<span class="ml-1 rounded-full bg-brand-100 px-1.5 text-xs text-brand-700">{{ $count }}</span>@endif</a>
    @endforeach
    <a href="{{ route('posts.create', $client) }}" class="ml-auto mb-2 whitespace-nowrap inline-flex items-center px-3 py-1.5 bg-brand-500 rounded-full font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-600">{{ __('New post') }}</a>
</nav>
