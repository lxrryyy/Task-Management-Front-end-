@props([
    'profiles' => [],
    'visible' => 3,
    'sizeClass' => 'w-6 h-6',
    // -space-x-6 fully overlaps w-6 avatars (looks like only 1 assignee).
    'overlapClass' => '-space-x-3',
    'dataPrefix' => 'avatar',
    'showHover' => true,
])

@php
    $profiles = is_array($profiles ?? null) ? $profiles : [];
    $count = count($profiles);
    $visible = (int) ($visible ?? 3);
    if ($visible < 1) {
        $visible = 1;
    }
    $visibleProfiles = array_slice($profiles, 0, $visible);
    $overflowCount = max(0, $count - $visible);
@endphp

@if ($count > 0)
    <div class="avatar-group {{ $overlapClass }} overflow-visible">
        @foreach ($visibleProfiles as $p)
            <div class="relative" @if($showHover) x-data="{ open:false }" @mouseenter="open=true" @mouseleave="open=false" @endif>
                <div class="avatar" data-{{ $dataPrefix }}-avatar>
                    <div
                        class="bg-neutral text-neutral-content {{ $sizeClass }} rounded-full flex items-center justify-center relative overflow-hidden">
                        <span data-{{ $dataPrefix }}-initials
                            class="text-xs font-semibold leading-none {{ !empty($p['profilePicture']) ? 'hidden' : '' }}">
                            {{ $p['initials'] ?? '?' }}
                        </span>

                        @if (!empty($p['profilePicture']))
                            <img src="{{ $p['profilePicture'] }}" alt=""
                                class="absolute inset-0 w-full h-full rounded-full object-cover" loading="lazy"
                                referrerpolicy="no-referrer"
                                onerror="this.style.display='none'; var wrap=this.closest('[data-{{ $dataPrefix }}-avatar]'); if(wrap){var sp=wrap.querySelector('[data-{{ $dataPrefix }}-initials]'); if(sp){sp.classList.remove('hidden');}}" />
                        @endif
                    </div>
                </div>

                @if ($showHover)
                    <div x-show="open" x-transition class="absolute left-1/2 top-full mt-2 -translate-x-1/2 z-[9999]">
                        <x-profile-hover-card
                            :name="$p['name'] ?? ''"
                            :email="$p['email'] ?? ''"
                            :specialization="$p['specialization'] ?? ''"
                            :role="$p['role'] ?? ''"
                            :avatar-url="$p['profilePicture'] ?? null"
                        />
                    </div>
                @endif
            </div>
        @endforeach

        @if ($overflowCount > 0)
            <div class="avatar avatar-placeholder">
                <div class="bg-neutral text-neutral-content {{ $sizeClass }} rounded-full flex items-center justify-center">
                    <span class="text-xs font-semibold leading-none">+{{ $overflowCount }}</span>
                </div>
            </div>
        @endif
    </div>
@endif

