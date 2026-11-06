{{-- One look for a post state everywhere: colour from PostState::color(), the label in staff words unless the page passes the client's. --}}
@props(['state', 'label' => null])
<span {{ $attributes->class(['inline-block whitespace-nowrap rounded px-2 py-0.5 text-xs font-semibold', $state->color()]) }}>{{ __($label ?? $state->label()) }}</span>
