<x-app-layout>
    <x-slot name="header">
        <h2 class="font-normal text-xl text-gray-800 leading-tight">
            {{ __('Projects') }}
        </h2>
    </x-slot>

    <div class="w-full mx-auto sm:px-6 lg:px-8">
        @livewire('projects', [
            'projects' => $projects ?? [],
            'accounts' => $accounts ?? [],
            'creatorId' => $creatorId ?? 0,
            'selectedMemberIds' => old('memberIds', []),
            'showAddModal' => request()->boolean('create') || ($errors->any() && !old('_edit_project_id')),
            'showEditModal' => $errors->any() && (bool) old('_edit_project_id'),
            'editingProjectId' => old('_edit_project_id'),
        ])
    </div>
</x-app-layout>
