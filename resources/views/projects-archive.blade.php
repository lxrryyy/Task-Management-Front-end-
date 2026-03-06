<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Archived Projects') }}
        </h2>
    </x-slot>

    <div class="w-full mx-auto sm:px-6 lg:px-8">
        @livewire('archive', [
            'projects' => $projects ?? [],
            'accounts' => $accounts ?? [],
            'creatorId' => $creatorId ?? 0,
        ])
    </div>
</x-app-layout>

