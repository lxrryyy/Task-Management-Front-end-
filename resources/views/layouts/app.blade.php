<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=ubuntu:400,500,600&display=swap" rel="stylesheet" />
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

            <a href="/time-logs"
               class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('time-logs') ? 'focus-clr-accent' : '' }} hover-clr-accent">
                <x-icons.time-logs classes="w-6 h-6" />
                <span class="hidden group-hover:block">Time Logs</span>
            </a>
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
                <span class="text-base-100">Hi, {{ Session::get('user')['name'] ?? Session::get('user')['Name'] ?? 'User' }}!</span>
                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center">👤</div>
            </div>
        </div>

        <main class="flex-1 overflow-auto p-6">
            {{ $slot }}
        </main>

    </div>

</div>

@if(Session::get('user'))
{{-- ══════════════════ GLOBAL FLOATING STICKY NOTE (synced with calendar to-do) ══════════════════ --}}
<div
    x-data="{
        storageKey: 'calendar_todos_{{ Session::get('user')['id'] ?? Session::get('user')['Id'] ?? 'guest' }}',
        posKey:     'sticky_note_pos_{{ Session::get('user')['id'] ?? Session::get('user')['Id'] ?? 'guest' }}',
        visible: true,
        minimized: false,
        todos: {},
        newItem: '',
        showInput: false,
        selectedYmd: new Date().toISOString().slice(0, 10),
        posX: window.innerWidth - 260,
        posY: window.innerHeight - 420,
        dragging: false,
        dragOffsetX: 0,
        dragOffsetY: 0,

        get todosForDay() { return this.todos[this.selectedYmd] ?? []; },
        get dateLabel() {
            const d = new Date(this.selectedYmd + 'T00:00:00');
            return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        },

        prevDay() {
            const d = new Date(this.selectedYmd + 'T00:00:00');
            d.setDate(d.getDate() - 1);
            this.selectedYmd = d.toISOString().slice(0, 10);
        },
        nextDay() {
            const d = new Date(this.selectedYmd + 'T00:00:00');
            d.setDate(d.getDate() + 1);
            this.selectedYmd = d.toISOString().slice(0, 10);
        },

        init() {
            try { this.todos = JSON.parse(localStorage.getItem(this.storageKey) || '{}'); } catch(e) { this.todos = {}; }
            try { const p = JSON.parse(localStorage.getItem(this.posKey)); if (p) { this.posX = p.x; this.posY = p.y; } } catch(e) {}
            window.addEventListener('storage', (e) => {
                if (e.key === this.storageKey) {
                    try { this.todos = JSON.parse(e.newValue || '{}'); } catch(e) {}
                }
            });
        },
        save()    { localStorage.setItem(this.storageKey, JSON.stringify(this.todos)); },
        savePos() { localStorage.setItem(this.posKey, JSON.stringify({ x: this.posX, y: this.posY })); },

        addItem() {
            const text = this.newItem.trim();
            if (!text) return;
            if (!this.todos[this.selectedYmd]) this.todos[this.selectedYmd] = [];
            this.todos[this.selectedYmd] = [...this.todos[this.selectedYmd], { id: Date.now(), text }];
            this.save();
            this.newItem = '';
            this.showInput = false;
        },
        removeItem(id) {
            if (!this.todos[this.selectedYmd]) return;
            this.todos[this.selectedYmd] = this.todos[this.selectedYmd].filter(i => i.id !== id);
            if (this.todos[this.selectedYmd].length === 0) delete this.todos[this.selectedYmd];
            this.save();
        },

        startDrag(e) {
            this.dragging = true;
            this.dragOffsetX = e.clientX - this.posX;
            this.dragOffsetY = e.clientY - this.posY;
        },
        onDrag(e) {
            if (!this.dragging) return;
            this.posX = Math.max(0, Math.min(window.innerWidth - 224, e.clientX - this.dragOffsetX));
            this.posY = Math.max(0, Math.min(window.innerHeight - 42, e.clientY - this.dragOffsetY));
        },
        stopDrag() { if (this.dragging) this.savePos(); this.dragging = false; },
    }"
    x-init="init()"
    @mousemove.window="onDrag($event)"
    @mouseup.window="stopDrag()"
>
    {{-- Sticky note --}}
    <div
        x-show="visible"
        x-transition
        :style="`position:fixed; left:${posX}px; top:${posY}px; z-index:9999; width:15rem;`"
        class="rounded shadow-xl flex flex-col"
    >
        {{-- Header --}}
        <div
            @mousedown="startDrag($event)"
            class="flex items-center justify-between px-3 py-2 rounded-t cursor-grab active:cursor-grabbing select-none clr-bg-primary"
        >
            <div class="flex items-center gap-1 min-w-0">
                <p class="text-base-100">TO DO</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button @click.stop="showInput = !showInput" title="Add item"
                        class="text-white transition text-base leading-none font-light">+</button>
                <button @click.stop="minimized = !minimized"
                        class="text-white transition text-sm leading-none"
                        x-text="minimized ? 'v' : '-'"></button>
                <button @click.stop="visible = false" title="Close"
                        class="text-white transition leading-none">
                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Body --}}
        <div x-show="!minimized" class="rounded-b border border-t-0 flex flex-col" style="max-height:18rem; background-color: #F0EFEF;">

            {{-- Add input --}}
            <div x-show="showInput" x-transition class="px-3 pt-2.5 pb-1 shrink-0 border-b clr-bg-secondary">
                <div class="flex gap-1.5">
                    <input
                        x-model="newItem"
                        @keydown.enter="addItem()"
                        @keydown.escape="showInput = false; newItem = ''"
                        type="text"
                        placeholder="Write a note..."
                        class="flex-1 text-xs border border-yellow-300 rounded px-2 py-1.5 bg-white focus:outline-none focus:border-yellow-400"
                        x-effect="if(showInput) $nextTick(() => $el.focus())"
                    />
                    <button @click="addItem()"
                            class="px-2 py-1 text-xs rounded text-white bg-yellow-500 hover:bg-yellow-400 transition shrink-0">
                        Add
                    </button>
                </div>
            </div>

            {{-- Items list --}}
            <div class="flex-1 overflow-y-auto flex flex-col">
                <template x-if="todosForDay.length === 0">
                    <p class="text-xs clr-primary text-center py-5 px-3">No notes for this day.<br>Click + to add one.</p>
                </template>
                <template x-for="item in todosForDay" :key="item.id">
                    <div class="flex items-start justify-between gap-2 px-3 py-2.5 border-b border-yellow-200 group last:border-b-0">
                        <p class="text-xs text-gray-700 leading-snug flex-1 break-words" x-text="item.text"></p>
                        <button @click="removeItem(item.id)"
                                class="clr-primary hover:text-red-400 transition opacity-0 group-hover:opacity-100 shrink-0 mt-0.5">
                            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endif

</body>
</html>
