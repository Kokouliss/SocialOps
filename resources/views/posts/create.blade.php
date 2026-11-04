<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $client->name }}: {{ __('new post') }}</h2>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 lg:grid lg:grid-cols-3 lg:gap-6 lg:items-start">
            <form id="post-form" method="POST" action="{{ route('posts.store', $client) }}" class="bg-white p-6 shadow-sm sm:rounded-lg lg:col-span-2">
                @include('posts._form')
            </form>
            <div class="mt-4 lg:mt-0 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto">
                @include('posts._preview')
            </div>
        </div>
    </div>
</x-app-layout>
