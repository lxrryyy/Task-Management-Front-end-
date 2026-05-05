@props([
    'name'            => '',
    'email'           => '',
    'checked'         => false,
    'picture'         => null,
    'initials'        => '',
    'bio'             => '',
    'specialization'  => '',
])

@php
    $b = trim((string) $bio);
    $s = trim((string) $specialization);
    if ($b !== '' && $s !== '' && $b !== $s) {
        $pillText = $b.' · '.$s;
    } elseif ($b !== '') {
        $pillText = $b;
    } elseif ($s !== '') {
        $pillText = $s;
    } else {
        $pillText = '';
    }
    $pillCase =
        $pillText !== '' && (str_contains($pillText, ' · ') || mb_strlen($pillText) > 24)
            ? 'normal-case'
            : 'uppercase tracking-wide';
@endphp

<button type="button"
        {{ $attributes->merge(['class' => 'w-full flex items-start gap-3 cursor-pointer hover:bg-base-300/50 p-2 rounded']) }}>

    {{-- Checkbox box --}}
    <span class="inline-flex items-center justify-center h-4 w-4 rounded border border-gray-400 bg-white flex-shrink-0 mt-1">
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
    <span class="inline-flex items-center justify-center h-7 w-7 rounded-full overflow-hidden border border-gray-200 bg-gray-200 flex-shrink-0 text-xs font-semibold text-gray-600 mt-0.5">
        @if($picture)
            <img src="{{ $picture }}" alt="{{ $name }}"
                 class="h-full w-full object-cover"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';" />
            <span style="display:none;">{{ $initials ?: mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
        @else
            {{ $initials ?: mb_strtoupper(mb_substr($name, 0, 1)) }}
        @endif
    </span>

    <div class="flex-1 min-w-0 text-left">
        <div class="font-medium text-sm text-gray-900 truncate leading-tight">{{ $name }}</div>
        <div class="mt-1 flex items-center min-w-0">
            @if ($pillText !== '')
                <span
                    class="inline-flex max-w-full items-center truncate px-2 py-1 text-[11px] font-medium text-slate-800"
                    title="{{ $pillText }}">{{ $pillText }}</span>
            @else
                <span
                    class="inline-flex items-center px-2 py-1  text-[11px] font-medium uppercase tracking-wide text-gray-400">Not set</span>
            @endif
        </div>
    </div>

    @if ($email)
        <span class="text-xs text-gray-500 shrink-0 text-right self-start pt-0.5 max-w-[40%] break-all">{{ $email }}</span>
    @endif
</button>