@props([
    'taskId',
    'expanded' => false,
])

<button
    type="button"
    {{ $attributes->class([
        'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200/90 bg-white text-gray-500 shadow-sm transition',
        'hover:border-gray-300 hover:bg-slate-50 hover:text-gray-800',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400/40',
    ]) }}
    wire:click.stop="toggle({{ (int) $taskId }})"
    aria-expanded="{{ $expanded ? 'true' : 'false' }}"
    title="{{ $expanded ? 'Collapse' : 'Expand' }}"
>
    <svg
        class="h-4 w-4 transition-transform duration-200 ease-out {{ $expanded ? 'rotate-90' : '' }}"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        aria-hidden="true"
    >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
</button>
