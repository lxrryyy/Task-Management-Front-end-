<x-app-layout>
    <x-slot name="header">
        <h2 class="font-normal text-xl text-gray-800 leading-tight">
            {{ __('Tasks') }}
        </h2>
    </x-slot>

    <div class="w-full mx-auto sm:px-6 lg:px-8">
        @livewire('tasks', [
            'projectId'        => $projectId ?? null,
            'tasks'            => $tasks ?? [],
            'accounts'         => $accounts ?? [],
            'showAddTaskModal' => ($errors->any() && old('name') !== null) || (session('task_warnings') && count(session('task_warnings')) > 0),
            'taskParentId'     => old('parentTaskId') ? (int) old('parentTaskId') : null,
        ])
    </div>
</x-app-layout>
