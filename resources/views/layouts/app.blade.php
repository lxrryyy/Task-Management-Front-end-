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

        pipSupported: ('documentPictureInPicture' in window),
        isPoppedOut: false,
        pipRef: null,

        async popout() {
            if (!('documentPictureInPicture' in window)) {
                alert('Pop Out requires Chrome or Edge. Your browser does not support it.');
                return;
            }
            await stickyPopout(this);
        },
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
                {{-- Pop Out button --}}
                <button
                    @click.stop="popout()"
                    :disabled="isPoppedOut"
                    x-text="isPoppedOut ? '📌' : '⬆ Pop Out'"
                    class="text-white text-xs font-semibold px-2 py-0.5 rounded transition bg-white/20 hover:bg-white/30 border border-white/30 disabled:opacity-50 disabled:cursor-not-allowed"
                ></button>
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

{{-- ══ PiP popout logic — $ signs escaped with @$ so Blade ignores them ══ --}}
<script>
async function stickyPopout(component) {
    if (!('documentPictureInPicture' in window)) return;
    try {
        const pip = await documentPictureInPicture.requestWindow({ width: 260, height: 400 });
        component.pipRef = pip;
        component.isPoppedOut = true;

        const storageKey = component.storageKey;

        pip.document.head.innerHTML = '<meta charset="UTF-8">'
            + '<link href="https://fonts.bunny.net/css?family=ubuntu:400,500,600&display=swap" rel="stylesheet">'
            + '<style>'
            + '*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }'
            + 'html, body { width: 100%; height: 100%; font-family: "Ubuntu", sans-serif; }'
            + 'body { display: flex; flex-direction: column; background: #F0EFEF; }'
            + '.pip-header { background: #102B3C; color: white; padding: 8px 12px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }'
            + '.pip-title { font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; }'
            + '.pip-date { font-size: 0.65rem; opacity: 0.6; }'
            + '.pip-nav { display: flex; align-items: center; gap: 6px; }'
            + '.pip-nav button { background: none; border: none; color: white; cursor: pointer; font-size: 1rem; opacity: 0.7; padding: 2px 4px; }'
            + '.pip-nav button:hover { opacity: 1; }'
            + '.pip-add { display: flex; gap: 6px; padding: 8px; border-bottom: 1px solid #e5e7eb; flex-shrink: 0; background: #f9f9f9; }'
            + '.pip-add input { flex: 1; font-size: 0.7rem; border: 1px solid #fcd34d; border-radius: 4px; padding: 4px 8px; outline: none; background: white; }'
            + '.pip-add input:focus { border-color: #f59e0b; }'
            + '.pip-add button { font-size: 0.7rem; background: #f59e0b; color: white; border: none; border-radius: 4px; padding: 4px 8px; cursor: pointer; }'
            + '.pip-list { flex: 1; overflow-y: auto; }'
            + '.pip-empty { font-size: 0.7rem; color: #9ca3af; text-align: center; padding: 20px 12px; }'
            + '.pip-item { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; padding: 8px 12px; border-bottom: 1px solid #fef08a; }'
            + '.pip-item p { font-size: 0.72rem; color: #374151; line-height: 1.4; flex: 1; word-break: break-word; }'
            + '.pip-item button { background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 0.8rem; padding: 0 2px; line-height: 1; flex-shrink: 0; }'
            + '.pip-item button:hover { color: #ef4444; }'
            + '.pip-footer { padding: 6px 10px; font-size: 0.6rem; color: #9ca3af; text-align: center; flex-shrink: 0; border-top: 1px solid #e5e7eb; }'
            + '</style>';

        const render = function(todos, ymd) {
            const items = todos[ymd] ?? [];
            const d = new Date(ymd + 'T00:00:00');
            const label = d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

            let itemsHtml = items.length === 0
                ? '<p class="pip-empty">No notes for this day.<br>Use the field above to add one.</p>'
                : items.map(function(i) {
                    return '<div class="pip-item" data-id="' + i.id + '"><p>' + i.text + '</p><button class="remove-btn" data-id="' + i.id + '">&#x2715;</button></div>';
                }).join('');

            pip.document.body.innerHTML =
                '<div class="pip-header">'
                    + '<div><div class="pip-title">&#128204; To Do</div><div class="pip-date">' + label + '</div></div>'
                    + '<div class="pip-nav"><button id="prev">&#8249;</button><button id="next">&#8250;</button></div>'
                + '</div>'
                + '<div class="pip-add"><input id="pip-input" type="text" placeholder="Add a note..." /><button id="pip-add-btn">Add</button></div>'
                + '<div class="pip-list">' + itemsHtml + '</div>'
                + '<div class="pip-footer">Always on top &middot; Synced with calendar</div>';

            pip.document.getElementById('prev').onclick = function() {
                const dt = new Date(currentYmd + 'T00:00:00');
                dt.setDate(dt.getDate() - 1);
                currentYmd = dt.toISOString().slice(0, 10);
                render(currentTodos, currentYmd);
            };
            pip.document.getElementById('next').onclick = function() {
                const dt = new Date(currentYmd + 'T00:00:00');
                dt.setDate(dt.getDate() + 1);
                currentYmd = dt.toISOString().slice(0, 10);
                render(currentTodos, currentYmd);
            };
            pip.document.getElementById('pip-add-btn').onclick = function() {
                const input = pip.document.getElementById('pip-input');
                const text = input.value.trim();
                if (!text) return;
                if (!currentTodos[currentYmd]) currentTodos[currentYmd] = [];
                currentTodos[currentYmd] = currentTodos[currentYmd].concat([{ id: Date.now(), text: text }]);
                localStorage.setItem(storageKey, JSON.stringify(currentTodos));
                component.todos = Object.assign({}, currentTodos);
                input.value = '';
                render(currentTodos, currentYmd);
            };
            pip.document.getElementById('pip-input').onkeydown = function(e) {
                if (e.key === 'Enter') pip.document.getElementById('pip-add-btn').click();
            };
            pip.document.querySelectorAll('.remove-btn').forEach(function(btn) {
                btn.onclick = function() {
                    const id = parseInt(btn.dataset.id);
                    currentTodos[currentYmd] = (currentTodos[currentYmd] ?? []).filter(function(i) { return i.id !== id; });
                    if (currentTodos[currentYmd].length === 0) delete currentTodos[currentYmd];
                    localStorage.setItem(storageKey, JSON.stringify(currentTodos));
                    component.todos = Object.assign({}, currentTodos);
                    render(currentTodos, currentYmd);
                };
            });
        };

        let currentYmd = component.selectedYmd;
        let currentTodos = Object.assign({}, component.todos);

        render(currentTodos, currentYmd);

        window.addEventListener('storage', function(e) {
            if (e.key === storageKey) {
                try { currentTodos = JSON.parse(e.newValue || '{}'); render(currentTodos, currentYmd); } catch(err) {}
            }
        });

        pip.addEventListener('pagehide', function() {
            component.isPoppedOut = false;
            component.pipRef = null;
        });

    } catch(err) {
        console.error('PiP failed:', err);
        component.isPoppedOut = false;
    }
}
</script>

</body>
</html>