@props([
    'name'     => '',
    'email'    => '',
    'checked'  => false,
    'picture'  => null,
    'initials' => '',
])

<button type="button"
        {{ $attributes->merge(['class' => 'w-full flex items-center gap-3 cursor-pointer hover:bg-base-300/50 p-2 rounded']) }}>

    {{-- Checkbox box --}}
    <span class="inline-flex items-center justify-center h-4 w-4 rounded border border-gray-400 bg-white flex-shrink-0">
        @if($checked)
            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="0" y="0" width="20" height="20" rx="4" fill="#111827"/>
                <path d="M5 10.5L8.25 13.75L15 7" stroke="#FFFFFF" stroke-width="2"
                      stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        @endif
        {{ $slot }}
    </span>

    {{-- Avatar --}}
    <span class="inline-flex items-center justify-center h-7 w-7 rounded-full overflow-hidden border border-gray-200 bg-gray-200 flex-shrink-0 text-xs font-semibold text-gray-600">
        @if($picture)
            <img src="{{ $picture }}" alt="{{ $name }}"
                 class="h-full w-full object-cover"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';" />
            <span style="display:none;">{{ $initials ?: mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
        @else
            {{ $initials ?: mb_strtoupper(mb_substr($name, 0, 1)) }}
        @endif
    </span>

    <span class="font-medium text-sm flex-1 text-left">{{ $name }}</span>

    @if($email)
        <span class="text-xs text-gray-500">{{ $email }}</span>
    @endif
</button>