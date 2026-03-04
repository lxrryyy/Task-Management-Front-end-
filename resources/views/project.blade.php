<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Project') }}
        </h2>
    </x-slot>

    <div class="w-full mx-auto sm:px-6 lg:px-8">
        <div class="bg-white p-6 rounded-lg shadow">
            <div class="text-lg font-semibold mb-2">
                {{ $project['name'] ?? $project['projectName'] ?? 'Project' }}
            </div>
            <div class="text-sm text-gray-600 mb-4">
                {{ $project['description'] ?? '' }}
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <dt class="text-xs uppercase text-gray-500">Status</dt>
                    <dd class="text-sm">{{ $project['status'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-500">Created at</dt>
                    <dd class="text-sm">
                        @php $createdAt = $project['createdAt'] ?? null; @endphp
                        {{ $createdAt ? \Carbon\Carbon::parse($createdAt)->format('m/d/Y') : '—' }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</x-app-layout>

