<div class="w-full mx-auto sm:px-6 lg:px-8 min-h-screen">
    @php use Carbon\Carbon; @endphp
    <style>
        .dash-shell { background:#f7f8f8; border:1px solid #e5e7eb; border-radius:14px; padding:12px; }
        .dash-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; }
        .dash-title { font-size:30px; font-weight:600; color:#111827; line-height:1; }
        .dash-muted { color:#6b7280; font-size:12px; }
        .dash-kpi-row { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:0; min-height:82px; }
        .dash-main-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .dash-panel-row { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .dash-assigned, .dash-projects { min-height:280px; }
        .dash-people, .dash-notepad { min-height:300px; }
        .dash-list-scroll { max-height:196px; overflow:auto; }
        .dash-project-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; align-content:start; }
        .dash-people-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; align-content:start; max-height:216px; overflow:auto; }
        @media (max-width: 1200px) {
            .dash-panel-row, .dash-main-grid { grid-template-columns:1fr; }
            .dash-kpi-row { grid-template-columns:repeat(2,minmax(0,1fr)); }
        }
    </style>

    <div class="pt-4 pb-6 space-y-5">
        @if ($flashSuccess)
            <div x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.dismissFlashSuccess() }, 6500)" x-show="show"
                 x-transition.opacity.duration.300ms class="alert alert-success text-sm py-2 px-4 rounded-lg">
                <span>{{ $flashSuccess }}</span>
            </div>
        @endif

        <p class="text-xl font-bold clr-txt-secondary">{{ Carbon::now()->format('l, F j, Y') }}</p>

        <div class="dash-shell space-y-4">
            <div class="dash-card px-2 py-2 dash-kpi-row">
                @php
                    $kpiMap = [
                        ['label' => 'Total Project', 'key' => 'totalProjects', 'trend' => 'text-emerald-500', 'delta' => 2],
                        ['label' => 'Total Tasks', 'key' => 'totalTasks', 'trend' => 'text-emerald-500', 'delta' => 4],
                        ['label' => 'Assigned Tasks', 'key' => 'assignedTasks', 'trend' => 'text-orange-500', 'delta' => 3],
                        ['label' => 'Completed Tasks', 'key' => 'completedTasks', 'trend' => 'text-emerald-500', 'delta' => 1],
                        ['label' => 'Overdue Tasks', 'key' => 'overdueTasks', 'trend' => 'text-red-500', 'delta' => 2],
                    ];
                @endphp
                @if($loading)
                    @foreach($kpiMap as $i => $k)
                        <div class="px-3 py-2 {{ $i < 4 ? 'border-r border-dashed border-gray-300' : '' }}">
                            <div class="flex items-center gap-1 text-md text-gray-500 leading-none">
                                <span>{{ $k['label'] }}</span>
                            </div>
                            <div class="h-8 w-20 bg-gray-200 rounded animate-pulse mt-2"></div>
                        </div>
                    @endforeach
                @else
                    @foreach($kpiMap as $i => $k)
                        <div class="px-3 py-2 {{ $i < 4 ? 'border-r border-dashed border-gray-300' : '' }}">
                            <div class="flex items-center gap-1 text-md text-gray-500 leading-none">
                                <span>{{ $k['label'] }}</span>
                                <span class="{{ $k['trend'] }}">▲ {{ $k['delta'] }}</span>
                            </div>
                            <div class="dash-title mt-2"
                                 wire:key="kpi-{{ $k['key'] }}-{{ (int) ($kpiCards[$k['key']] ?? 0) }}"
                                 x-data="countUpNumber({{ (int) ($kpiCards[$k['key']] ?? 0) }}, {{ 500 + ($i * 35) }})"
                                 x-init="start()"
                                 x-text="display"></div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="dash-panel-row">
                <section class="dash-card p-3 dash-assigned flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-lg font-semibold text-gray-900 leading-none">Assigned Tasks</h2>
                        <span class="text-xs text-orange-700 border border-orange-200 rounded-md px-2 py-1 bg-orange-50">Nearest Due Date</span>
                    </div>
                    <div class="border-t border-dashed border-gray-300 my-2"></div>
                    <div class="space-y-2 dash-list-scroll mt-3">
                        @if($loading)
                            @for($i = 0; $i < 4; $i++)
                                <div class="border border-gray-200 rounded-lg px-3 py-2">
                                    <div class="flex items-center justify-between">
                                        <div class="h-5 bg-gray-200 rounded animate-pulse w-2/3"></div>
                                        <div class="h-3 w-3 bg-gray-200 rounded-full animate-pulse"></div>
                                    </div>
                                    <div class="h-3 bg-gray-200 rounded animate-pulse w-1/2 mt-2"></div>
                                </div>
                            @endfor
                        @else
                            @forelse($assignedTaskList as $task)
                                <a href="{{ route('projects.tasks', $task['projectId']) }}" class="block border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <p class="text-lg font-semibold text-gray-900 leading-none">{{ $task['name'] }}</p>
                                        <span class="text-gray-400">◎</span>
                                    </div>
                                    <p class="dash-muted mt-1">{{ $task['projectName'] }} · {{ $task['dueLabel'] }}</p>
                                </a>
                            @empty
                                <div class="text-sm text-gray-400 py-10 text-center">No assigned tasks yet.</div>
                            @endforelse
                        @endif
                    </div>
                    <a href="/tasks" class="block text-center text-md font-medium text-[#587f75] mt-2">Show All</a>
                </section>

                <section class="dash-card p-3 dash-projects flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-lg font-semibold text-gray-900 leading-none">Projects</h2>
                        <span class="text-gray-400">⋯</span>
                    </div>
                    <div class="border-t border-dashed border-gray-300 my-2"></div>
                    <div class="dash-project-grid mt-3">
                        <button type="button" wire:click="$dispatch('open-dashboard-project-create')"
                                class="border border-gray-200 rounded-lg px-3 py-3 text-left hover:bg-gray-50">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex w-8 h-8 rounded-full bg-gray-100 items-center justify-center text-xl text-gray-500">+</span>
                                <span class="text-md font-medium text-gray-900">New Project</span>
                            </div>
                        </button>
                        @if($loading)
                            @for($i = 0; $i < 5; $i++)
                                <div class="border border-gray-200 rounded-lg px-3 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-md bg-gray-200 animate-pulse"></div>
                                        <div class="min-w-0 flex-1">
                                            <div class="h-4 bg-gray-200 rounded animate-pulse w-3/4"></div>
                                            <div class="h-3 bg-gray-200 rounded animate-pulse w-1/2 mt-2"></div>
                                        </div>
                                    </div>
                                </div>
                            @endfor
                        @else
                            @foreach($projectOverviewList as $proj)
                                @php $initial = strtoupper(substr($proj['name'] ?? 'P', 0, 1)); @endphp
                                <a href="{{ route('projects.tasks', $proj['id']) }}" class="border border-gray-200 rounded-lg px-3 py-3 hover:bg-gray-50">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex w-8 h-8 rounded-md bg-blue-100 text-blue-700 items-center justify-center font-semibold">{{ $initial }}</span>
                                        <div class="min-w-0">
                                            <p class="text-md font-semibold text-gray-900 truncate leading-none">{{ $proj['name'] }}</p>
                                            <p class="dash-muted mt-1">{{ $proj['dueSoon'] }} task due soon</p>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        @endif
                    </div>
                </section>
            </div>

            <div class="dash-panel-row">
                <section class="dash-card p-3 dash-people">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-xl font-semibold text-gray-900 leading-none">People ({{ count($peopleList ?? []) }})</h2>
                        <div class="flex items-center gap-1">
                            <span class="text-xs text-gray-500 border rounded-md px-2 py-1 bg-gray-50">Frequent Collaborators</span>
                            @if($isAdmin)
                                <button type="button"
                                        class="btn btn-xs clr-bg-primary text-base-100 p-4"
                                        onclick="window.location.href='/user-management'">+</button>
                            @endif
                        </div>
                    </div>
                    <div class="border-t border-dashed border-gray-300 my-2"></div>
                    <div class="dash-people-grid mt-3">
                        @if($loading)
                            @for($i = 0; $i < 6; $i++)
                                <div class="border border-gray-200 rounded-lg p-3 text-center">
                                    <div class="mx-auto w-10 h-10 rounded-full bg-gray-200 animate-pulse"></div>
                                    <div class="h-4 bg-gray-200 rounded animate-pulse w-3/4 mx-auto mt-3"></div>
                                </div>
                            @endfor
                        @else
                        @forelse($peopleList as $person)
                            @php
                                $parts = preg_split('/\s+/', trim((string) ($person['name'] ?? 'U')));
                                $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));
                            @endphp
                            <div class="relative border border-gray-200 rounded-lg p-3 text-center"
                                 x-data="{ open:false }"
                                 @mouseenter="open=true" @mouseleave="open=false">
                                <div class="mx-auto w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 text-sm font-semibold flex items-center justify-center overflow-hidden">
                                    @if(!empty($person['profilePicture']))
                                        <img src="{{ $person['profilePicture'] }}" alt="{{ $person['name'] }}"
                                             class="w-full h-full object-cover"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';" />
                                        <span style="display:none;" class="items-center justify-center w-full h-full">{{ $initials }}</span>
                                    @else
                                        {{ $initials }}
                                    @endif
                                </div>
                                <p class="text-md font-medium text-gray-900 truncate mt-2 leading-none">{{ $person['name'] }}</p>
                                <div x-show="open" x-transition class="absolute left-1/2 top-full mt-2 -translate-x-1/2 z-[9999]">
                                    <x-profile-hover-card
                                        :name="$person['name'] ?? ''"
                                        :email="$person['email'] ?? ''"
                                        :specialization="$person['specialization'] ?? ''"
                                        :role="$person['role'] ?? ''"
                                        :avatar-url="$person['profilePicture'] ?? null"
                                    />
                                </div>
                            </div>
                        @empty
                            <p class="col-span-3 text-sm text-gray-400 py-8 text-center">No collaborators found.</p>
                        @endforelse
                        @endif
                    </div>
                </section>

                <section class="dash-card p-3 dash-notepad flex flex-col"
                         x-data="{
                            notes: [],
                            noteDeleting: null,
                            selectedDateLabel: new Date().toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' }),
                            noteDate(iso) {
                                try { return new Date(iso).toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' }); }
                                catch (_) { return ''; }
                            },
                            noteBorderClass(note) {
                                const palette = ['border-pink-500', 'border-blue-500', 'border-emerald-500', 'border-amber-500'];
                                const seed = Number(note?.id || 0);
                                return palette[Math.abs(seed) % palette.length];
                            },
                            openNewNote() {
                                if (typeof window._calPopOut === 'function') window._calPopOut(null, '');
                            },
                            openViewNote(note) {
                                if (typeof window._calPopOut === 'function') window._calPopOut(note.id, note.content || '');
                            },
                            async deleteNote(id) {
                                if (!confirm('Delete this sticky note?')) return;
                                this.noteDeleting = id;
                                try {
                                    await fetch('/notes/' + id, {
                                        method: 'DELETE',
                                        headers: { 'X-CSRF-TOKEN': window._calCsrf || document.querySelector('meta[name=csrf-token]')?.content || '' },
                                        credentials: 'same-origin'
                                    });
                                    this.notes = this.notes.filter(n => n.id !== id);
                                } finally {
                                    this.noteDeleting = null;
                                }
                            },
                            async refreshNotes() {
                                try {
                                    const r = await fetch('/notes', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                                    const data = await r.json();
                                    this.notes = Array.isArray(data) ? data : [];
                                } catch (_) {}
                            },
                            init() {
                                this.refreshNotes();
                                window.addEventListener('cal-notes-refresh', () => this.refreshNotes());
                            }
                         }"
                         x-init="init()">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900 leading-none mb-2">To-do</h2>
                        <button @click="openNewNote()"
                                class="w-7 h-7 rounded flex items-center justify-center text-gray-500 hover:bg-gray-100 text-xl leading-none transition"
                                title="New sticky note">+</button>
                    </div>
                    <p class="dash-muted" x-text="selectedDateLabel"></p>
                    <div class="border-t border-dashed border-gray-300 my-2"></div>
                    <div class="flex-1 min-h-0 overflow-y-auto">
                        <template x-if="notes.length === 0">
                            <p class="text-xs text-gray-400 text-center py-6">No sticky notes yet.<br>Click + to add one.</p>
                        </template>
                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <template x-for="note in notes" :key="note.id">
                                <div :class="['relative group rounded-lg border-l-4 p-3 shadow-sm cursor-pointer transition hover:shadow-md bg-white text-gray-800 min-h-[96px]', noteBorderClass(note)]"
                                     @click="openViewNote(note)">
                                    <p class="text-sm leading-snug pr-6 whitespace-pre-wrap line-clamp-3 font-normal" x-text="note.content"></p>
                                    <p class="text-xs mt-1.5 text-gray-500" x-text="noteDate(note.updatedAt || note.createdAt)"></p>
                                    <button @click.stop="deleteNote(note.id)"
                                            :disabled="noteDeleting === note.id"
                                            class="absolute inset-y-0 right-0 flex items-center pr-2 opacity-0 group-hover:opacity-100 transition"
                                            title="Delete note">
                                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" class="text-gray-500">
                                            <path d="M18 6L6 18M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <livewire:dashboard-project-create />
    <livewire:dashboard-task-create :projects="$projects" />
    @include('partials.calendar-popout-script')
</div>

