<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=ubuntu:400,500,600&display=swap" rel="stylesheet" />
    {{-- Datepicker for constrained due-date ranges --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    @php
        $useBuildOnNetwork =
            !app()->environment('production') && !in_array(request()->getHost(), ['localhost', '127.0.0.1'], true);
        $manifestPath = public_path('build/manifest.json');
    @endphp
    @if ($useBuildOnNetwork && file_exists($manifestPath))
        @php
            $manifest = json_decode(file_get_contents($manifestPath), true) ?? [];
        @endphp
        {{-- Root-relative URLs so CSS/JS load from the same host as the page (LAN IP), not APP_URL/localhost --}}
        @if (!empty($manifest['resources/css/app.css']['file']))
            <link rel="stylesheet" href="/build/{{ $manifest['resources/css/app.css']['file'] }}">
        @endif
        @if (!empty($manifest['resources/js/app.js']['file']))
            <script type="module" src="/build/{{ $manifest['resources/js/app.js']['file'] }}"></script>
        @endif
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>

<body class="font-sans antialiased">

    <div class="flex h-screen relative">

        <div class="group absolute left-0 top-0 h-full text-white flex flex-col transition-all duration-300 ease-in-out w-16 hover:w-64 overflow-hidden z-50"
            style="background-color: #102B3C;">

            <div class="flex items-center h-16 px-3 border-b border-white/20">
                <img src="/images/icon-white.png" alt="Icon" class="w-8 block group-hover:hidden" />
                <img src="/images/odecci-plain-logo.png" alt="Logo" class="w-36 hidden group-hover:block" />
            </div>

            <nav class="flex flex-col p-2 gap-1 mt-4 flex-1">

                <a href="/dashboard"
                    class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('dashboard') ? 'focus-clr-accent' : 'text-white' }}">
                    <x-icons.dashboard classes="w-6 h-6" />
                    <span class="hidden group-hover:block">Dashboard</span>
                </a>

                <a href="/calendar"
                    class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('calendar') ? 'focus-clr-accent' : '' }} hover-clr-accent">
                    <x-icons.calendar classes="w-6 h-6" />
                    <span class="hidden group-hover:block">Calendar</span>
                </a>

                <a href="/projects"
                    class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('projects') ? 'focus-clr-accent' : '' }} hover-clr-accent">
                    <x-icons.project classes="w-6 h-6" />
                    <span class="hidden group-hover:block">Projects</span>
                </a>

                @php
                    $navUser = \Illuminate\Support\Facades\Session::get('user', []);
                    $navRole = mb_strtolower(
                        trim(
                            (string) ($navUser['role'] ??
                                ($navUser['Role'] ?? ($navUser['roleName'] ?? ($navUser['RoleName'] ?? '')))),
                        ),
                    );
                @endphp
                @if ($navRole === 'admin')
                    <a href="/audit-logs"
                        class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('audit-logs') ? 'focus-clr-accent' : '' }} hover-clr-accent">
                        <x-icons.time-logs classes="w-6 h-6" />
                        <span class="hidden group-hover:block">Audit Logs</span>
                    </a>
                    <a href="/user-management"
                        class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('user-management') ? 'focus-clr-accent' : '' }} hover-clr-accent">
                        <x-icons.user-management classes="w-6 h-6" />
                        <span class="hidden group-hover:block">User Management</span>
                    </a>
                @endif
            </nav>

            <div class="px-2 pb-1">
                <a href="/settings"
                    class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('settings') ? 'focus-clr-accent' : '' }} hover-clr-accent">
                    <x-icons.settings classes="w-6 h-6" />
                    <span class="hidden group-hover:block">Settings</span>
                </a>
            </div>

            <div class="p-2 border-t border-white/20" x-data="{ open: false }">
                <button type="button" @click="open = true"
                    class="w-full flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap hover-clr-accent">
                    <x-icons.logout classes="w-6 h-6" />
                    <span class="hidden group-hover:block">Logout</span>
                </button>

                <form x-ref="logoutForm" method="POST" action="{{ route('logout') }}" class="hidden">
                    @csrf
                </form>

                <dialog :class="open ? 'modal modal-open' : 'modal'">
                    <div class="modal-box max-w-sm">
                        <h3 class="text-lg font-bold clr-primary">Confirm Logout</h3>
                        <p class="py-4 text-sm clr-primary">Are you sure you want to log out?</p>
                        <div class="modal-action">
                            <button type="button" class="btn btn-ghost clr-bg-primary text-base-100 p-2"
                                @click="open = false">Cancel</button>
                            <button type="button" class="btn clr-bg-primary text-base-100 p-2"
                                @click="$refs.logoutForm.submit()">Logout</button>
                        </div>
                    </div>
                    <div class="modal-backdrop" @click="open = false"></div>
                </dialog>
            </div>
        </div>

        <div class="flex-1 flex flex-col overflow-hidden bg-gray-100 ml-16">

            <div class="clr-bg-primary shadow px-6 py-4 h-16 flex items-center justify-between">
                <h1 class="text-xl font-semibold">{{ $header ?? '' }}</h1>
                <div class="flex items-center gap-3">
                    <div class="relative" x-data="notifDropdown()" x-init="init()">
                        {{-- Wrapper div owns the relative context so badge bleeds outside the button --}}
                        <div
                            style="position:relative; display:inline-flex; align-items:center; justify-content:center;">
                            <button type="button"
                                style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px;"
                                class="text-base-100 hover-clr-accent" @click="toggle()" aria-label="Notifications">
                                <x-icons.notification classes="w-6 h-6" />
                            </button>
                            <span x-show="unreadCount > 0" x-text="unreadCount > 99 ? '99+' : unreadCount"
                                style="position:absolute; top:-8px; right:-8px; min-width:18px; height:18px; font-size:10px; font-weight:700; line-height:18px; border-radius:9999px; background:#ef4444; color:#fff; display:inline-block; text-align:center; padding:0 4px; box-shadow:0 1px 3px rgba(0,0,0,0.3); pointer-events:none; z-index:10;"></span>
                        </div>

                        <div x-show="open" x-cloak x-transition @click.outside="open = false" style="display:none;"
                            :style="open ? '' : 'display:none;'"
                            class="absolute right-0 mt-2 w-96 max-w-[90vw] bg-white text-gray-900 rounded-lg shadow-xl border border-gray-200 overflow-hidden z-50">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                                <span class="text-sm font-semibold">Notifications</span>
                                <button type="button" class="text-xs text-blue-600 hover:underline disabled:opacity-50"
                                    @click="markAllRead()" :disabled="unreadCount === 0">
                                    Mark all read
                                </button>
                            </div>

                            <div class="max-h-[420px] overflow-y-auto">
                                <template x-if="loading">
                                    <div class="p-4 text-sm text-gray-500">Loading…</div>
                                </template>

                                <template x-if="!loading && items.length === 0">
                                    <div class="p-4 text-sm text-gray-500">No notifications.</div>
                                </template>

                                <template x-if="!loading && items.length > 0">
                                    <div class="px-4 py-2 border-b border-gray-100 bg-gray-50 flex items-center gap-3">
                                        <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 rounded border border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500"
                                                :checked="allSelected"
                                                @change="toggleSelectAll()"
                                            />
                                            <span>Select all</span>
                                        </label>
                                        <span class="text-[11px] text-gray-500" x-text="`${selectedCount} selected`"></span>
                                        <button type="button" class="text-xs text-blue-600 hover:underline disabled:opacity-50"
                                            @click="markSelectedRead()" :disabled="selectedIds.length === 0">
                                            Mark selected as read
                                        </button>
                                        <button type="button" class="text-xs text-red-600 hover:text-red-700 disabled:opacity-50"
                                            @click="deleteSelected()" :disabled="selectedIds.length === 0">
                                            Delete selected
                                        </button>
                                    </div>
                                </template>

                                <template x-for="n in items" :key="n._key || n.id">
                                    <div class="px-4 py-3 border-b border-gray-100 hover:bg-gray-50 cursor-pointer"
                                        :class="!n.isRead ? 'bg-sky-50' : ''" role="button" tabindex="0"
                                        @click="openNotification(n)" @keydown.enter.prevent="openNotification(n)">
                                        <div class="flex items-start gap-3">
                                            <div class="pt-0.5">
                                                <input
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500"
                                                    :checked="isSelected(n.id)"
                                                    @click.stop
                                                    @change.stop="toggleSelect(n.id)"
                                                />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm text-gray-900 whitespace-pre-wrap break-words"
                                                    :class="!n.isRead ? 'font-semibold' : 'font-normal'"
                                                    x-text="n.message || ''"></p>
                                                <p class="text-xs text-gray-500 mt-1" x-text="n.createdAtLabel"></p>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span
                                                    x-show="!n.isRead"
                                                    class="text-[10px] px-2 py-1 rounded-full bg-sky-100 text-sky-700 font-medium"
                                                >
                                                    Unread
                                                </span>
                                                <button type="button" class="text-xs text-red-600 hover:text-red-700"
                                                    @click.stop="remove(n)">
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    @php
                        $navUser = \Illuminate\Support\Facades\Session::get('user', []);
                        $pic = $navUser['profilePicture'] ?? ($navUser['ProfilePicture'] ?? null);
                        $avatarSrc = $pic;
                        $avatarHasImage = !empty($avatarSrc);
                        $fullName = (string) ($navUser['name'] ?? ($navUser['Name'] ?? ($navUser['fullName'] ?? '')));
                        $parts = preg_split('/\s+/', trim($fullName));
                        $parts = array_values(array_filter($parts, fn($p) => is_string($p) && trim($p) !== ''));
                        $first = (string) ($parts[0] ?? '');
                        $last = (string) (!empty($parts) ? implode(' ', array_slice($parts, 1)) : '');
                        $initials = '';
                        $a = mb_substr(trim($first), 0, 1);
                        $b = mb_substr(trim($last), 0, 1);
                        if ($a !== '' && $b !== '') {
                            $initials = mb_strtoupper($a . $b);
                        } elseif ($a !== '') {
                            $initials = mb_strtoupper($a);
                        } else {
                            $initials = '?';
                        }

                        $bgColors = ['#102B3C', '#205375', '#F0EFEF', '#ED1C24'];
                        $seed = (string) ($navUser['id'] ?? ($navUser['Id'] ?? $fullName));
                        $bg = $bgColors[(int) (abs(crc32($seed)) % count($bgColors))];
                        $initialsTextClass = $bg === '#F0EFEF' ? 'text-gray-800' : 'text-white';
                    @endphp
                    <a href="/settings" aria-label="Open settings">
                        <div id="header-avatar"
                            class="w-8 h-8 rounded-full overflow-hidden border border-gray-200 flex items-center justify-center"
                            style="background-color: {{ $avatarHasImage ? 'transparent' : $bg }};"
                            data-avatar-bg="{{ $bg }}"
                            data-avatar-initials-text-class="{{ $initialsTextClass }}">
                            {{-- Always render initials; hide it only when the image loads --}}
                            <span id="header-avatar-initials" class="font-semibold {{ $initialsTextClass }}"
                                style="{{ $avatarHasImage ? 'display:none;' : '' }}">
                                {{ $initials }}
                            </span>
                            <img id="header-avatar-img" src="{{ $avatarSrc ?? '' }}" alt="Profile"
                                class="h-full w-full object-cover"
                                onload="var wrap=this.closest('#header-avatar'); if(wrap){var span=wrap.querySelector('#header-avatar-initials'); if(span){span.style.display='none';}}"
                                onerror="var wrap=this.closest('#header-avatar'); if(wrap){this.style.display='none'; wrap.style.backgroundColor = wrap.dataset.avatarBg || 'transparent'; var span=wrap.querySelector('#header-avatar-initials'); if(span){span.style.display='flex';}}"
                                style="{{ empty($avatarSrc) ? 'display:none;' : '' }}" />
                        </div>
                    </a>
                </div>
            </div>

            <main class="flex-1 overflow-auto p-6">
                {{ $slot }}
            </main>

        </div>

    </div>

    {{-- Toast container --}}
    <div id="toast-container"
        class="fixed top-20 right-6 z-[10] flex flex-col gap-3 w-80 max-w-[90vw] pointer-events-none"></div>

    @livewireScripts

    <script>
        document.addEventListener('livewire:load', () => {
            if (typeof Livewire === 'undefined') return;
            Livewire.on('avatar-updated', (payload) => {
                const wrap = document.getElementById('header-avatar');
                const img = document.getElementById('header-avatar-img');
                const span = document.getElementById('header-avatar-initials');
                if (!wrap || !img || !span) return;

                const pic = payload?.profilePicture;
                const initialsTextClass = payload?.initialsTextClass || 'text-white';
                const avatarBg = payload?.avatarBg || wrap.dataset.avatarBg || '#102B3C';

                if (pic) {
                    wrap.style.backgroundColor = 'transparent';
                    img.src = pic;
                    img.style.display = '';
                    span.style.display = 'none';
                } else {
                    img.style.display = 'none';
                    span.style.display = 'flex';
                    span.className = 'font-semibold ' + initialsTextClass;

                    wrap.style.backgroundColor = avatarBg;
                }

                if (payload?.initials) span.textContent = payload.initials;
            });
        });
    </script>
</body>

</html>
