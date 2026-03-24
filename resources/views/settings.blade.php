<x-app-layout>
    <x-slot name="header">
        <h2 class="font-normal text-xl text-gray-800 leading-tight">
            {{ __('Settings') }}
        </h2>
    </x-slot>

    <div class="w-full mx-auto sm:px-6 lg:px-8">
        @livewire('settings')
    </div>
</x-app-layout>
