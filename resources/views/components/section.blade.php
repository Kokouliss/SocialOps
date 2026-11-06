{{-- A titled card: the title and one line on what it is for, then the rows (x-field) or any content. --}}
@props(['title' => null, 'description' => null, 'flush' => false])
<section {{ $attributes->class(['rounded-xl border border-gray-300 bg-white']) }}>
    @if ($title)
        <header class="border-b border-gray-300 px-6 py-4">
            <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
            @if ($description)<p class="mt-0.5 text-sm text-gray-600">{{ $description }}</p>@endif
        </header>
    @endif
    <div class="{{ $flush ? '' : 'px-6' }} divide-y divide-gray-200">{{ $slot }}</div>
</section>
