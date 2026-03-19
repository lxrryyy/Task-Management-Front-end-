<div x-data="auditLogsClient(@js($allLogs ?? []), 25)">
    <div class="flex w-full items-center clr-primary">
        <a href="/dashboard" class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('projects') ? 'clr-primary' : '' }} hover-clr-accent">
            <x-icons.back-btn classes="w-6 h-6" />
        </a>
        <span class="group-hover:block text-xl">Audit Logs</span>
    </div>
    <hr class="border-2 border-gray-300" />

    <div class="flex flex-row justify-between mt-4">
        <div class="flex flex-row gap-4">
            <button @click="open = !open" class="btn border-2 border-gray clr-primary text-base-100 p-4 hover-clr-bg-primary hover:text-base-100">
                <x-icons.sort class="w-4 h-4 inline-block" /> Filter
            </button>
            <label class="input focus-within:outline-none bg-transparent focus-within:border-base-300 flex-1">
                <input x-model.debounce.300ms="filters.search" class="w-40 bg-transparent focus:outline-none rounded-lg" type="search" placeholder="Search" />
            </label>
        </div>
        <div class="flex flex-row gap-4">
            <button class="btn clr-bg-primary text-base-100 rounded-lg p-4"><x-icons.export classes="w-6 h-6" /> Export</button>
        </div>
    </div>

    <div x-show="open" x-transition class="flex flex-row gap-4 border border-gray-300 rounded-lg p-4 mt-2 bg-white">
        <div class="flex flex-col flex-1">
            <span>User</span>
            <input
                x-model.debounce.200ms="filters.userText"
                type="text"
                placeholder="Search user..."
                class="input input-sm input-bordered w-full bg-white text-gray-900 mb-2"
            />
            <select x-model="filters.userId" class="select select-bordered w-full bg-white text-gray-900">
                <option value="">All User</option>
                <template x-for="u in userOptions" :key="u.id">
                    <option :value="String(u.id)" x-text="u.name"></option>
                </template>
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Role</span>
            <input
                x-model.debounce.200ms="filters.roleText"
                type="text"
                placeholder="Search role..."
                class="input input-sm input-bordered w-full bg-white text-gray-900 mb-2"
            />
            <select x-model="filters.role" class="select select-bordered w-full bg-white text-gray-900">
                <option value="">All roles</option>
                <template x-for="r in roleOptions" :key="r">
                    <option :value="r" x-text="r"></option>
                </template>
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Project Name</span>
            <input
                x-model.debounce.200ms="filters.projectText"
                type="text"
                placeholder="Search project..."
                class="input input-sm input-bordered w-full bg-white text-gray-900 mb-2"
            />
            <select x-model="filters.project" class="select select-bordered w-full bg-white text-gray-900">
                <option value="">All projects</option>
                <template x-for="p in projectOptions" :key="p">
                    <option :value="p" x-text="p"></option>
                </template>
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Action</span>
            <select x-model="filters.action" class="select select-bordered w-full bg-white text-gray-900">
                <option value="">All actions</option>
                <template x-for="a in actionOptions" :key="a">
                    <option :value="a" x-text="a"></option>
                </template>
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Status</span>
            <select x-model="filters.status" class="select select-bordered w-full bg-white text-gray-900">
                <option value="">All statuses</option>
                <template x-for="s in statusOptions" :key="s">
                    <option :value="s" x-text="s"></option>
                </template>
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Date From</span>
            <input x-model="filters.from" type="date" class="input input-sm input-bordered w-full bg-white text-gray-900" />
        </div>
        <div class="flex flex-col flex-1">
            <span>Date To</span>
            <input x-model="filters.to" type="date" class="input input-sm input-bordered w-full bg-white text-gray-900" />
        </div>
    </div>

    @if($loadError)
        <div class="mt-3 alert alert-error text-sm flex items-center gap-2 py-2 px-4 rounded-lg">
            <span>{{ $loadError }}</span>
        </div>
    @endif

    <div class="mt-4 border border-gray-200 rounded-lg bg-white overflow-hidden">
        <div wire:loading class="p-4 text-sm text-gray-500">Loading audit logs…</div>

        <div class="overflow-x-auto overflow-y-auto max-h-[520px]" wire:loading.remove>
        <table class="table w-full">
            <thead class="bg-gray-50 sticky top-0 z-10">
            <tr class="text-gray-500 text-xs uppercase tracking-wide">
                <th class="!font-normal">
                    <div class="flex items-center gap-1">
                        <span>User</span>
                        <span class="text-gray-400">↓</span>
                    </div>
                </th>
                <th class="!font-normal">Role</th>
                <th class="!font-normal">Project Name</th>
                <th class="!font-normal">Action</th>
                <th class="!font-normal">Description</th>
                <th class="!font-normal whitespace-nowrap">Date &amp; Time</th>
            </tr>
            </thead>
            <tbody>
            <template x-for="l in pageItems" :key="l._k">
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-4">
                        <div class="flex flex-col leading-tight">
                            <span class="font-medium text-gray-900" x-text="l.userName"></span>
                            <template x-if="l.userEmail">
                                <span class="text-xs text-gray-500" x-text="l.userEmail"></span>
                            </template>
                        </div>
                    </td>
                    <td class="py-4 text-gray-700 whitespace-nowrap" x-text="l.userRole || '—'"></td>
                    <td class="py-4 text-gray-700" x-text="l.projectName || '—'"></td>
                    <td class="py-4 whitespace-nowrap">
                        <template x-if="l.action">
                            <span class="px-2 py-1 rounded text-[10px] font-semibold"
                                  :style="l.actionStyle"
                                  x-text="l.action"></span>
                        </template>
                        <template x-if="!l.action">
                            <span>—</span>
                        </template>
                    </td>
                    <td class="py-4 text-gray-700 max-w-[520px] whitespace-pre-wrap break-words" x-text="l.description || '—'"></td>
                    <td class="py-4 text-gray-700 whitespace-nowrap">
                        <div class="flex flex-col leading-tight">
                            <span x-text="l.dateLabel"></span>
                            <template x-if="l.timeLabel">
                                <span class="text-xs text-gray-500" x-text="l.timeLabel"></span>
                            </template>
                        </div>
                    </td>
                </tr>
            </template>
            <tr x-show="pageItems.length === 0">
                <td colspan="6" class="text-center py-10 text-gray-500">No audit logs found.</td>
            </tr>
            </tbody>
        </table>
        </div>

        {{-- Pagination --}}
        <div class="flex items-center justify-between px-4 py-3 border-t border-gray-200">
            <div class="text-xs text-gray-500">
                Showing <span x-text="from"></span>-<span x-text="to"></span> of <span x-text="total"></span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button"
                        class="btn btn-sm border border-gray-300 bg-white text-gray-700 p-4"
                        @click="prevPage()"
                        :disabled="page <= 1">
                    Prev
                </button>
                <span class="text-xs text-gray-600">
                    Page <span x-text="page"></span> / <span x-text="totalPages"></span>
                </span>
                <button type="button"
                        class="btn btn-sm border border-gray-300 bg-white text-gray-700 p-4"
                        @click="nextPage()"
                        :disabled="page >= totalPages">
                    Next
                </button>
            </div>
        </div>
    </div>

    <script>
        function auditLogsClient(logs, pageSize) {
            const actionStyle = (action) => ({
                // DELETE - FEE2E2 (TEXT - 7F1D1D)
                'DELETE':  'background:#FEE2E2;color:#7F1D1D;',
                'DELETED': 'background:#FEE2E2;color:#7F1D1D;',
                // PATCH - C11574 (TEXT - FFFFFF)
                'PATCH':   'background:#C11574;color:#FFFFFF;',
                // POST - EEF4FF (TEXT - 3538CD)
                'POST':    'background:#EEF4FF;color:#3538CD;',
                // RESTORE - F2F4F7 (TEXT - 344054)
                'RESTORE': 'background:#F2F4F7;color:#344054;',
            })[action] || 'background:#F2F4F7;color:#344054;';

            const fmtDateTime = (at) => {
                const s = (at || '').toString().trim();
                if (!s) return { dateLabel: '—', timeLabel: '' };
                const d = new Date(s);
                if (isNaN(d.getTime())) return { dateLabel: s, timeLabel: '' };
                return {
                    dateLabel: d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: '2-digit' }),
                    timeLabel: d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }).replace(' ', '').toLowerCase(),
                };
            };

            const rows = Array.isArray(logs) ? logs.map((raw, idx) => {
                const uid = parseInt(raw.accountId ?? 0) || 0;
                const action = (raw.action || '').toString().trim().toUpperCase();
                const at = (raw.at || raw.createdAt || '').toString().trim();
                const dt = fmtDateTime(at);
                return {
                    _k: String(raw.id ?? idx) + ':' + idx,
                    uid,
                    userName: (raw.userName || (uid ? `User #${uid}` : '—')).toString().trim(),
                    userEmail: (raw.userEmail || '').toString().trim(),
                    userRole: (raw.userRole || '').toString().trim(),
                    projectName: (raw.projectName || '').toString().trim(),
                    action,
                    actionStyle: actionStyle(action),
                    description: (raw.message || raw.entity || '').toString().trim(),
                    status: (raw.status || '').toString().trim(),
                    at,
                    ...dt,
                };
            }) : [];

            return {
                open: false,
                pageSize: pageSize || 25,
                page: 1,
                rows,
                filters: {
                    search: '',
                    userText: '',
                    userId: '',
                    roleText: '',
                    role: '',
                    projectText: '',
                    project: '',
                    action: '',
                    status: '',
                    from: '',
                    to: '',
                },
                get actionOptions() {
                    const s = new Set(this.rows.map(r => r.action).filter(Boolean));
                    return Array.from(s).sort();
                },
                get roleOptions() {
                    const s = new Set(this.rows.map(r => r.userRole).filter(Boolean));
                    return Array.from(s).sort();
                },
                get projectOptions() {
                    const s = new Set(this.rows.map(r => r.projectName).filter(Boolean));
                    return Array.from(s).sort();
                },
                get statusOptions() {
                    const s = new Set(this.rows.map(r => r.status).filter(Boolean));
                    return Array.from(s).sort();
                },
                get userOptions() {
                    const seen = new Set();
                    const opts = [];
                    for (const r of this.rows) {
                        if (!r.uid) continue;
                        if (seen.has(r.uid)) continue;
                        seen.add(r.uid);
                        opts.push({ id: r.uid, name: r.userName || `User #${r.uid}` });
                    }
                    return opts.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                },
                matches(r) {
                    const f = this.filters;
                    const search = (f.search || '').trim().toLowerCase();
                    const userText = (f.userText || '').trim().toLowerCase();
                    const roleText = (f.roleText || '').trim().toLowerCase();
                    const projectText = (f.projectText || '').trim().toLowerCase();
                    const userId = (f.userId || '').trim();
                    const role = (f.role || '').trim().toLowerCase();
                    const project = (f.project || '').trim().toLowerCase();
                    const action = (f.action || '').trim().toUpperCase();
                    const status = (f.status || '').trim().toLowerCase();
                    const from = (f.from || '').trim();
                    const to = (f.to || '').trim();

                    if (search) {
                        const hay = [r.userName, r.userEmail, r.userRole, r.projectName, r.action, r.description].join(' ').toLowerCase();
                        if (!hay.includes(search)) return false;
                    }
                    if (userText) {
                        const hay = (r.userName + ' ' + (r.userEmail || '')).toLowerCase();
                        if (!hay.includes(userText)) return false;
                    }
                    if (userId) {
                        if (String(r.uid) !== userId) return false;
                    }
                    if (roleText) {
                        if (!(r.userRole || '').toLowerCase().includes(roleText)) return false;
                    }
                    if (role) {
                        if ((r.userRole || '').toLowerCase() !== role) return false;
                    }
                    if (projectText) {
                        if (!(r.projectName || '').toLowerCase().includes(projectText)) return false;
                    }
                    if (project) {
                        if ((r.projectName || '').toLowerCase() !== project) return false;
                    }
                    if (action) {
                        if ((r.action || '') !== action) return false;
                    }
                    if (status) {
                        if ((r.status || '').toLowerCase() !== status) return false;
                    }
                    if (from || to) {
                        const d = r.at ? new Date(r.at) : null;
                        if (!d || isNaN(d.getTime())) return false;
                        if (from) {
                            const fd = new Date(from + 'T00:00:00');
                            if (d < fd) return false;
                        }
                        if (to) {
                            const td = new Date(to + 'T23:59:59');
                            if (d > td) return false;
                        }
                    }
                    return true;
                },
                get filteredRows() {
                    const out = this.rows.filter(r => this.matches(r));
                    // reset page if out of range
                    const tp = Math.max(1, Math.ceil(out.length / this.pageSize));
                    if (this.page > tp) this.page = tp;
                    if (this.page < 1) this.page = 1;
                    return out;
                },
                get total() { return this.filteredRows.length; },
                get totalPages() { return Math.max(1, Math.ceil(this.total / this.pageSize)); },
                get from() { return this.total ? ((this.page - 1) * this.pageSize + 1) : 0; },
                get to() { return this.total ? Math.min(this.page * this.pageSize, this.total) : 0; },
                get pageItems() {
                    const start = (this.page - 1) * this.pageSize;
                    return this.filteredRows.slice(start, start + this.pageSize);
                },
                nextPage() { if (this.page < this.totalPages) this.page++; },
                prevPage() { if (this.page > 1) this.page--; },
            };
        }
    </script>
</div>
