{{-- An empty list says what would be here and offers the next step: a link from action + href, or any markup in the action slot. --}}
@props(['action' => null, 'href' => null])
<div {{ $attributes->class(['rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-600']) }} data-empty-state>
    <p>{{ $slot }}</p>
    @if ($href)
        <a href="{{ $href }}" class="mt-3 inline-flex items-center rounded-full bg-brand-500 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-600">{{ $action }}</a>
    @elseif (isset($action) && ! is_string($action))
        <div class="mt-3">{{ $action }}</div>
    @endif
</div>
