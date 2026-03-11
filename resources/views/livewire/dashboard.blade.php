<div class="w-full mx-auto sm:px-6 lg:px-8" style="height: 80vh;">
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
    @php
    use Carbon\Carbon;
    @endphp
    @if ($user)
    <h1 class="text-xl">Welcome, <strong>{{ $user['name'] ?? $user['Name'] ?? 'User' }} </strong> !</h1>
    @endif
    <span class="text-xs">{{ Carbon::now()->format('l, F j, Y') }}</span>
    </div>

    <div class="flex flex-row justify-between p-4 gap-2 flex-1 min-h-0" style="height: 33rem;">
        {{-- This is the left side --}}
        <div class="flex flex-col w-1/2 min-h-0 border border-gray-200 rounded-lg p-4">
            <h1 class="text-xl font-bold">Projects</h1>

            <a href="/projects" class="flex clr-primary justify-end hover:underline"><span class="text-sm">View All Projects</span></a>

            <div class="flex-1 min-h-0 overflow-y-auto rounded-xl bg-white">
                @forelse($projects as $project)
                    @php
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
                                            @endphp
                                            <tr class="border-b border-gray-200 last:border-b-0">
                                                <td class="py-1">{{ $taskName }}</td>
                                                <td class="py-1">{{ $status }}</td>
                                                <td class="py-1 text-xs text-gray-500">{{ $due }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="pb-3 text-xs text-gray-400">
                                No tasks yet.
                            </div>
                        @endif
                    </details>
                @empty
                    <div class="flex-1 flex items-center justify-center text-sm text-gray-400">
                        No projects found.
                    </div>
                @endforelse
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

            <div class="flex flex-col justify-between items-center gap-6 pl-4 mt-4">
                @php
                    // Match status colors used across the app UI.
                    $statusColors = [
                        'In Progress' => '#3b82f6', // blue
                        'Completed'   => '#22c55e', // green
                        'Not Started' => '#ef4444', // red
                        'For Review'  => '#374151', // dark gray
                    ];
                    $breakdown = $taskStatusSummary['breakdown'] ?? [];
                    $totalTasks = (int) ($taskStatusSummary['totalTasks'] ?? 0);
                    $segments = [];
                    foreach ($breakdown as $row) {
                        $name = $row['statusName'] ?? $row['status'] ?? '';
                        $count = (int) ($row['count'] ?? 0);
                        $pct = (float) ($row['percentage'] ?? 0);
                        $segments[] = [
                            'label'  => $name,
                            'value'  => $count,
                            'color'  => $statusColors[$name] ?? '#9ca3af',
                        ];
                    }
                    $total = array_sum(array_column($segments, 'value')) ?: 1;
                    $start = 0.0;
                    $stops = [];
                    foreach ($segments as $seg) {
                        $pct = ((float) $seg['value'] / (float) $total) * 100.0;
                        $end = $start + $pct;
                        $stops[] = $seg['color'] . ' ' . rtrim(rtrim(number_format($start, 4, '.', ''), '0'), '.') . '% '
                            . rtrim(rtrim(number_format($end, 4, '.', ''), '0'), '.') . '%';
                        $start = $end;
                    }
                    $gradient = !empty($stops) ? 'conic-gradient(' . implode(', ', $stops) . ')' : 'none';
                @endphp

                <div
                    class="w-72 h-72 rounded-full border border-gray-200"
                    style="background-color:#ffffff;background-image:{{ $gradient }};background-repeat:no-repeat;background-size:100% 100%;"
                ></div>

                <div class="flex flex-row flex-wrap justify-content gap-4 text-sm">
                    @foreach($segments as $seg)
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full" style="background:{{ $seg['color'] }};"></span>
                            <span class="font-medium">{{ $seg['label'] }}</span>
                            <span class="text-gray-500">({{ $seg['value'] }})</span>
                        </div>
                    @endforeach
                    @if(empty($segments) && $totalTasks === 0)
                        <span class="text-gray-400 text-sm">No task data</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

