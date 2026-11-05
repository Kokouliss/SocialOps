@props(['active'])

<a {{ $attributes->merge(['class' => ($active ?? false) ? 'flex items-center rounded-md bg-white/10 px-3 py-2 text-sm font-medium text-white' : 'flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-400 hover:bg-white/5 hover:text-white']) }}>
    {{ $slot }}
</a>
