<x-app-layout :client="$client">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }}: {{ __('deadlines') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <p class="text-sm text-green-600">{{ __(ucfirst(str_replace('-', ' ', session('status')))) }}.</p>
            @endif

            <form method="POST" action="{{ route('deadlines.store', $client) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                <h3 class="font-semibold text-gray-900">{{ __('Add a deadline') }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ __('What the client owes us (products, footage, answers) and what we owe the client (the topic plan, the edited videos), by when. Tick it when it is done.') }}</p>
                <div class="mt-3 grid gap-3 md:grid-cols-6">
                    <div class="md:col-span-3">
                        <x-input-label for="title" :value="__('What')" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" placeholder="{{ __('December topic plan') }}" required />
                        <x-input-error class="mt-1" :messages="$errors->get('title')" />
                    </div>
                    <div>
                        <x-input-label for="side" :value="__('Who')" />
                        <select id="side" name="side" class="mt-1 block w-full border-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm text-sm">
                            @foreach (\App\Models\Deadline::SIDES as $value => $label)<option value="{{ $value }}" @selected(old('side', 'agency') === $value)>{{ __($label) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="due_on" :value="__('Due')" />
                        <x-text-input id="due_on" name="due_on" type="date" class="mt-1 block w-full" :value="old('due_on')" />
                    </div>
                    <div class="flex items-end"><x-primary-button>{{ __('Add') }}</x-primary-button></div>
                </div>
            </form>

            @forelse ($months as $month => $deadlines)
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="font-semibold text-gray-900">{{ $month ? \Carbon\Carbon::parse($month.'-01')->translatedFormat('F Y') : __('No date') }}</h3>
                    <ul class="mt-2 divide-y divide-gray-100 text-sm">
                        @foreach ($deadlines as $deadline)
                            <li class="flex flex-wrap items-center gap-3 py-2">
                                <form method="POST" action="{{ route('deadlines.toggle', $deadline) }}">@csrf @method('PATCH')<button type="submit" class="rounded border border-gray-300 px-2 py-0.5 text-xs hover:bg-gray-50" title="{{ $deadline->done_at ? __('Reopen') : __('Mark done') }}">{{ $deadline->done_at ? '✓' : '○' }}</button></form>
                                <span class="{{ $deadline->done_at ? 'text-gray-400 line-through' : 'text-gray-900' }}">{{ $deadline->title }}</span>
                                <span class="rounded px-2 py-0.5 text-xs {{ $deadline->side === 'client' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800' }}">{{ __(\App\Models\Deadline::SIDES[$deadline->side]) }}</span>
                                @if ($deadline->due_on)<span class="text-xs {{ ! $deadline->done_at && $deadline->due_on->lt(today()) ? 'text-red-600 font-medium' : 'text-gray-500' }}">{{ $deadline->due_on->translatedFormat('D d-m-Y') }}</span>@endif
                                @if ($deadline->done_at)<span class="text-xs text-gray-400">{{ __('done') }} {{ $deadline->done_at->format('d-m-Y') }}</span>@endif
                                <form method="POST" action="{{ route('deadlines.destroy', $deadline) }}" class="ml-auto" onsubmit="return confirm('{{ __('Delete this deadline?') }}')">@csrf @method('DELETE')<button type="submit" class="text-xs text-red-600 hover:underline">{{ __('Delete') }}</button></form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <x-empty-state class="bg-white">{{ __('No deadlines yet. Add what the client owes and what we owe for the month.') }}</x-empty-state>
            @endforelse
        </div>
    </div>
</x-app-layout>
