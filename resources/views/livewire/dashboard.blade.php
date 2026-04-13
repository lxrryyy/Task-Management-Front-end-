<div class="w-full mx-auto sm:px-6 lg:px-8" style="height: 100vh;">
    <style>
        details .details-caret {
            transform: rotate(-90deg);
            transition: transform 150ms ease;
            display: inline-flex;
            align-items: center;
        }

        details[open] .details-caret {
            transform: rotate(0deg);
        }

        .status-dropdown .status-caret {
            transform: rotate(-90deg);
            transition: transform 150ms ease;
            display: inline-flex;
            align-items: center;
        }

        .status-dropdown:focus-within .status-caret {
            transform: rotate(0deg);
        }
    </style>

    <div class="flex flex-col mt-4 gap-2">
        @if (session('success'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition.opacity.duration.300ms
                class="alert alert-success text-sm flex items-center gap-2 py-2 px-4 rounded-lg">
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @php
        use Carbon\Carbon;
        @endphp
        <span class="clr-txt-secondary text-xl font-bold">{{ Carbon::now()->format('l, F j, Y') }}</span>
        <div class="flex flex-row gap-4 w-full h-32">
            @if($isAdmin)
                @foreach(($adminSummaryCards ?? []) as $idx => $card)
                    <div class="flex flex-1 border border-gray-200 rounded-lg bg-white h-24 px-4 py-3 items-center gap-3">
                        <div class="shrink-0">
                            @if(!empty($card['icon']))
                                <x-dynamic-component :component="$card['icon']" classes="w-8 h-8" />
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 leading-tight">
                            <p class="text-sm font-medium text-gray-700 truncate">{{ $card['label'] ?? 'Card' }}</p>
                            <h1 class="text-3xl font-bold clr-primary mt-0.5"
                                wire:key="admin-card-{{ $idx }}-{{ (int) ($card['value'] ?? 0) }}"
                                x-data="countUpNumber({{ (int) ($card['value'] ?? 0) }}, {{ 650 + ($idx * 50) }})"
                                x-init="start()"
                                x-text="display"></h1>
                            <p class="text-[10px] text-gray-500 mt-0.5 truncate">{{ $card['sub'] ?? '' }}</p>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="flex flex-col flex-start flex-1 border rounded-lg p-4">
                    <span class="text-lg font-medium">
                        My Projects
                    </span>
                    <h1 class="text-3xl font-bold"
                        wire:key="card-projects-{{ (int) ($summaryCards['projects'] ?? 0) }}"
                        x-data="countUpNumber({{ (int) ($summaryCards['projects'] ?? 0) }}, 650)"
                        x-init="start()"
                        x-text="display"></h1>
                    <label for="">Active</label>
                </div>
                <div class="flex flex-col flex-start flex-1 border rounded-lg p-4">
                    <span class="text-lg font-medium">
                        Tasks
                    </span>
                    <h1 class="text-3xl font-bold"
                        wire:key="card-tasks-{{ (int) ($summaryCards['tasks'] ?? 0) }}"
                        x-data="countUpNumber({{ (int) ($summaryCards['tasks'] ?? 0) }}, 700)"
                        x-init="start()"
                        x-text="display"></h1>
                    <label for="">Assigned to me</label>
                </div>
                <div class="flex flex-col flex-start flex-1 border rounded-lg p-4">
                    <span class="text-lg font-medium">
                        For Review
                    </span>
                    <h1 class="text-3xl font-bold"
                        wire:key="card-review-{{ (int) ($summaryCards['forReview'] ?? 0) }}"
                        x-data="countUpNumber({{ (int) ($summaryCards['forReview'] ?? 0) }}, 750)"
                        x-init="start()"
                        x-text="display"></h1>
                    <label for="">Awaiting Review</label>
                </div>
                <div class="flex flex-col flex-start flex-1 border rounded-lg p-4">
                    <span class="text-lg font-medium">
                        Completed
                    </span>
                    <h1 class="text-3xl font-bold text-green-600"
                        wire:key="card-completed-{{ (int) ($summaryCards['completed'] ?? 0) }}"
                        x-data="countUpNumber({{ (int) ($summaryCards['completed'] ?? 0) }}, 800)"
                        x-init="start()"
                        x-text="display"></h1>
                    <label for="">Active</label>
                </div>
            @endif
        </div>
    </div>

    <div class="flex flex-row justify-between p-4 gap-2 flex-1 min-h-0" style="height: 33rem;">
        {{-- This is the left side --}}
        <div class="flex flex-col w-1/2 min-h-0 border border-gray-200 rounded-lg p-4">
            <h1 class="text-xl font-bold">Projects</h1>
            <a href="/projects" class="flex clr-primary justify-end hover:underline"><span class="text-sm">View All Projects</span></a>

            <div class="flex-1 min-h-0 overflow-y-auto rounded-xl bg-white">
                @if($loading)
                @foreach(range(1, 6) as $i)
                <div class="flex items-center justify-between py-3 px-2 border-b border-gray-100">
                    <div class="h-4 bg-gray-200 rounded animate-pulse w-40"></div>
                    <div class="h-4 bg-gray-200 rounded animate-pulse w-12"></div>
                </div>
                @endforeach
                @else
                @forelse($projects as $project)
                @php
                $pid = (int) ($project['id'] ?? $project['Id'] ?? 0);
                $projectName = $project['name'] ?? $project['Name'] ?? $project['title'] ?? 'Project';
                $tasks = $project['tasks'] ?? $project['Tasks'] ?? [];
                @endphp
                <details class="group border-b border-gray-100 last:border-b-0">
                    <summary class="flex items-center justify-between py-2 cursor-pointer select-none">
                        <span class="inline-flex items-center gap-2 font-medium text-gray-900">
                            <span class="details-caret">
                                <x-icons.dropdown classes="w-3 h-3 text-gray-500" />
                            </span>
                            {{ $projectName }}
                        </span>
                        <span class="text-xs text-gray-500">
                            {{ is_countable($tasks) ? count($tasks) : 0 }} tasks
                        </span>
                    </summary>

                    @if(!empty($tasks))
                    <div class="pb-3">
                        <table class="table table-zebra w-full text-sm">
                            <thead>
                                <tr class="text-gray-500">
                                    <th class="font-semibold">Task</th>
                                    <th class="font-semibold">Status</th>
                                    <th class="font-semibold">Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tasks as $task)
                                @php
                                $taskName = $task['title'] ?? $task['name'] ?? 'Task';
                                $status = $task['statusName'] ?? $task['status'] ?? '';
                                $dueRaw = $task['dueDate'] ?? $task['dueAt'] ?? null;
                                $due = $dueRaw ? \Carbon\Carbon::parse($dueRaw)->format('Y-m-d') : '—';
                                $statusPillStyle = match($status) {
                                'Not Started' => 'background:#fee2e2;color:#ef4444;',
                                'In Progress' => 'background:#dbeafe;color:#3b82f6;',
                                'For Review' => 'background:#e5e7eb;color:#374151;',
                                'Completed' => 'background:#dcfce7;color:#22c55e;',
                                default => 'background:#f3f4f6;color:#374151;',
                                };
                                @endphp
                                <tr class="border-b border-gray-200 last:border-b-0 hover:bg-gray-50 cursor-pointer"
                                    onclick="window.location.href='{{ route('projects.tasks', $pid) }}'">
                                    <td class="py-1">
                                        <a href="{{ route('projects.tasks', $pid) }}" class="hover:underline">
                                            {{ $taskName }}
                                        </a>
                                    </td>
                                    <td class="py-1">
                                        <span class="px-2 py-0.5 text-xs rounded" style="{{ $statusPillStyle }}">
                                            {{ $status }}
                                        </span>
                                    </td>
                                    <td class="py-1 text-xs text-gray-500">{{ $due }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="pb-3 text-xs text-gray-400">No tasks yet.</div>
                    @endif
                </details>
                @empty
                <div class="flex-1 flex items-center justify-center text-sm text-gray-400">
                    No projects found.
                </div>
                @endforelse
                @endif
            </div>

            <div class="flex flex-row justify-center gap-4 mt-auto pt-4 shrink-0">
                <button type="button" class="btn w-1/2 clr-bg-primary text-base-100"
                    wire:click="$dispatch('open-dashboard-project-create')">
                    Add Project
                </button>
                <button type="button" class="btn w-1/2 clr-bg-primary text-base-100"
                    wire:click="$dispatch('open-dashboard-task-create')">
                    Add Task
                </button>
            </div>
        </div>

        <livewire:dashboard-project-create />
        <livewire:dashboard-task-create :projects="$projects" />

        {{-- This is the right side --}}
        <div class="flex flex-col w-1/2 border border-gray-200 rounded-lg p-4">
            <h1 class="text-xl font-bold pl-4">Task by Status</h1>

            @if($loading)
            <div class="flex flex-col items-center gap-6 mt-4">
                <div class="w-72 h-72 rounded-full bg-gray-200 animate-pulse"></div>
                <div class="flex flex-row gap-4">
                    @foreach(range(1, 4) as $i)
                    <div class="h-4 bg-gray-200 rounded animate-pulse w-20"></div>
                    @endforeach
                </div>
            </div>
            @else
            <div
                class="flex flex-col items-center gap-6 pl-4 mt-4 w-full"
                wire:key="task-status-chart-{{ (int) $selectedProjectId }}-{{ (int) ($taskStatusSummary['totalTasks'] ?? 0) }}"
                x-data="taskStatusChart(@js($taskStatusSummary['breakdown'] ?? []))"
                x-init="init()"
            >
                <div class="w-full flex justify-end items-center gap-2">
                    <div class="dropdown dropdown-end status-dropdown">
                        <button tabindex="0" type="button" class="btn clr-bg-primary text-base-100 btn-sm px-2 flex items-center gap-2">
                            <span class="status-caret">
                                <x-icons.dropdown />
                            </span>
                            <span x-text="chartTypeLabel"></span>
                        </button>
                        <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-50 w-40 p-2 shadow-lg border absolute right-0 top-full mt-2">
                            <li>
                                <button type="button" @click="setChartType('doughnut')">Donut</button>
                            </li>
                            <li>
                                <button type="button" @click="setChartType('pie')">Pie</button>
                            </li>
                        </ul>
                    </div>

                    <div class="dropdown dropdown-end self-end flex justify-end relative status-dropdown">
                        <button tabindex="0" type="button" class="btn clr-bg-primary text-base-100 btn-sm px-2 flex items-center gap-2">
                            <span class="status-caret">
                                <x-icons.dropdown />
                            </span>
                            <span>
                                @php
                                $selectedName = 'Project';
                                foreach ($projects as $p) {
                                $pid = (int) ($p['id'] ?? $p['Id'] ?? 0);
                                if ($pid === (int) $selectedProjectId) {
                                $selectedName = $p['name'] ?? $p['Name'] ?? $p['title'] ?? 'Project';
                                break;
                                }
                                }
                                @endphp
                                {{ $selectedName }}
                            </span>
                        </button>
                        <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-50 w-52 p-2 shadow-lg border absolute right-0 top-full mt-2">
                            @foreach($projects as $project)
                            @php
                            $projId = (int) ($project['id'] ?? $project['Id'] ?? 0);
                            $projName = $project['name'] ?? $project['Name'] ?? $project['title'] ?? 'Project';
                            @endphp
                            <li>
                                <button type="button"
                                    wire:click="$set('selectedProjectId', {{ $projId }})"
                                    @click="$nextTick(() => $el.closest('.dropdown')?.querySelector('button')?.blur())"
                                    class="{{ (int) $selectedProjectId === $projId ? 'active' : '' }}">
                                    {{ $projName }}
                                </button>
                            </li>
                            @endforeach
                            @if(empty($projects))
                            <li><span class="text-gray-400 text-sm">No projects</span></li>
                            @endif
                        </ul>
                    </div>
                </div>

                <div class="w-72 h-72 relative">
                    <canvas x-ref="canvas" class="w-full h-full"></canvas>
                </div>

                <div class="flex flex-row flex-wrap justify-content gap-4 text-sm">
                    <template x-for="seg in segments" :key="seg.label">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full" :style="`background:${seg.color};`"></span>
                            <span class="font-medium" x-text="seg.label"></span>
                            <span class="text-gray-500" x-text="`(${seg.value})`"></span>
                        </div>
                    </template>
                    <span x-show="segments.length === 0" class="text-gray-400 text-sm">No task data</span>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@once
    <script>
        function taskStatusChart(breakdown) {
            const palette = {
                'Completed': '#16A34A',
                'For Review': '#F59E0B',
                'In Progress': '#2563EB',
                'Not Started': '#EF4444',
            };

            return {
                chart: null,
                chartType: 'doughnut',
                segments: [],
                _layoutObserver: null,
                get chartTypeLabel() {
                    return this.chartType === 'doughnut' ? 'Donut' : 'Pie';
                },
                init() {
                    const rows = Array.isArray(breakdown) ? breakdown : [];
                    this.segments = rows.map((row) => {
                        const label = (row.statusName || row.status || '').toString().trim();
                        const value = parseInt(row.count || 0, 10) || 0;
                        return {
                            label,
                            value,
                            color: palette[label] || '#9ca3af',
                        };
                    });
                    this.$nextTick(() => this.scheduleRender());
                },
                scheduleRender() {
                    let attempts = 0;
                    const tryRender = () => {
                        attempts++;
                        const canvas = this.$refs.canvas;
                        const ready =
                            window.Chart &&
                            canvas &&
                            canvas.clientWidth > 0 &&
                            canvas.clientHeight > 0;
                        if (ready) {
                            this.renderChart();
                            this.attachLayoutObserver();
                            return;
                        }
                        if (attempts < 120) {
                            requestAnimationFrame(tryRender);
                        } else {
                            this.renderChart();
                        }
                    };
                    requestAnimationFrame(tryRender);
                },
                attachLayoutObserver() {
                    if (this._layoutObserver || typeof ResizeObserver === 'undefined') return;
                    const el = this.$refs.canvas?.parentElement;
                    if (!el) return;
                    this._layoutObserver = new ResizeObserver(() => {
                        if (this.chart) {
                            requestAnimationFrame(() => this.chart.resize());
                        }
                    });
                    this._layoutObserver.observe(el);
                },
                setChartType(type) {
                    if (this.chartType === type) return;
                    this.chartType = type;
                    this.$nextTick(() => this.scheduleRender());
                },
                renderChart() {
                    if (!window.Chart || !this.$refs.canvas) return;
                    if (this.chart) {
                        this.chart.destroy();
                        this.chart = null;
                    }

                    const labels = this.segments.map(s => s.label);
                    const values = this.segments.map(s => s.value);
                    const colors = this.segments.map(s => s.color);

                    const centerTextPlugin = {
                        id: 'centerTextPlugin',
                        afterDraw: (chart) => {
                            if (this.chartType !== 'doughnut') return;
                            const meta = chart.getDatasetMeta(0);
                            if (!meta || !meta.data || !meta.data.length) return;
                            const x = meta.data[0].x;
                            const y = meta.data[0].y;
                            const ctx = chart.ctx;
                            const dataArr = chart.data?.datasets?.[0]?.data ?? [];
                            const total = Array.isArray(dataArr)
                                ? dataArr.reduce((sum, v) => sum + (Number(v) || 0), 0)
                                : 0;
                            ctx.save();
                            ctx.textAlign = 'center';
                            ctx.fillStyle = '#111827';
                            ctx.font = '700 28px Ubuntu, sans-serif';
                            ctx.fillText(String(total), x, y - 2);
                            ctx.fillStyle = '#6b7280';
                            ctx.font = '500 12px Ubuntu, sans-serif';
                            ctx.fillText('Total', x, y + 16);
                            ctx.restore();
                        }
                    };

                    const percentageLabelPlugin = {
                        id: 'percentageLabelPlugin',
                        afterDatasetsDraw: (chart) => {
                            const meta = chart.getDatasetMeta(0);
                            if (!meta || !meta.data || !meta.data.length) return;

                            const ctx = chart.ctx;
                            const dataArr = chart.data?.datasets?.[0]?.data ?? [];
                            const total = Array.isArray(dataArr)
                                ? dataArr.reduce((sum, v) => sum + (Number(v) || 0), 0)
                                : 0;
                            if (!total) return;

                            ctx.save();
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.font = '600 11px Ubuntu, sans-serif';

                            meta.data.forEach((arc, i) => {
                                const raw = Number(dataArr[i] || 0);
                                if (!raw) return;

                                const pct = (raw / total) * 100;
                                const label = `${Math.round(pct)}%`;

                                const angle = (arc.startAngle + arc.endAngle) / 2;
                                const radius = this.chartType === 'doughnut'
                                    ? (arc.innerRadius + arc.outerRadius) / 2
                                    : arc.outerRadius * 0.62;
                                const x = arc.x + Math.cos(angle) * radius;
                                const y = arc.y + Math.sin(angle) * radius;

                                // White text works across dark slices; slight shadow improves readability.
                                ctx.fillStyle = '#ffffff';
                                ctx.shadowColor = 'rgba(0,0,0,0.35)';
                                ctx.shadowBlur = 2;
                                ctx.fillText(label, x, y);
                                ctx.shadowBlur = 0;
                            });

                            ctx.restore();
                        }
                    };

                    this.chart = new Chart(this.$refs.canvas.getContext('2d'), {
                        type: this.chartType,
                        data: {
                            labels,
                            datasets: [{
                                data: values,
                                backgroundColor: colors,
                                borderColor: '#ffffff',
                                borderWidth: 2,
                                hoverOffset: 2,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: this.chartType === 'doughnut' ? '62%' : '0%',
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.parsed || 0;
                                            return `${label}: ${value}`;
                                        }
                                    }
                                }
                            }
                        },
                        plugins: [centerTextPlugin, percentageLabelPlugin]
                    });
                    requestAnimationFrame(() => {
                        if (this.chart) this.chart.resize();
                    });
                },
                destroy() {
                    if (this._layoutObserver) {
                        this._layoutObserver.disconnect();
                        this._layoutObserver = null;
                    }
                    if (this.chart) {
                        this.chart.destroy();
                        this.chart = null;
                    }
                }
            };
        }
    </script>
@endonce
