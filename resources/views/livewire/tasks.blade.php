<div class="flex flex-col gap-4">
    <div class="flex justify-between">
        <div class="flex gap-2">
            <button class="btn clr-bg-primary text-base-100 p-4"><x-icons.list class="w-4 h-4 inline-block" /> List</button>
            <button class="btn border-2 border-gray-400 clr-primary p-4 hover-clr-bg-primary hover:text-base-100 hover:border-none"><x-icons.board class="w-4 h-4 inline-block" /> Board View</button>
        </div>
        <div class="flex items-center">
            <label class="input focus-within:outline-none bg-transparent focus-within:border-base-300 flex-1">
                <input wire:model.live.debounce.300ms="search" class="w-96 bg-transparent focus:outline-none rounded-xl" type="search" placeholder="Search" />
            </label>
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn w-36 border-2 border-gray rounded-xl m-1 hover-clr-bg-primary hover:text-white "><x-icons.sort class="w-4 h-4 inline-block" /> Filter</button>
                <ul tabindex="-1" class="dropdown-content menu bg-base-100 rounded-box z-50 w-56 p-2 shadow-lg mt-1">
                    <li><a href="#">Alphabetical (A → Z)</a></li>
                    <li><a href="#">Alphabetical (Z → A)</a></li>
                    <li><a href="#">Date (Newest first)</a></li>
                    <li><a href="#">Date (Oldest first)</a></li>
                </ul>
            </div>
            <button wire:click="openAddTaskModal" class="btn clr-bg-primary text-base-100 p-4">+ Add Task</button>
        </div>
    </div>

    {{-- Add Task Modal --}}
    <dialog class="{{ $showAddTaskModal ? 'modal modal-open' : 'modal' }}">
        <div class="modal-box w-11/12 max-w-5xl overflow-y-auto">
            <div class="modal-action mt-0 mb-2">
                <button type="button" wire:click="closeAddTaskModal" class="btn btn-sm">✕</button>
            </div>
            <h3 class="font-bold text-lg">{{ $taskParentId ? 'New Subtask' : 'New Task' }}</h3>

            @if($errors->any())
                <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 mt-3 text-sm text-red-700">
                    <p class="font-semibold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->get('api_error') as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                        @foreach($errors->all() as $msg)
                            @if(!in_array($msg, $errors->get('api_error', [])))
                                <li>{{ $msg }}</li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('tasks.store', $projectId) }}" class="mt-4 flex flex-col gap-4">
                @csrf
                @if($taskParentId)
                    <input type="hidden" name="parentTaskId" value="{{ $taskParentId }}" />
                @endif

                {{-- Task Name --}}
                <div class="flex flex-col gap-1">
                    <label class="font-medium text-sm">Task Name</label>
                    <input
                        name="name"
                        type="text"
                        placeholder="Enter task name"
                        class="input input-bordered w-full {{ $errors->has('name') ? 'border-red-500' : '' }}"
                        value="{{ old('name') }}"
                        required
                    />
                    @foreach($errors->get('name') as $msg)
                        <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
                    @endforeach
                </div>

                {{-- Assignee --}}
                <div class="flex flex-col gap-1">
                    <label class="font-medium text-sm">Assignee</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                            </svg>
                        </span>
                        <select name="assigneeId" class="select select-bordered w-full pl-10">
                            <option value="">— Unassigned —</option>
                            @foreach($accounts as $account)
                                @php
                                    $aid   = $account['id']    ?? $account['Id']    ?? null;
                                    $aname = $account['name']  ?? $account['Name']  ?? 'Unknown';
                                @endphp
                                @if($aid !== null)
                                    <option value="{{ $aid }}" {{ old('assigneeId') == $aid ? 'selected' : '' }}>
                                        {{ $aname }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Priority | Story Point | Start Date | Due Date --}}
                <div class="flex flex-wrap gap-4">
                    <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
                        <label class="font-medium text-sm">Priority</label>
                        <select name="priority" class="select select-bordered w-full">
                            <option value="">Priority</option>
                            <option value="Low"      {{ old('priority') === 'Low'      ? 'selected' : '' }}>Low</option>
                            <option value="Medium"   {{ old('priority') === 'Medium'   ? 'selected' : '' }}>Medium</option>
                            <option value="High"     {{ old('priority') === 'High'     ? 'selected' : '' }}>High</option>
                            <option value="Critical" {{ old('priority') === 'Critical' ? 'selected' : '' }}>Critical</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
                        <label class="font-medium text-sm">Story Point</label>
                        <input
                            name="storyPoints"
                            type="number"
                            min="0"
                            placeholder="0"
                            class="input input-bordered w-full"
                            value="{{ old('storyPoints') }}"
                        />
                    </div>

                    <div class="flex flex-col gap-1 flex-1 min-w-[140px]">
                        <label class="font-medium text-sm">Start Date</label>
                        <input
                            name="startDate"
                            type="date"
                            class="input input-bordered w-full {{ $errors->has('startDate') ? 'border-red-500' : '' }}"
                            value="{{ old('startDate') }}"
                        />
                        @foreach($errors->get('startDate') as $msg)
                            <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
                        @endforeach
                    </div>

                    <div class="flex flex-col gap-1 flex-1 min-w-[140px]">
                        <label class="font-medium text-sm">Due Date</label>
                        <input
                            name="dueDate"
                            type="date"
                            class="input input-bordered w-full {{ $errors->has('dueDate') ? 'border-red-500' : '' }}"
                            value="{{ old('dueDate') }}"
                        />
                        @foreach($errors->get('dueDate') as $msg)
                            <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
                        @endforeach
                    </div>
                </div>

                {{-- Description --}}
                <div class="flex flex-col gap-1">
                    <label class="font-medium text-sm">Description</label>
                    <textarea
                        name="description"
                        class="textarea textarea-bordered w-full h-32"
                        placeholder="Task description"
                    >{{ old('description') }}</textarea>
                </div>

                <div class="modal-action">
                    <button type="submit" class="btn clr-bg-primary text-base-100 px-6">+ Add tasks</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="button" wire:click="closeAddTaskModal">close</button>
        </form>
    </dialog>

    <div class="overflow-x-auto max-h-[500px] relative">
        <table class="table w-full table-fixed">
            <colgroup>
                <col class="w-8"><!-- expand/collapse -->
                <col class="w-10"><!-- checkbox -->
                <col class="w-1/3"><!-- Task Name -->
                <col class="w-1/5"><!-- Assignee -->
                <col class="w-28"><!-- Due Date -->
                <col class="w-24"><!-- Story Point -->
                <col class="w-30"><!-- Status -->
                <col class="w-24"><!-- Priority -->
                <col class="w-20"><!-- Action -->
            </colgroup>
            <thead>
            <tr class="bg-base-200">
                <th class="sticky top-0 z-10 bg-base-200"></th>
                <th class="sticky top-0 z-10 bg-base-200"></th>
                <th class="sticky top-0 z-10 bg-base-200">Task Name</th>
                <th class="sticky top-0 z-10 bg-base-200">Assignee</th>
                <th class="sticky top-0 z-10 bg-base-200">Due Date</th>
                <th class="sticky top-0 z-10 bg-base-200">Story Point</th>
                <th class="sticky top-0 z-10 bg-base-200">Status</th>
                <th class="sticky top-0 z-10 bg-base-200">Priority</th>
                <th class="sticky top-0 z-10 bg-base-200">Action</th>
            </tr>
            </thead>
            <tbody>
            @php
                // Group tasks by parentTaskId (null => parents)
                $byParent = [];
                foreach ($filteredTasks as $task) {
                    $pid = $task['parentTaskId'] ?? $task['parentId'] ?? $task['parentID'] ?? null;
                    $key = $pid ?? '__root__';
                    $byParent[$key][] = $task;
                }

                $parents = $byParent['__root__'] ?? [];

                $fmt = function (array $task) {
                    $taskName    = $task['name'] ?? $task['title'] ?? '—';
                    $assignee    = $task['assigneeName'] ?? $task['assignedToName'] ?? $task['reporterName'] ?? '—';
                    $dueDateRaw  = $task['dueDate'] ?? $task['dueAt'] ?? null;
                    $storyPoints = $task['storyPoints'] ?? $task['storyPoint'] ?? $task['points'] ?? null;
                    $status      = $task['status'] ?? '—';
                    $priority    = $task['priority'] ?? '—';

                    return compact('taskName','assignee','dueDateRaw','storyPoints','status','priority');
                };
            @endphp

            @forelse($parents as $parent)
                @php
                    $p = $fmt($parent);
                    $parentId = $parent['id'] ?? null;
                    $children = $parentId !== null ? ($byParent[$parentId] ?? []) : [];
                    $hasChildren = !empty($children);
                    $isExpanded = $parentId !== null && ($expanded[$parentId] ?? false);
                @endphp
                <!-- Parent task -->
                <tr class="hover:bg-gray-50">
                    <td>
                        @if($parentId !== null)
                            <button
                                type="button"
                                class="btn btn-ghost btn-xs"
                                wire:click="toggle({{ $parentId }})"
                            >
                                {{ $isExpanded ? '▾' : '▸' }}
                            </button>
                        @endif
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" class="checkbox" />
                        </label>
                    </td>
                    <td>
                        <span class="font-semibold">{{ $p['taskName'] }}</span>
                    </td>
                    <td>{{ $p['assignee'] }}</td>
                    <td>
                        @if($p['dueDateRaw'])
                            {{ \Carbon\Carbon::parse($p['dueDateRaw'])->format('Y-m-d') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $p['storyPoints'] ?? '—' }}</td>
                    <td>
                        <span class="badge badge-success w-30">{{ $p['status'] }}</span>
                    </td>
                    <td>
                        <span class="badge badge-warning">{{ $p['priority'] }}</span>
                    </td>
                    <td>
                        <button class="btn btn-ghost btn-xs">details</button>
                    </td>
                </tr>

                @if($isExpanded && $parentId !== null)
                    @foreach($children as $child)
                        @php
                            $c = $fmt($child);
                            $childId = $child['id'] ?? null;
                            $grandChildren = $childId !== null ? ($byParent[$childId] ?? []) : [];
                            $childHasChildren = !empty($grandChildren);
                            $childExpanded = $childId !== null && ($expanded[$childId] ?? false);
                        @endphp
                        <!-- Subtask row -->
                        <tr class="hover:bg-gray-50">
                            <td>
                                @if($childId !== null)
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-xs"
                                        wire:click="toggle({{ $childId }})"
                                    >
                                        {{ $childExpanded ? '▾' : '▸' }}
                                    </button>
                                @endif
                            </td>
                            <td>
                                <label>
                                    <input type="checkbox" class="checkbox checkbox-xs" />
                                </label>
                            </td>
                            <td class="pl-10">
                                <span class="text-sm">{{ $c['taskName'] }}</span>
                            </td>
                            <td>{{ $c['assignee'] }}</td>
                            <td>
                                @if($c['dueDateRaw'])
                                    {{ \Carbon\Carbon::parse($c['dueDateRaw'])->format('Y-m-d') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $c['storyPoints'] ?? '—' }}</td>
                            <td>
                                <span class="badge badge-success badge-sm">{{ $c['status'] }}</span>
                            </td>
                            <td>
                                <span class="badge badge-ghost badge-sm">{{ $c['priority'] }}</span>
                            </td>
                            <td>
                                <button class="btn btn-ghost btn-xs">Edit</button>
                            </td>
                        </tr>

                        @if($childExpanded && $childId !== null)
                            @foreach($grandChildren as $gc)
                                @php($g = $fmt($gc))
                                <!-- Grandchild task rows -->
                                <tr class="hover:bg-gray-50">
                                    <td></td>
                                    <td>
                                        <label>
                                            <input type="checkbox" class="checkbox checkbox-xs" />
                                        </label>
                                    </td>
                                    <td class="pl-16 text-xs">
                                        {{ $g['taskName'] }}
                                    </td>
                                    <td>{{ $g['assignee'] }}</td>
                                    <td>
                                        @if($g['dueDateRaw'])
                                            {{ \Carbon\Carbon::parse($g['dueDateRaw'])->format('Y-m-d') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $g['storyPoints'] ?? '—' }}</td>
                                    <td>
                                        <span class="badge badge-ghost badge-sm">{{ $g['status'] }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-ghost badge-sm">{{ $g['priority'] }}</span>
                                    </td>
                                    <td>
                                        <button class="btn btn-ghost btn-xs">Edit</button>
                                    </td>
                                </tr>
                            @endforeach
                            {{-- Always show "+ Add subtask" at the bottom of expanded subtask --}}
                            <tr class="hover:bg-blue-50 cursor-pointer"
                                wire:click="addSubtask({{ $childId }})">
                                <td></td>
                                <td></td>
                                <td class="pl-16">
                                    <span class="text-sm clr-primary font-medium">+ Add subtask</span>
                                </td>
                                <td colspan="6"></td>
                            </tr>
                        @endif
                    @endforeach
                    {{-- Always show "+ Add subtask" at the bottom of expanded parent --}}
                    <tr class="hover:bg-blue-50 cursor-pointer"
                        wire:click="addSubtask({{ $parentId }})">
                        <td></td>
                        <td></td>
                        <td class="pl-10">
                            <span class="text-sm clr-primary font-medium">+ Add subtask</span>
                        </td>
                        <td colspan="6"></td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="9" class="text-center py-8 text-gray-500">
                        No tasks for this project yet.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
