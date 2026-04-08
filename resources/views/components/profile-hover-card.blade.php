@props([
    'name' => '',
    'email' => '',
    'specialization' => '',
    'role' => '',
    'avatarUrl' => null,
])

<div
    {{ $attributes->class('rounded-lg shadow-lg border border-gray-300 bg-base-100 px-4 py-3 text-sm w-64') }}>
    <div class="flex items-center gap-3 mb-3">
        <div class="avatar">
            <div class="w-10 h-10 rounded-full bg-neutral text-neutral-content overflow-hidden flex items-center justify-center">
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ $name }}" class="w-full h-full object-cover"
                        loading="lazy" referrerpolicy="no-referrer">
                @else
                    <span class="text-sm font-semibold">
                        {{ collect(explode(' ', $name))->map(fn($p) => mb_substr($p, 0, 1))->join('') ?: '?' }}
                    </span>
                @endif
            </div>
        </div>
        <div class="min-w-0">
            <p class="font-medium text-gray-900 truncate">{{ $name }}</p>
            @if ($email)
                <p class="text-xs text-gray-500 truncate">{{ $email }}</p>
            @endif
        </div>
    </div>

    <div class="space-y-1">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-gray-600">Specialization</span>
            @if ($specialization)
                <span
                    class="inline-flex items-center rounded-full border border-gray-300 px-2 py-0.5 text-[11px] text-gray-700 bg-gray-50">
                    {{ $specialization }}
                </span>
            @else
                <span class="text-[11px] text-gray-400">—</span>
            @endif
        </div>
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-gray-600">Role</span>
            @if ($role)
                <span
                    class="inline-flex items-center rounded-full border border-gray-300 px-2 py-0.5 text-[11px] text-gray-700 bg-gray-50">
                    {{ $role }}
                </span>
            @else
                <span class="text-[11px] text-gray-400">—</span>
            @endif
        </div>
    </div>
</div>

