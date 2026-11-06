{{-- The side navigation: the pages, then every client of this login, then the login itself. A top bar with a menu button on phones. --}}
@php($clients = auth()->user()->clients()->orderBy('name')->get(['id', 'name', 'slug']))
<nav x-data="{ open: false }" class="bg-ink text-neutral-200 md:sticky md:top-0 md:flex md:h-screen md:w-60 md:shrink-0 md:flex-col">
    <div class="flex h-14 items-center justify-between px-4 md:h-16 md:px-5">
        <a href="{{ route('dashboard') }}"><x-application-logo class="block h-9 w-auto" /></a>
        <button type="button" @click="open = ! open" class="rounded-md p-2 text-neutral-300 hover:text-white md:hidden" aria-label="{{ __('Menu') }}">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div :class="open ? 'block' : 'hidden'" class="hidden flex-1 overflow-y-auto px-3 pb-4 md:block">
        <div class="space-y-0.5">
            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">{{ __('Dashboard') }}</x-nav-link>
            <x-nav-link :href="route('calendar')" :active="request()->routeIs('calendar') && ! request('client')">{{ __('Calendar') }}</x-nav-link>
            @php($toPost = \App\Http\Controllers\TodayController::toPost(auth()->user(), today())->count())
            <x-nav-link :href="route('today')" :active="request()->routeIs('today')">{{ __('Post today') }}@if ($toPost)<span class="ml-auto rounded-full bg-brand-500 px-2 py-0.5 text-xs font-semibold text-white" title="{{ __('Posts still to post today') }}">{{ $toPost }}</span>@endif</x-nav-link>
            <x-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.index', 'clients.create')">{{ __('Clients') }}</x-nav-link>
            <x-nav-link :href="route('jobs.index')" :active="request()->routeIs('jobs.*')">{{ __('Jobs') }}</x-nav-link>
            <x-nav-link :href="route('staff.index')" :active="request()->routeIs('staff.*')">{{ __('Staff') }}</x-nav-link>
            @can('admin')
                <x-nav-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')">{{ __('Settings') }}</x-nav-link>
                <x-nav-link :href="url('/refresh')">{{ __('Refresh after update') }}</x-nav-link>
            @endcan
        </div>

        @if ($clients->isNotEmpty())
            <p class="mb-1 mt-6 px-4 text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Clients') }}</p>
            <div class="space-y-0.5">
                @foreach ($clients as $one)
                    <x-nav-link :href="route('calendar', ['client' => $one->slug])" :active="isset($client) && $client?->is($one)" class="truncate">{{ $one->name }}</x-nav-link>
                @endforeach
            </div>
        @endif
    </div>

    <div :class="open ? 'block' : 'hidden'" class="hidden border-t border-white/10 px-5 py-3 text-sm md:block">
        <p class="truncate font-medium text-white">{{ Auth::user()->name }}</p>
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-neutral-300">
            <a href="{{ route('profile.edit') }}" class="hover:text-white">{{ __('Profile') }}</a>
            <x-locale-switch class="hover:text-white" />
            <button type="button" onclick="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'" class="hover:text-white" title="{{ __('Light or dark') }}" aria-label="{{ __('Light or dark') }}">&#9680;</button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="hover:text-white">{{ __('Log Out') }}</button>
            </form>
        </div>
    </div>
</nav>
