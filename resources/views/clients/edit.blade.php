<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }} <span class="font-mono text-base text-gray-500">{{ $client->code }}</span></h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('clients.update', $client) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @method('PATCH')
                @include('clients._form')
            </form>
        </div>
    </div>
</x-app-layout>
