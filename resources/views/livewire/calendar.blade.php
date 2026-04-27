<div>
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
            tasks: @js($calendarTasks),

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
                    red:  { border:'border-red-400',  bg:'bg-white', title:'text-gray-800', sub:'text-gray-500', badge:'bg-red-100 text-red-700' },
                    pink: { border:'border-pink-500', bg:'bg-white', title:'text-gray-800', sub:'text-gray-500', badge:'bg-pink-100 text-pink-700' },
                    blue: { border:'border-blue-500', bg:'bg-white', title:'text-gray-800', sub:'text-gray-500', badge:'bg-blue-100 text-blue-800' },
                    gray: { border:'border-gray-400', bg:'bg-white', title:'text-gray-800', sub:'text-gray-500', badge:'bg-gray-200 text-gray-700' },
                }[color] ?? { border:'border-blue-500', bg:'bg-white', title:'text-gray-800', sub:'text-gray-500', badge:'bg-blue-100 text-blue-800' };
            },

            /* ── mini calendar ──────────────────────── */
            get miniMonthLabel() {
                return this.miniDate.toLocaleDateString('en-GB',{month:'long',year:'numeric'});
            },
            prevMiniMonth() { const d=new Date(this.miniDate); d.setMonth(d.getMonth()-1); this.miniDate=d; },
            nextMiniMonth() { const d=new Date(this.miniDate); d.setMonth(d.getMonth()+1); this.miniDate=d; },
            setMiniYear(year) {
                const y = parseInt(year, 10);
                if (!Number.isFinite(y)) return;
                const d = new Date(this.miniDate);
                d.setFullYear(y);
                this.miniDate = d;
            },
            setMiniMonth(month) {
                const m = parseInt(month, 10);
                if (!Number.isFinite(m)) return;
                const d = new Date(this.miniDate);
                d.setMonth(m);
                this.miniDate = d;
            },
            get miniYears() {
                const current = new Date().getFullYear();
                const out = [];
                for (let y = current - 5; y <= current + 5; y++) out.push(y);
                return out;
            },
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
                    cells.push(row);
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
            notes: @js($stickyNotes ?? []),
            noteDeleting: null,

            get selectedDateLabel() {
                return this.currentDate.toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
            },
            noteDate(iso) {
                return new Date(iso).toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
            },
            priorityLabel(priority) {
                const raw = String(priority ?? '').trim();
                return raw.replace(/^[\u2022\u00b7\-\*]\s*/, '');
            },
            noteBorderClass(note) {
                const palette = ['border-pink-500', 'border-blue-500', 'border-emerald-500', 'border-amber-500'];
                const seed = Number(note?.id || 0);
                return palette[Math.abs(seed) % palette.length];
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
        style="height: calc(100vh - 4rem);"
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
                            <div class="flex flex-col gap-1">
                                <div class="">
                                    <p class="font-normal text-lg" :class="colorClasses(task.color).title" x-text="task.title"></p>
                                    <p class="text-xs mt-1" :class="colorClasses(task.color).sub"
                                    x-text="task.subtasks + ' Subtask' + (task.subtasks!==1?'s':'')"></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                {{-- Assignee initials avatars --}}
                                <div class="flex -space-x-2">
                                    <template x-for="(assignee,i) in (task.assignees || []).slice(0,3)" :key="i">
                                        <div class="w-7 h-7 rounded-full border-2 border-white overflow-hidden flex items-center justify-center text-white text-xs font-medium"
                                            :style="'background:'+avatarBg(assignee.name)"
                                            :title="assignee.name">
                                            <template x-if="assignee.profilePicture">
                                                <img :src="assignee.profilePicture" alt=""
                                                    class="w-full h-full object-cover"
                                                    x-on:error="$el.style.display='none'; const s = $el.parentElement?.querySelector('[data-avatar-initials]'); if (s) s.classList.remove('hidden');" />
                                            </template>
                                            <span data-avatar-initials :class="assignee.profilePicture ? 'hidden' : ''" x-text="initials(assignee.name)"></span>
                                        </div>
                                    </template>
                                </div>
                                {{-- Three-dot removed --}}
                            </div>
                        </div>
                        <div class="mt-auto flex items-center justify-between text-xs" :class="colorClasses(task.color).sub">
                            <span class="text-sm" x-text="task.project"></span>
                            <span
                                class="px-2 py-0.5 rounded"
                                :class="{
                                    'bg-red-100 text-red-700': task.color === 'red',
                                    'bg-pink-100 text-pink-700': task.color === 'pink',
                                    'bg-blue-100 text-blue-800': task.color === 'blue',
                                    'bg-gray-200 text-gray-700': task.color === 'gray',
                                    'bg-blue-100 text-blue-800': !['red','pink','blue','gray'].includes(task.color),
                                }"
                                x-text="task.priority"></span>
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
                                    class="relative rounded-xl border border-gray-200 bg-white shadow-sm cursor-pointer transition-all duration-300 hover:shadow-lg overflow-hidden"
                                    style="min-height:12rem;"
                                    @click="goToTask(task)">
                                    {{-- Left color bar --}}
                                    <div
                                        class="absolute left-0 top-0 bottom-0 w-1.5"
                                        :class="{
                                            'bg-red-500': task.color === 'red',
                                            'bg-pink-500': task.color === 'pink',
                                            'bg-blue-600': task.color === 'blue',
                                            'bg-gray-400': task.color === 'gray',
                                            'bg-blue-600': !['red','pink','blue','gray'].includes(task.color),
                                        }"
                                    ></div>

                                    <div class="flex flex-col h-full pl-5 pr-4 py-4">
                                        {{-- Title + priority pill --}}
                                        <div>
                                            <p class="text-lg font-medium text-gray-900 leading-snug" x-text="task.title"></p>
                                            <div class="mt-2 inline-flex h-5 items-center gap-2 px-2 py-1 rounded-md"
                                                :class="{
                                                    'bg-red-100 text-red-700': task.color === 'red',
                                                    'bg-pink-100 text-pink-700': task.color === 'pink',
                                                    'bg-blue-100 text-blue-800': task.color === 'blue',
                                                    'bg-gray-200 text-gray-700': task.color === 'gray',
                                                    'bg-blue-100 text-blue-800': !['red','pink','blue','gray'].includes(task.color),
                                                }">
                                                <span class="w-1 h-1 rounded-full"
                                                    :class="{
                                                        'bg-red-600': task.color === 'red',
                                                        'bg-pink-600': task.color === 'pink',
                                                        'bg-blue-700': task.color === 'blue',
                                                        'bg-gray-600': task.color === 'gray',
                                                        'bg-blue-700': !['red','pink','blue','gray'].includes(task.color),
                                                    }"></span>
                                                <span class="text-xs font-medium" x-text="priorityLabel(task.priority)"></span>
                                            </div>
                                        </div>

                                        {{-- Subtasks --}}
                                        <p class="mt-3 text-xs text-gray-700"
                                            x-text="(task.subtasks ?? 0) + ' subtasks'"></p>

                                        {{-- Bottom row --}}
                                        <div class="mt-auto flex items-end justify-between gap-2">
                                            <p class="text-xs text-gray-500 leading-tight" x-text="task.project"></p>
                                        </div>
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
                    <div class="flex items-center gap-2">
                    <span class="text-base font-normal text-gray-800" x-text="miniMonthLabel"></span>

                    <!-- Year -->
                    <div class="flex flex-col justify-center items-center w-28 gap-1">
                        <select class="select select-bordered select-xs text-xs min-h-0 h-9"
                                x-effect="$el.value = miniDate.getFullYear()"
                                @change="setMiniYear($event.target.value)">
                            <template x-for="yy in miniYears" :key="yy">
                                <option :value="yy" x-text="yy"></option>
                            </template>
                        </select>

                        <!-- Month -->
                        <select class="select select-bordered select-xs text-xs min-h-0 h-9"
                                x-effect="$el.value = miniDate.getMonth()"
                                @change="setMiniMonth($event.target.value)">
                            <template x-for="(mm, index) in ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']" :key="index">
                                <option :value="index" x-text="mm"></option>
                            </template>
                        </select>
                    </div>
                </div>
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
                                                            'clr-bg-accent text-white': !isSelected(cell.ymd) && hasTask(cell.ymd),
                                                            'bg-gray-100': isToday(cell.ymd) && !isSelected(cell.ymd),
                                                            'text-gray-500 hover:bg-gray-100': !isSelected(cell.ymd) && !isToday(cell.ymd),
                                                        }"
                                                        class="w-6 h-6 text-sm rounded-full flex items-center justify-center transition relative"
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
                        <div :class="['relative group rounded-lg border-l-4 p-3 shadow-sm cursor-pointer transition hover:shadow-md bg-white text-gray-800', noteBorderClass(note)]"
                             @click="openViewNote(note)">
                            {{-- Folded corner --}}
                            <div class="absolute bottom-0 right-0 w-0 h-0 pointer-events-none"
                                 style="border-style:solid;border-width:0 0 14px 14px;border-color:transparent transparent rgba(255,255,255,0.22) transparent;"></div>
                            <p class="text-sm leading-snug pr-6 whitespace-pre-wrap line-clamp-3 font-normal"
                               x-text="note.content"></p>
                            <p class="text-xs mt-1.5 text-gray-500"
                               x-text="noteDate(note.updatedAt || note.createdAt)"></p>
                            {{-- Delete button (hover) --}}
                            <button @click.stop="deleteNote(note.id)"
                                    :disabled="noteDeleting === note.id"
                                    class="absolute inset-y-0 right-0 flex items-center pr-2 opacity-0 group-hover:opacity-100 transition"
                                    title="Delete note">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"
                                     viewBox="0 0 24 24" class="text-gray-500">
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
