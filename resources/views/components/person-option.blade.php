@props([
    'name'    => '',
    'email'   => '',
    'checked' => false,
])

{{--
    Reusable person-row button used inside dropdown lists.

    Static checked state  → pass :checked="true/false"   (project member form)
    Alpine reactive state → leave checked=false and put an Alpine <template x-if> in the default slot
                            so the parent controls visibility dynamically  (task assignee dropdown)
--}}
<button type="button"
        {{ $attributes->merge(['class' => 'w-full flex items-center gap-3 cursor-pointer hover:bg-base-300/50 p-2 rounded']) }}>

    {{-- Checkbox box --}}
    <span class="inline-flex items-center justify-center h-4 w-4 rounded border border-gray-400 bg-white flex-shrink-0">
        @if($checked)
            {{-- Static checkmark (server-rendered) --}}
            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="0" y="0" width="20" height="20" rx="4" fill="#111827"/>
                <path d="M5 10.5L8.25 13.75L15 7" stroke="#FFFFFF" stroke-width="2"
                      stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        @endif
        {{-- Dynamic checkmark slot — use Alpine <template x-if> here when needed --}}
        {{ $slot }}
    </span>

    <span class="font-medium text-sm flex-1 text-left">{{ $name }}</span>

    @if($email)
        <span class="text-xs text-gray-500">{{ $email }}</span>
    @endif
</button>
