<div>
    <script id="calendar-tasks-data"  type="application/json">@json($calendarTasks)</script>
    <script id="sticky-notes-data"    type="application/json">@json($stickyNotes ?? [])</script>

    <div
        x-data="{
            view: 'Day',
            currentDate: new Date(),
            miniDate: new Date(),

            /* ── helpers ───────────────────────────── */
            toYMD(date) {
                const d = new Date(date);
                return d.getFullYear() + '-'
                    + String(d.getMonth()+1).padStart(2,'0') + '-'
                    + String(d.getDate()).padStart(2,'0');
            },
            weekStart(date) {
                const d = new Date(date); const day = d.getDay();
                d.setDate(d.getDate() - day + (day === 0 ? -6 : 1)); return d;
            },
            initials(name) {
                return name.trim().split(/\s+/).slice(0,2).map(p => p[0].toUpperCase()).join('');
            },
            avatarBg(name) {
                const colors = ['#6366f1','#ec4899','#3b82f6','#22c55e','#f59e0b','#ef4444','#8b5cf6','#14b8a6'];
                let h = 0; for (let c of name) h = (h*31 + c.charCodeAt(0)) & 0xFFFFFF;
                return colors[Math.abs(h) % colors.length];
            },

            /* ── tasks (injected from PHP) ──────────── */
            tasks: JSON.parse(document.getElementById('calendar-tasks-data').textContent),

            /* ── day-view header ────────────────────── */
            get formattedDate() {
                if (this.view === 'Day')
                    return this.currentDate.toLocaleDateString('en-GB', { day:'numeric', month:'long' });
                const s = this.weekStart(this.currentDate);
                const e = new Date(s); e.setDate(e.getDate()+6);
                return s.toLocaleDateString('en-GB',{day:'numeric',month:'short'})
                    + ' – ' + e.toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'});
            },
            prev() {
                const d = new Date(this.currentDate);
                this.view==='Day' ? d.setDate(d.getDate()-1) : d.setDate(d.getDate()-7);
                this.currentDate = d;
            },
            next() {
                const d = new Date(this.currentDate);
                this.view==='Day' ? d.setDate(d.getDate()+1) : d.setDate(d.getDate()+7);
                this.currentDate = d;
            },

            /* ── filtered tasks ─────────────────────── */
            get filteredTasks() {
                if (this.view === 'Day') {
                    const t = this.toYMD(this.currentDate);
                    return this.tasks.filter(t2 => t2.dueDate === t);
                }
                const s = this.weekStart(this.currentDate);
                const days = Array.from({length:7},(_,i) => { const d=new Date(s); d.setDate(d.getDate()+i); return this.toYMD(d); });
                return this.tasks.filter(t => days.includes(t.dueDate));
            },
            get weekDays() {
                const s = this.weekStart(this.currentDate);
                return Array.from({length:7},(_,i) => {
                    const d=new Date(s); d.setDate(d.getDate()+i);
                    return {
                        label:   d.toLocaleDateString('en-GB',{weekday:'short',day:'numeric'}),
                        dayName: d.toLocaleDateString('en-GB',{weekday:'long'}),
                        dayNum:  d.getDate(),
                        ymd:     this.toYMD(d),
                    };
                });
            },
            tasksForDay(ymd) { return this.tasks.filter(t => t.dueDate===ymd); },

            /* ── card colors ────────────────────────── */
            colorClasses(color) {
                return {
                    red:  { border:'border-red-400',  bg:'bg-red-50',   title:'text-red-700',   sub:'text-red-400'  },
                    pink: { border:'border-pink-500',  bg:'bg-pink-50',  title:'text-pink-700',  sub:'text-pink-400' },
                    blue: { border:'border-blue-500',  bg:'bg-blue-50',  title:'text-blue-800',  sub:'text-blue-400' },
                    gray: { border:'border-gray-400',  bg:'bg-gray-50',  title:'text-gray-700',  sub:'text-gray-400' },
                }[color] ?? { border:'border-blue-500', bg:'bg-blue-50', title:'text-blue-800', sub:'text-blue-400' };
            },

            /* ── mini calendar ──────────────────────── */
            get miniMonthLabel() {
                return this.miniDate.toLocaleDateString('en-GB',{month:'long',year:'numeric'});
            },
            prevMiniMonth() { const d=new Date(this.miniDate); d.setMonth(d.getMonth()-1); this.miniDate=d; },
            nextMiniMonth() { const d=new Date(this.miniDate); d.setMonth(d.getMonth()+1); this.miniDate=d; },
            get miniDays() {
                const y=this.miniDate.getFullYear(), m=this.miniDate.getMonth();
                const first=new Date(y,m,1).getDay();
                const offset = first===0 ? 6 : first-1;
                const daysInMonth = new Date(y,m+1,0).getDate();
                const cells=[]; let d=1;
                for(let w=0;w<6;w++){
                    const row=[];
                    for(let c=0;c<7;c++){
                        const idx=w*7+c;
                        if(idx<offset||d>daysInMonth){ row.push(null); }
                        else { row.push({day:d, ymd:`${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`}); d++; }
                    }
                    row.push(row); cells.push(row);
                    if(d>daysInMonth) break;
                }
                return cells;
            },
            selectDay(ymd) {
                this.currentDate = new Date(ymd+'T00:00:00');
                this.view = 'Day';
            },
            isToday(ymd) { return ymd===this.toYMD(new Date()); },
            isSelected(ymd) { return ymd===this.toYMD(this.currentDate); },
            hasTask(ymd) { return this.tasks.some(t => t.dueDate===ymd); },

            /* ── navigation ─────────────────────────── */
            goToTask(task) {
                if (task.projectId) {
                    window.location.href = '/projects/' + task.projectId + '/tasks';
                }
            },

            /* ── sticky notes (API-backed) ───────────── */
            notes: JSON.parse(document.getElementById('sticky-notes-data').textContent),
            noteDeleting: null,

            get selectedDateLabel() {
                return this.currentDate.toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
            },
            noteDate(iso) {
                return new Date(iso).toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
            },

            openNewNote() {
                if (typeof window._calPopOut === 'function') {
                    window._calPopOut(null, '');
                }
            },
            openViewNote(note) {
                if (typeof window._calPopOut === 'function') {
                    window._calPopOut(note.id, note.content);
                }
            },
            async deleteNote(id) {
                if (!confirm('Delete this sticky note?')) return;
                this.noteDeleting = id;
                try {
                    await fetch('/notes/' + id, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': window._calCsrf || document.querySelector('meta[name=csrf-token]').content },
                    });
                    this.notes = this.notes.filter(n => n.id !== id);
                    if (window._calPopupClosed) window._calPopupClosed(id);
                } finally {
                    this.noteDeleting = null;
                }
            },

            async refreshNotes() {
                try {
                    const r = await fetch('/notes', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    const data = await r.json();
                    this.notes = Array.isArray(data) ? data : [];
                } catch (e) {}
            },

            init() {
                window.addEventListener('cal-notes-refresh', () => this.refreshNotes());
                if (typeof window._calInit === 'function') window._calInit(this);
            },
        }"
        x-init="init()"
        class="flex w-full h-full overflow-hidden"
        style="height: calc(100vh - 8rem);"
    >
        {{-- ══════════════════ LEFT SIDE ══════════════════ --}}
        <div class="flex-1 flex flex-col overflow-hidden border-r border-gray-100">

            {{-- Header (fixed, no scroll) --}}
            <div class="flex items-center justify-between p-6 pb-4 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="flex gap-1">
                        <button @click="prev()" class="w-7 h-7 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                        </button>
                        <button @click="next()" class="w-7 h-7 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                        </button>
                    </div>
                    <h2 class="text-xl font-normal text-gray-800" x-text="formattedDate"></h2>
                </div>
                <div class="flex rounded-full bg-gray-100 p-1 gap-1">
                    <template x-for="v in ['Day','Week']" :key="v">
                        <button @click="view=v"
                            :class="view===v ? 'clr-bg-primary text-white shadow' : 'clr-primary hover:text-gray-700'"
                            class="px-5 py-1.5 rounded-lg border-2 border-gray text-sm transition" x-text="v"></button>
                    </template>
                </div>
            </div>

            {{-- Scrollable task area --}}
            <div class="flex-1 overflow-y-auto px-6 pb-6">

            {{-- ── Day view ── --}}
            <div x-show="view==='Day'" class="flex flex-col gap-4">
                <template x-if="filteredTasks.length===0">
                    <div class="text-center text-gray-400 text-sm py-20">No tasks due on this day.</div>
                </template>
                <template x-for="task in filteredTasks" :key="task.id">
                    <div :class="['rounded-2xl border-l-4 p-5 shadow-sm flex flex-col gap-4 cursor-pointer transition-all duration-300 hover:shadow-lg hover:!bg-gray-200', colorClasses(task.color).border, colorClasses(task.color).bg]"
                        style="min-height:130px;"
                        @click="goToTask(task)">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-normal text-sm" :class="colorClasses(task.color).title" x-text="task.title"></p>
                                <p class="text-xs mt-1" :class="colorClasses(task.color).sub"
                                x-text="task.subtasks + ' Subtask' + (task.subtasks!==1?'s':'')"></p>
                            </div>
                            <div class="flex items-center gap-2">
                                {{-- Assignee initials avatars --}}
                                <div class="flex -space-x-2">
                                    <template x-for="(name,i) in task.assigneeNames.slice(0,3)" :key="i">
                                        <div class="w-7 h-7 rounded-full border-2 border-white flex items-center justify-center text-white text-xs font-medium"
                                            :style="'background:'+avatarBg(name)"
                                            :title="name"
                                            x-text="initials(name)"></div>
                                    </template>
                                </div>
                                {{-- Three-dot --}}
                                <button class="p-1 rounded-full hover:bg-white/60 transition" @click.stop>
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" class="text-gray-400">
                                        <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-xs" :class="colorClasses(task.color).sub">
                            <span x-text="task.project"></span>
                            <span class="px-2 py-0.5 rounded bg-white/70" :class="colorClasses(task.color).title" x-text="task.priority"></span>
                        </div>
                    </div>
                </template>
            </div>

            {{-- ── Week view ── --}}
            <div x-show="view==='Week'" style="display:none;" class="overflow-x-auto">
                {{-- Day headers --}}
                <div class="grid border-b border-gray-200 pb-3 mb-2" style="grid-template-columns:repeat(7,minmax(8rem,1fr));">
                    <template x-for="day in weekDays" :key="'h-'+day.ymd">
                        <div class="flex flex-col items-center px-2">
                            <span class="text-xs uppercase tracking-wide"
                                :class="isToday(day.ymd) ? 'clr-accent font-medium' : 'text-gray-500'"
                                x-text="day.dayName"></span>
                            <span class="mt-1 w-8 h-8 flex items-center justify-center rounded-full text-sm"
                                :class="isToday(day.ymd) ? 'clr-bg-primary text-white' : 'text-gray-700'"
                                x-text="day.dayNum"></span>
                        </div>
                    </template>
                </div>

                {{-- Day columns with tasks --}}
                <div class="grid" style="grid-template-columns:repeat(7,minmax(8rem,1fr));">
                    <template x-for="day in weekDays" :key="'c-'+day.ymd">
                        <div class="border-r border-gray-100 last:border-r-0 px-2 flex flex-col gap-3 min-h-[24rem]">
                            <template x-for="task in tasksForDay(day.ymd)" :key="task.id">
                                <div
                                    :class="['rounded-xl border-l-4 p-3 shadow-sm flex flex-col gap-3 cursor-pointer transition-all duration-300 hover:shadow-lg hover:!bg-gray-200', colorClasses(task.color).border, colorClasses(task.color).bg]"
                                    style="min-height:10rem;"
                                    @click="goToTask(task)">
                                    {{-- Title --}}
                                    <div>
                                        <p class="text-sm font-normal leading-snug" :class="colorClasses(task.color).title" x-text="task.title"></p>
                                        <p class="text-xs mt-1" :class="colorClasses(task.color).sub" x-text="task.project"></p>
                                    </div>
                                    {{-- Bottom: avatars + three-dot --}}
                                    <div class="flex items-center justify-between mt-auto">
                                        <div class="flex -space-x-2">
                                            <template x-for="(name,i) in task.assigneeNames.slice(0,3)" :key="i">
                                                <div class="w-6 h-6 rounded-full border-2 border-white flex items-center justify-center text-white text-xs"
                                                    :style="'background:'+avatarBg(name)"
                                                    :title="name"
                                                    x-text="initials(name)"></div>
                                            </template>
                                        </div>
                                        <button class="p-0.5 rounded-full hover:bg-white/60 transition" @click.stop>
                                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" class="text-gray-400">
                                                <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            </div>{{-- end scrollable task area --}}
        </div>

        {{-- ══════════════════ RIGHT SIDE ══════════════════ --}}
        <div class="w-80 shrink-0 flex flex-col overflow-hidden shadow-lg">

            {{-- ── Mini Calendar (white top) ── --}}
            <div class="bg-white p-6 rounded-t">

                {{-- Month header --}}
                <div class="px-2 flex items-center justify-between mb-8">
                    <span class="text-base font-normal text-gray-800" x-text="miniMonthLabel"></span>
                    <div class="flex items-center gap-2">
                        <button @click="prevMiniMonth()" class="hover:text-gray-400 text-gray-800 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="15 6 9 12 15 18"/>
                            </svg>
                        </button>
                        <button @click="nextMiniMonth()" class="hover:text-gray-400 text-gray-800 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="9 6 15 12 9 18"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Calendar table --}}
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr>
                                <template x-for="h in ['Mo','Tu','We','Th','Fr','Sa','Su']" :key="h">
                                    <th>
                                        <div class="w-full flex justify-center">
                                            <p class="text-sm font-normal text-center text-gray-600" x-text="h"></p>
                                        </div>
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, ri) in miniDays" :key="ri">
                                <tr>
                                    <template x-for="(cell, ci) in row" :key="ci">
                                        <td class="pt-3">
                                            <div class="w-full flex justify-center">
                                                <template x-if="cell">
                                                    <button
                                                        @click="selectDay(cell.ymd)"
                                                        :class="{
                                                            'clr-bg-primary text-white': isSelected(cell.ymd),
                                                            'bg-gray-100': isToday(cell.ymd) && !isSelected(cell.ymd),
                                                            'text-gray-500 hover:bg-gray-100': !isSelected(cell.ymd) && !isToday(cell.ymd),
                                                        }"
                                                        class="w-8 h-8 text-sm rounded-full flex items-center justify-center transition relative"
                                                        x-text="cell.day">
                                                    </button>
                                                </template>
                                                <template x-if="!cell">
                                                    <span class="w-8 h-8 inline-block"></span>
                                                </template>
                                            </div>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ── Sticky Notes (API-backed) ── --}}
            <div class="bg-gray-50 rounded-b flex-1 flex flex-col overflow-hidden">

                {{-- Header --}}
                <div class="px-6 py-4 shrink-0 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-normal text-gray-800">To-do</p>
                        <button @click="openNewNote()"
                                class="w-6 h-6 rounded flex items-center justify-center text-gray-500 hover:bg-gray-200 text-xl leading-none transition"
                                title="New sticky note">+</button>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="selectedDateLabel"></p>
                </div>

                {{-- Scrollable notes list --}}
                <div class="flex-1 overflow-y-auto px-4 py-3 flex flex-col gap-3">
                    <template x-if="notes.length === 0">
                        <p class="text-xs text-gray-400 text-center py-6">No sticky notes yet.<br>Click + to add one.</p>
                    </template>

                    <template x-for="note in notes" :key="note.id">
                        <div class="relative group rounded-lg p-3 shadow-sm cursor-pointer transition hover:shadow-md clr-bg-secondary text-base-100"
                             @click="openViewNote(note)">
                            {{-- Folded corner --}}
                            <div class="absolute bottom-0 right-0 w-0 h-0 pointer-events-none"
                                 style="border-style:solid;border-width:0 0 14px 14px;border-color:transparent transparent rgba(255,255,255,0.22) transparent;"></div>
                            <p class="text-sm leading-snug pr-6 whitespace-pre-wrap line-clamp-3 font-normal"
                               x-text="note.content"></p>
                            <p class="text-xs mt-1.5 opacity-80"
                               x-text="noteDate(note.updatedAt || note.createdAt)"></p>
                            {{-- Delete button (hover) --}}
                            <button @click.stop="deleteNote(note.id)"
                                    :disabled="noteDeleting === note.id"
                                    class="absolute inset-y-0 right-0 flex items-center pr-2 opacity-0 group-hover:opacity-100 transition"
                                    title="Delete note">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"
                                     viewBox="0 0 24 24" class="text-base-100">
                                    <path d="M18 6L6 18M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// Calendar sticky-note Pop Out (Picture-in-Picture window)
// Called by Alpine methods openNewNote/openViewNote via window._calPopOut(id, content)
(function () {
    window._calCsrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';

    async function fetchNotes() {
        const r = await fetch('/notes', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
        const data = await r.json();
        return Array.isArray(data) ? data : [];
    }

    window._calPopOut = async function (noteId, noteContent) {
        if (!('documentPictureInPicture' in window)) {
            alert('Pop Out requires Chrome or Edge.');
            return;
        }

        const pip = await documentPictureInPicture.requestWindow({ width: 320, height: 420 });

        pip.document.head.innerHTML =
            '<meta charset="UTF-8">' +
            '<link href="https://fonts.bunny.net/css?family=ubuntu:400,500,600&display=swap" rel="stylesheet">' +
            '<style>' +
            '*, *::before, *::after { box-sizing:border-box; }' +
            'html, body { width:100%; height:100%; margin:0; font-family:Ubuntu, sans-serif; background:#F0EFEF; }' +
            '.hdr{background:#102B3C;color:#fff;padding:10px 12px;display:flex;align-items:center;justify-content:space-between;}' +
            '.ttl{font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;}' +
            '.btn{background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.22);color:#fff;border-radius:6px;padding:4px 8px;font-size:11px;cursor:pointer;}' +
            '.btn:disabled{opacity:.5;cursor:not-allowed;}' +
            '.wrap{padding:10px 12px;display:flex;flex-direction:column;gap:10px;height:calc(100% - 44px);}' +
            '#editor{flex:1;display:flex;flex-direction:column;}' +
            '.row{display:flex;gap:8px;}' +
            'input,textarea{width:100%;border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px;font-size:12px;outline:none;background:#fff;}' +
            'textarea{min-height:110px;resize:none;}' +
            '.list{flex:1;overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;}' +
            '.item{padding:10px 10px;border-bottom:1px solid #f3f4f6;cursor:pointer;display:flex;align-items:flex-start;justify-content:space-between;gap:8px;}' +
            '.item:last-child{border-bottom:none;}' +
            '.item p{margin:0;font-size:12px;line-height:1.35;color:#374151;white-space:pre-wrap;word-break:break-word;flex:1;}' +
            '.x{border:none;background:none;color:#9ca3af;cursor:pointer;font-size:14px;line-height:1;padding:0 2px;}' +
            '.x:hover{color:#ef4444;}' +
            '.muted{color:#9ca3af;font-size:12px;text-align:center;padding:18px 10px;}' +
            '</style>';

        pip.document.body.innerHTML =
            '<div class="hdr">' +
                '<div class="ttl">To-do</div>' +
                '<button id="modeBtn" class="btn"></button>' +
            '</div>' +
            '<div class="wrap">' +
                '<div id="editor"></div>' +
                '<div class="list" id="list"></div>' +
            '</div>';

        const modeBtn = pip.document.getElementById('modeBtn');
        const editor = pip.document.getElementById('editor');
        const listEl = pip.document.getElementById('list');

        let mode = noteId ? 'edit' : 'new'; // 'new' | 'edit' | 'list'
        let currentId = noteId;

        function escapeHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        async function refreshCalendarList() {
            // force Livewire/Alpine section to refresh by reloading notes via a small fetch and storage in window var
            try { window.dispatchEvent(new CustomEvent('cal-notes-refresh')); } catch (e) {}
        }

        async function renderList() {
            const notes = await fetchNotes();
            if (!notes.length) {
                listEl.innerHTML = '<div class="muted">No notes yet.</div>';
                return;
            }
            listEl.innerHTML = notes.map(n =>
                '<div class="item" data-id="' + n.id + '">' +
                    '<p>' + escapeHtml((n.content ?? '')).slice(0, 240) + '</p>' +
                    '<button class="x" data-del="' + n.id + '">✕</button>' +
                '</div>'
            ).join('');

            listEl.querySelectorAll('[data-id]').forEach(el => {
                el.onclick = (e) => {
                    if (e.target && e.target.getAttribute('data-del')) return;
                    const id = parseInt(el.getAttribute('data-id'), 10);
                    const note = notes.find(nn => nn.id === id);
                    currentId = id;
                    mode = 'edit';
                    render();
                    if (note) {
                        pip.document.getElementById('content').value = note.content || '';
                    }
                };
            });
            listEl.querySelectorAll('[data-del]').forEach(btn => {
                btn.onclick = async (e) => {
                    e.stopPropagation();
                    const id = parseInt(btn.getAttribute('data-del'), 10);
                    await fetch('/notes/' + id, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window._calCsrf },
                        credentials: 'same-origin',
                    });
                    await renderList();
                    await refreshCalendarList();
                };
            });
        }

        async function render() {
            if (mode === 'new') {
                modeBtn.textContent = 'View list';
                editor.style.display = 'flex';
                listEl.style.display = 'block';
                editor.innerHTML =
                    '<div class="row">' +
                        '<input id="content" type="text" placeholder="Write a note..." />' +
                        '<button id="save" class="btn" style="background-color:#102b3c;">Add</button>' +
                    '</div>';
                listEl.innerHTML = '<div class="muted">Add a note, or switch to list.</div>';
                pip.document.getElementById('save').onclick = async () => {
                    const content = pip.document.getElementById('content').value.trim();
                    if (!content) return;
                    await fetch('/notes', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window._calCsrf },
                        body: JSON.stringify({ content, isPinned: false }),
                        credentials: 'same-origin',
                    });
                    pip.document.getElementById('content').value = '';
                    await refreshCalendarList();
                };
                pip.document.getElementById('content').onkeydown = (e) => {
                    if (e.key === 'Enter') pip.document.getElementById('save').click();
                };
                return;
            }

            if (mode === 'edit') {
                modeBtn.textContent = 'View list';
                editor.style.display = 'flex';
                editor.innerHTML =
                    '<div style="display:flex;flex-direction:column;height:100%;">' +
                        '<textarea id="content" placeholder="Edit note..." style="flex:1;resize:none;"></textarea>' +
                        '<div class="row" style="justify-content:flex-end;margin-top:8px;">' +
                            '<button id="update" class="btn" style="background-color:#102b3c;">Save</button>' +
                        '</div>' +
                    '</div>';
                listEl.style.display = 'none';
                pip.document.getElementById('content').value = noteContent || '';
                pip.document.getElementById('update').onclick = async () => {
                    const content = pip.document.getElementById('content').value.trim();
                    await fetch('/notes/' + currentId, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window._calCsrf },
                        body: JSON.stringify({ content }),
                        credentials: 'same-origin',
                    });
                    await refreshCalendarList();
                };
                return;
            }

            // list mode
            modeBtn.textContent = 'New note';
            editor.innerHTML = '';
            editor.style.display = 'none';
            listEl.style.display = 'block';
            await renderList();
        }

        modeBtn.onclick = async () => {
            if (mode === 'list') {
                mode = 'new';
            } else {
                mode = 'list';
            }
            await render();
        };

        // If opened from clicking an existing note, go edit; otherwise new.
        if (noteId) {
            mode = 'edit';
        }
        await render();

        // Mark closed
        pip.addEventListener('pagehide', function () {
            try { if (window._calPopupClosed) window._calPopupClosed(currentId); } catch (e) {}
        });
    };
})();
</script>
