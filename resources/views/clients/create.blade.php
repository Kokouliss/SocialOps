<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('New client') }}</h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('clients.store') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @include('clients._form')
            </form>
        </div>
    </div>
</x-app-layout>
