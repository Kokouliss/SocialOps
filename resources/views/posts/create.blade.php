<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }}: {{ __('new post') }}</h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('posts.store', $client) }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @include('posts._form')
            </form>
        </div>
    </div>
</x-app-layout>
