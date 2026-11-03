<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Calendar') }}</h2>
            <form method="GET" action="{{ route('calendar') }}" class="flex flex-wrap items-center gap-2 text-sm">
                <input type="hidden" name="view" value="{{ $view }}">
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <select name="client" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="">{{ __('All clients') }}</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->slug }}" @selected($clientSlug === $client->slug)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </form>
            @php($step = $view === 'week' ? '1 week' : '1 month')
            <a class="px-2 py-1 border rounded" href="{{ route('calendar', ['view' => $view, 'client' => $clientSlug, 'date' => $date->copy()->sub($step)->toDateString()]) }}">&larr;</a>
            <span class="font-medium">{{ $view === 'week' ? $date->copy()->startOfWeek()->format('j M').' – '.$date->copy()->endOfWeek()->format('j M Y') : $date->format('F Y') }}</span>
            <a class="px-2 py-1 border rounded" href="{{ route('calendar', ['view' => $view, 'client' => $clientSlug, 'date' => $date->copy()->add($step)->toDateString()]) }}">&rarr;</a>
            <a class="px-2 py-1 border rounded {{ $view === 'month' ? 'bg-gray-800 text-white' : '' }}" href="{{ route('calendar', ['view' => 'month', 'client' => $clientSlug, 'date' => $date->toDateString()]) }}">{{ __('Month') }}</a>
            <a class="px-2 py-1 border rounded {{ $view === 'week' ? 'bg-gray-800 text-white' : '' }}" href="{{ route('calendar', ['view' => 'week', 'client' => $clientSlug, 'date' => $date->toDateString()]) }}">{{ __('Week') }}</a>
            @if ($clientSlug)
                <a class="px-2 py-1 border rounded" href="{{ route('posts.csv', [$clients->firstWhere('slug', $clientSlug), 'view' => $view, 'date' => $date->toDateString()]) }}" title="{{ __('The posts shown, one row per platform, for Canva Bulk Create or a spreadsheet') }}">{{ __('Export CSV') }}</a>
                <a class="ml-auto inline-flex items-center px-4 py-2 bg-brand-500 rounded-full font-semibold text-xs text-white uppercase tracking-widest hover:bg-brand-600" href="{{ route('posts.create', $clients->firstWhere('slug', $clientSlug)) }}">{{ __('New post') }}</a>
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

            <div class="grid grid-cols-7 gap-px bg-gray-200 border border-gray-200 text-xs">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                    <div class="bg-gray-50 px-2 py-1 font-medium text-gray-600">{{ __($dow) }}</div>
                @endforeach
                @foreach ($days as $day)
                    @php($key = $day->toDateString())
                    <div class="day bg-white min-h-24 p-1 {{ $day->month !== $date->month && $view === 'month' ? 'text-gray-400' : '' }}" data-date="{{ $key }}">
                        <div class="flex justify-between">
                            <span class="{{ $day->isToday() ? 'font-bold text-brand-600' : '' }}">{{ $day->day }}</span>
                            @if ($clientSlug)
                                <a href="{{ route('posts.create', [$clients->firstWhere('slug', $clientSlug), 'date' => $key]) }}" class="text-gray-400 hover:text-gray-700" title="{{ __('New post') }}">+</a>
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

            <div class="day bg-white border border-dashed border-gray-300 p-3 text-xs" data-date="">
                <p class="font-medium text-gray-600 mb-1">{{ __('No date yet (drag onto a day to schedule)') }}</p>
                @forelse ($unscheduled as $post)
                    @include('calendar._chip', ['post' => $post])
                @empty
                    <span class="text-gray-400">{{ __('None') }}</span>
                @endforelse
            </div>
        </div>
    </div>

    <script>
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
