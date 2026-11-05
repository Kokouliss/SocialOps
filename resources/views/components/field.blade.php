{{-- One row of a form: the label and its help on the left, the control(s) on the right. --}}
@props(['label', 'for' => null, 'help' => null])
<div {{ $attributes->class(['grid gap-2 py-5 md:grid-cols-3 md:gap-8']) }}>
    <div>
        @if ($for)
            <label for="{{ $for }}" class="block text-sm font-medium text-gray-900">{{ $label }}</label>
        @else
            <p class="text-sm font-medium text-gray-900">{{ $label }}</p>
        @endif
        @if ($help)<p class="mt-1 text-xs leading-5 text-gray-500">{{ $help }}</p>@endif
    </div>
    <div class="min-w-0 md:col-span-2">{{ $slot }}</div>
</div>
