@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-brand-500 text-start text-base font-medium text-white bg-gray-800 focus:outline-none focus:text-white focus:bg-gray-700 focus:border-brand-300 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700 hover:border-gray-500 focus:outline-none focus:text-white focus:bg-gray-700 focus:border-gray-500 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
