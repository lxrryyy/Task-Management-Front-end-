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
        $useBuildOnNetwork = !app()->environment('production') && !in_array(request()->getHost(), ['localhost', '127.0.0.1'], true);
        $manifestPath = public_path('build/manifest.json');
    @endphp
    @if($useBuildOnNetwork && file_exists($manifestPath))
        @php
            $manifest = json_decode(file_get_contents($manifestPath), true) ?? [];
        @endphp
        @if(!empty($manifest['resources/css/app.css']['file']))
            <link rel="stylesheet" href="{{ asset('build/'.$manifest['resources/css/app.css']['file']) }}">
        @endif
        @if(!empty($manifest['resources/js/app.js']['file']))
            <script type="module" src="{{ asset('build/'.$manifest['resources/js/app.js']['file']) }}"></script>
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
            <img src="{{ asset('images/icon-white.png') }}" alt="Icon"
                 class="w-8 block group-hover:hidden" />
            <img src="{{ asset('images/odecci-plain-logo.png') }}" alt="Logo"
                 class="w-36 hidden group-hover:block" />
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
                $navRole = mb_strtolower(trim((string) ($navUser['role'] ?? $navUser['Role'] ?? $navUser['roleName'] ?? $navUser['RoleName'] ?? '')));
            @endphp
            @if($navRole === 'admin')
                <a href="/audit-logs"
                   class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('audit-logs') ? 'focus-clr-accent' : '' }} hover-clr-accent">
                    <x-icons.time-logs classes="w-6 h-6" />
                    <span class="hidden group-hover:block">Audit Logs</span>
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
                        <button type="button" class="btn btn-ghost clr-bg-primary text-base-100 p-2" @click="open = false">Cancel</button>
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
                    <div style="position:relative; display:inline-flex; align-items:center; justify-content:center;">
                        <button type="button"
                                style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px;"
                                class="text-base-100 hover-clr-accent"
                                @click="toggle()"
                                aria-label="Notifications">
                            <x-icons.notification classes="w-6 h-6" />
                        </button>
                        <span x-show="unreadCount > 0"
                              x-text="unreadCount > 99 ? '99+' : unreadCount"
                              style="position:absolute; top:-8px; right:-8px; min-width:18px; height:18px; font-size:10px; font-weight:700; line-height:18px; border-radius:9999px; background:#ef4444; color:#fff; display:inline-block; text-align:center; padding:0 4px; box-shadow:0 1px 3px rgba(0,0,0,0.3); pointer-events:none; z-index:10;"></span>
                    </div>

                    <div x-show="open"
                         x-transition
                         @click.outside="open = false"
                         class="absolute right-0 mt-2 w-96 max-w-[90vw] bg-white text-gray-900 rounded-lg shadow-xl border border-gray-200 overflow-hidden z-50">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                            <span class="text-sm font-semibold">Notifications</span>
                            <button type="button"
                                    class="text-xs text-blue-600 hover:underline disabled:opacity-50"
                                    @click="markAllRead()"
                                    :disabled="unreadCount === 0">
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

                            <template x-for="n in items" :key="n.id">
                                <div class="px-4 py-3 border-b border-gray-100 hover:bg-gray-50"
                                     :class="!n.isRead ? 'bg-gray-100' : ''">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-gray-900 whitespace-pre-wrap break-words" x-text="n.message || ''"></p>
                                            <p class="text-xs text-gray-500 mt-1" x-text="n.createdAtLabel"></p>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <button type="button"
                                                    class="text-xs text-gray-600 hover:text-gray-900 disabled:opacity-50"
                                                    @click="markRead(n)"
                                                    :disabled="n.isRead">
                                                Read
                                            </button>
                                            <button type="button"
                                                    class="text-xs text-red-600 hover:text-red-700"
                                                    @click="remove(n)">
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center">👤</div>
            </div>
        </div>

        <main class="flex-1 overflow-auto p-6">
            {{ $slot }}
        </main>

    </div>

</div>

{{-- Toast container --}}
<div id="toast-container" class="fixed bottom-5 right-5 z-[9999] flex flex-col gap-2 pointer-events-none"></div>

@livewireScripts
<script>
    function notifDropdown() {
        const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';

        const norm = (n) => {
            const id = parseInt(n.id ?? n.Id ?? 0) || 0;
            const msg = (n.message ?? n.Message ?? '').toString();
            const isRead = !!(n.isRead ?? n.IsRead ?? false);
            const createdAtRaw = (n.createdAt ?? n.CreatedAt ?? '').toString();
            let createdAtLabel = createdAtRaw;
            if (createdAtRaw) {
                const d = new Date(createdAtRaw);
                if (!isNaN(d.getTime())) {
                    createdAtLabel = d.toLocaleString(undefined, { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
                }
            }
            return { id, message: msg, isRead, createdAt: createdAtRaw, createdAtLabel };
        };

        const showToast = (message) => {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = [
                'pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg',
                'text-white text-sm max-w-xs w-full',
                'translate-x-full opacity-0 transition-all duration-300 ease-out'
            ].join(' ');
            toast.style.backgroundColor = '#102B3C';
            toast.innerHTML = `
                <svg class="w-5 h-5 shrink-0" style="color:#93c5fd" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span class="flex-1">${message}</span>
                <button onclick="this.closest('[data-toast]').remove()" style="opacity:0.6;font-size:1rem;line-height:1;background:none;border:none;color:white;cursor:pointer;">&times;</button>
            `;
            toast.setAttribute('data-toast', '');
            container.appendChild(toast);

            // Animate in
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    toast.classList.remove('translate-x-full', 'opacity-0');
                });
            });

            // Auto-dismiss after 4s
            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                toast.addEventListener('transitionend', () => toast.remove(), { once: true });
            }, 4000);
        };

        return {
            open: false,
            loading: false,
            items: [],
            unreadCount: 0,

            async init() {
                await this.refreshUnread();
                // Poll every 30s; toast if new notifications arrive
                setInterval(async () => {
                    const prevCount = this.unreadCount;
                    await this.refreshUnread();
                    if (this.unreadCount > prevCount) {
                        const diff = this.unreadCount - prevCount;
                        showToast(`You have ${diff} new notification${diff > 1 ? 's' : ''}!`);
                    }
                }, 30000);
            },

            toggle() {
                this.open = !this.open;
                if (this.open) {
                    this.load();
                }
            },

            async refreshUnread() {
                try {
                    const r = await fetch('/notifications/unread', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    const data = await r.json();
                    this.unreadCount = Array.isArray(data) ? data.filter(x => !(x.isRead ?? x.IsRead)).length : 0;
                } catch (e) {
                    // ignore
                }
            },

            async load() {
                this.loading = true;
                try {
                    const r = await fetch('/notifications', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    const data = await r.json();
                    this.items = Array.isArray(data) ? data.map(norm) : [];
                    this.unreadCount = this.items.filter(x => !x.isRead).length;
                } catch (e) {
                    this.items = [];
                } finally {
                    this.loading = false;
                }
            },

            async markRead(n) {
                if (!n || !n.id || n.isRead) return;
                try {
                    await fetch(`/notifications/${n.id}/read`, {
                        method: 'PUT',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        credentials: 'same-origin',
                    });
                    n.isRead = true;
                    this.unreadCount = Math.max(0, this.items.filter(x => !x.isRead).length);
                } catch (e) {}
            },

            async markAllRead() {
                if (this.unreadCount <= 0) return;
                try {
                    await fetch('/notifications/read-all', {
                        method: 'PUT',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        credentials: 'same-origin',
                        body: JSON.stringify({}),
                    });
                    this.items = this.items.map(x => ({ ...x, isRead: true }));
                    this.unreadCount = 0;
                } catch (e) {}
            },

            async remove(n) {
                if (!n || !n.id) return;
                try {
                    await fetch(`/notifications/${n.id}`, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        credentials: 'same-origin',
                    });
                    this.items = this.items.filter(x => x.id !== n.id);
                    this.unreadCount = Math.max(0, this.items.filter(x => !x.isRead).length);
                } catch (e) {}
            },
        };
    }
</script>
</body>
</html>
