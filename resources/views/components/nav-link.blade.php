@props(['active'])

{{-- The side navigation is dark in both themes, so its greys come from the neutral scale, which the dark-mode remap in app.css leaves alone. --}}
<a {{ $attributes->merge(['class' => ($active ?? false) ? 'flex items-center rounded-md border-l-4 border-brand-500 bg-white/15 px-3 py-2 text-[15px] font-semibold text-white' : 'flex items-center rounded-md border-l-4 border-transparent px-3 py-2 text-[15px] font-medium text-neutral-200 hover:bg-white/10 hover:text-white']) }}>
    {{ $slot }}
</a>
