<div>
    <script id="calendar-tasks-data" type="application/json">@json($calendarTasks)</script>

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

            /* ── to-do (localStorage notepad) ───────── */
            storageKey: 'calendar_todos_{{ Session::get("user")["id"] ?? Session::get("user")["Id"] ?? "guest" }}',
            todos: {},
            newTodo: '',
            showInput: false,

            loadTodos() {
                try { this.todos = JSON.parse(localStorage.getItem(this.storageKey) || '{}'); }
                catch(e) { this.todos = {}; }
            },
            saveTodos() {
                localStorage.setItem(this.storageKey, JSON.stringify(this.todos));
            },
            get selectedYmd() { return this.toYMD(this.currentDate); },
            get todosForDay() { return this.todos[this.selectedYmd] ?? []; },
            addTodo() {
                const text = this.newTodo.trim();
                if (!text) return;
                if (!this.todos[this.selectedYmd]) this.todos[this.selectedYmd] = [];
                this.todos[this.selectedYmd] = [...this.todos[this.selectedYmd], { id: Date.now(), text }];
                this.saveTodos();
                this.newTodo = '';
                this.showInput = false;
            },
            deleteTodo(id) {
                if (!this.todos[this.selectedYmd]) return;
                this.todos[this.selectedYmd] = this.todos[this.selectedYmd].filter(t => t.id !== id);
                if (this.todos[this.selectedYmd].length === 0) delete this.todos[this.selectedYmd];
                this.saveTodos();
            },
            get selectedDateLabel() {
                return this.currentDate.toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
            },
        }"
        x-init="loadTodos()"
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

            {{-- ── To-do notepad (gray bottom) ── --}}
            <div class="bg-gray-50 rounded-b flex-1 flex flex-col overflow-hidden">

                {{-- Fixed header --}}
                <div class="px-6 py-4 shrink-0 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-normal text-gray-800">To-do</p>
                        <button @click="showInput = !showInput"
                                class="w-6 h-6 rounded flex items-center justify-center text-gray-500 hover:bg-gray-200 text-xl leading-none transition">
                            <span x-text="showInput ? '×' : '+'"></span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="selectedDateLabel"></p>
                </div>

                {{-- Add input --}}
                <div x-show="showInput" class="px-6 pt-3 shrink-0" x-transition>
                    <div class="flex gap-2">
                        <input
                            x-model="newTodo"
                            @keydown.enter="addTodo()"
                            @keydown.escape="showInput=false; newTodo=''"
                            type="text"
                            placeholder="Write a note..."
                            class="flex-1 text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:border-gray-400 bg-white"
                            x-ref="todoInput"
                            x-effect="if(showInput) $nextTick(() => $refs.todoInput?.focus())"
                        />
                        <button @click="addTodo()"
                                class="px-3 py-1.5 text-xs rounded-lg clr-bg-primary text-white hover:opacity-90 transition">
                            Add
                        </button>
                    </div>
                </div>

                {{-- Scrollable notes list --}}
                <div class="flex-1 overflow-y-auto px-6 py-3">
                    <template x-if="todosForDay.length === 0">
                        <p class="text-xs text-gray-400 text-center py-6">No notes for this day.</p>
                    </template>

                    <template x-for="todo in todosForDay" :key="todo.id">
                        <div class="flex items-start justify-between gap-2 border-b border-gray-200 py-2.5 group rounded px-1 -mx-1 hover:bg-gray-100 transition-colors duration-500">
                            <p class="text-sm text-gray-800 leading-snug flex-1" x-text="todo.text"></p>
                            <button @click="deleteTodo(todo.id)"
                                    class="text-gray-300 hover:text-red-400 transition opacity-0 group-hover:opacity-100 shrink-0 mt-0.5">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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
