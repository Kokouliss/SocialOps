<x-app-layout :client="$client">
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Calendar') }}</h2>
            <form method="GET" action="{{ route('calendar') }}" class="flex flex-wrap items-center gap-2 text-sm">
                <input type="hidden" name="view" value="{{ $view }}">
                <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.from.value = this.form.to.value = ''; this.form.submit()" title="{{ __('Go to a date') }}" class="border-gray-300 rounded-md shadow-sm text-sm">
                <label class="text-gray-500">{{ __('From') }} <input type="date" name="from" value="{{ $range ? $range[0]->toDateString() : '' }}" onchange="this.form.to.value && this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm"></label>
                <label class="text-gray-500">{{ __('To') }} <input type="date" name="to" value="{{ $range ? $range[1]->toDateString() : '' }}" onchange="this.form.from.value && this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm"></label>
                <select name="client" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="">{{ __('All clients') }}</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->slug }}" @selected($clientSlug === $client->slug)>{{ $client->name }}</option>
                    @endforeach
                </select>
                <select name="state" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="">{{ __('Any state') }}</option>
                    @foreach (\App\Posts\PostState::cases() as $state)
                        <option value="{{ $state->value }}" @selected(($filters['state'] ?? '') === $state->value)>{{ __($state->label()) }}</option>
                    @endforeach
                </select>
                <select name="platform" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="">{{ __('Any platform') }}</option>
                    @foreach (\App\Models\Post::PLATFORMS as $platform)
                        <option value="{{ $platform }}" @selected(($filters['platform'] ?? '') === $platform)>{{ str_replace('_', ' ', ucfirst($platform)) }}</option>
                    @endforeach
                </select>
                <select name="pillar" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="">{{ __('Any pillar') }}</option>
                    @foreach ($pillars as $pillar)
                        <option value="{{ $pillar }}" @selected(($filters['pillar'] ?? '') === $pillar)>{{ str_replace('_', ' ', $pillar) }}</option>
                    @endforeach
                </select>
            </form>
            @php($step = $view === 'week' ? '1 week' : '1 month')
            @php($len = $range ? (int) $range[0]->diffInDays($range[1]) + 1 : 0)
            {{-- the dates every link keeps: the from–to range, or the date shown; the arrows move by the range's own length --}}
            @php($when = $range ? ['from' => $range[0]->toDateString(), 'to' => $range[1]->toDateString()] : ['date' => $date->toDateString()])
            @php($span = [...$filters, 'client' => $clientSlug, ...$when])
            @php($move = fn (int $dir) => $range ? ['from' => $range[0]->copy()->addDays($dir * $len)->toDateString(), 'to' => $range[1]->copy()->addDays($dir * $len)->toDateString()] : ['date' => ($dir > 0 ? $date->copy()->add($step) : $date->copy()->sub($step))->toDateString()])
            <a class="px-2 py-1 border rounded" href="{{ route('calendar', [...$span, 'view' => $view, ...$move(-1)]) }}">&larr;</a>
            <span class="font-medium">{{ $range ? $range[0]->format('d-m-Y').' – '.$range[1]->format('d-m-Y') : ($view === 'week' ? $date->copy()->startOfWeek()->format('d-m-Y').' – '.$date->copy()->endOfWeek()->format('d-m-Y') : $date->translatedFormat('F Y')) }}</span>
            <a class="px-2 py-1 border rounded" href="{{ route('calendar', [...$span, 'view' => $view, ...$move(1)]) }}">&rarr;</a>
            <a class="px-2 py-1 border rounded {{ $view === 'month' ? 'bg-gray-800 text-white' : '' }}" href="{{ route('calendar', [...$span, 'view' => 'month']) }}">{{ __('Month') }}</a>
            <a class="px-2 py-1 border rounded {{ $view === 'week' ? 'bg-gray-800 text-white' : '' }}" href="{{ route('calendar', [...$span, 'view' => 'week']) }}">{{ __('Week') }}</a>
            <a class="px-2 py-1 border rounded {{ $view === 'list' ? 'bg-gray-800 text-white' : '' }}" href="{{ route('calendar', [...$span, 'view' => 'list']) }}">{{ __('List') }}</a>
            @if ($clientSlug)
                <a class="px-2 py-1 border rounded" href="{{ route('posts.csv', [$clients->firstWhere('slug', $clientSlug), 'view' => $view, ...$when]) }}" title="{{ __('The posts shown, one row per platform, for Canva Bulk Create or a spreadsheet') }}">{{ __('Export CSV') }}</a>
            @else
                <button type="button" data-new-post="" class="ml-auto inline-flex items-center px-4 py-2 bg-brand-500 rounded-full font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-600">{{ __('New post') }}</button>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <p class="text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif
            @error('state') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @if ($clientUrl)
                <div class="bg-white p-4 shadow-sm sm:rounded-lg">
                    <x-input-label for="client-url" :value="__('View-only link for the client (no login, valid :days days, this month and the next)', ['days' => \App\Http\Controllers\CalendarController::LINK_DAYS])" />
                    <x-text-input id="client-url" type="text" class="mt-1 block w-full font-mono text-xs" :value="$clientUrl" readonly onclick="this.select()" />
                </div>
            @endif

            @if ($view !== 'list')
            @foreach ($grids as $grid)
            @if ($grid['title'])<h3 class="hidden sm:block font-semibold text-gray-800 {{ $loop->first ? '' : 'pt-4' }}">{{ $grid['title'] }}</h3>@endif
            <div class="hidden sm:grid grid-cols-7 gap-px bg-gray-200 border border-gray-200 text-xs">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                    <div class="bg-gray-50 px-2 py-1 font-medium text-gray-600">{{ __($dow) }}</div>
                @endforeach
                @foreach ($grid['days'] as $day)
                    @php($key = $day->toDateString())
                    <div class="day bg-white min-h-24 p-1 cursor-pointer {{ ($grid['month'] ? $day->month !== $grid['month'] || $day->lt($range[0]) || $day->gt($range[1]) : $day->month !== $date->month && $view === 'month') ? 'text-gray-400' : '' }}" data-date="{{ $key }}">
                        <div class="flex justify-between">
                            <span class="{{ $day->isToday() ? 'font-bold text-brand-600' : '' }}">{{ $day->day }}</span>
                            @if ($clientSlug)
                                <a href="{{ route('posts.create', [$clients->firstWhere('slug', $clientSlug), 'date' => $key]) }}" class="text-gray-400 hover:text-gray-700" title="{{ __('New post') }}">+</a>
                            @else
                                <button type="button" data-new-post="{{ $key }}" class="text-gray-400 hover:text-gray-700" title="{{ __('New post') }}">+</button>
                            @endif
                        </div>
                        @foreach ($marks[$key] ?? [] as $mark)
                            <div class="truncate text-amber-700" title="{{ $mark }}">{{ $mark }}</div>
                        @endforeach
                        @foreach ($posts->get($key, []) as $post)
                            @include('calendar._chip', ['post' => $post])
                        @endforeach
                    </div>
                @endforeach
            </div>
            @endforeach

            <div class="day hidden sm:block bg-white border border-dashed border-gray-300 p-3 text-xs" data-date="">
                <p class="font-medium text-gray-600 mb-1">{{ __('No date yet (drag onto a day to schedule)') }}</p>
                @forelse ($unscheduled as $post)
                    @include('calendar._chip', ['post' => $post])
                @empty
                    <span class="text-gray-400">{{ __('None') }}</span>
                @endforelse
            </div>
            @endif
            @include('calendar._list', ['class' => $view === 'list' ? '' : 'sm:hidden', 'days' => $range ? collect($range[0]->toPeriod($range[1], '1 day')) : $days])

            <p class="flex flex-wrap items-center gap-1.5 bg-white p-3 shadow-sm sm:rounded-lg text-xs text-gray-500" title="{{ __('Click a state to show only those posts; click it again to show all.') }}">
                {{ __('States') }}:
                @foreach (\App\Posts\PostState::cases() as $state)
                    @php($on = ($filters['state'] ?? '') === $state->value)
                    <a href="{{ route('calendar', [...$filters, 'state' => $on ? null : $state->value, 'view' => $view, 'client' => $clientSlug, ...$when]) }}"><x-state-badge :state="$state" :class="$on ? 'ring-2 ring-gray-800' : ''" /></a>
                @endforeach
            </p>
        </div>
    </div>

    @unless ($clientSlug)
        <dialog id="pick-client" class="rounded-lg p-4 text-sm shadow-xl backdrop:bg-black/30">
            <p class="mb-2 font-medium">{{ __('New post for which client?') }}</p>
            <div class="flex flex-col gap-1">
                @foreach ($clients as $other)
                    <a href="{{ route('posts.create', $other) }}" data-href="{{ route('posts.create', $other) }}" class="rounded px-3 py-1.5 hover:bg-gray-100">{{ $other->name }}</a>
                @endforeach
            </div>
            <form method="dialog" class="mt-3 text-right"><button class="text-gray-500">{{ __('Cancel') }}</button></form>
        </dialog>
    @endunless

    <script>
        // Click an empty spot on a day (or "+", or "New post") to start a post on that date; with no client selected, pick one first.
        const create = @json($clientSlug ? route('posts.create', $clients->firstWhere('slug', $clientSlug)) : null), pick = document.getElementById('pick-client');
        const newPost = date => {
            if (create) { location.href = create + (date ? '?date=' + date : ''); return; }
            pick.querySelectorAll('a').forEach(a => a.href = a.dataset.href + (date ? '?date=' + date : ''));
            pick.showModal();
        };
        document.querySelectorAll('[data-new-post]').forEach(b => b.addEventListener('click', () => newPost(b.dataset.newPost)));
        document.querySelectorAll('.day[data-date]').forEach(day => day.addEventListener('click', e => {
            if (day.dataset.date && !e.target.closest('a, button')) newPost(day.dataset.date);
        }));

        // Drag a post chip onto a day: PATCH /posts/{id}/reschedule, then reload.
        document.querySelectorAll('.chip').forEach(chip => {
            chip.addEventListener('dragstart', e => e.dataTransfer.setData('text/plain', chip.dataset.id));
        });
        document.querySelectorAll('.day[data-date]').forEach(day => {
            if (!day.dataset.date) return;
            day.addEventListener('dragover', e => { e.preventDefault(); day.classList.add('bg-brand-50'); });
            day.addEventListener('dragleave', () => day.classList.remove('bg-brand-50'));
            day.addEventListener('drop', async e => {
                e.preventDefault();
                const id = e.dataTransfer.getData('text/plain');
                const body = new URLSearchParams({ _method: 'PATCH', _token: '{{ csrf_token() }}', date: day.dataset.date });
                const res = await fetch(`/posts/${id}/reschedule`, { method: 'POST', body, headers: { 'Accept': 'text/html' } });
                if (res.ok) location.reload(); else alert('{{ __('Could not move this post.') }}');
            });
        });
    </script>
</x-app-layout>
