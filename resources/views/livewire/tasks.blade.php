<div class="flex flex-col gap-4">

    {{-- API error banner --}}
    @if($moveError)
    <div class="alert alert-error text-sm flex items-center gap-2 py-2 px-4 rounded-lg">
        <span>{{ $moveError }}</span>
        <button wire:click="$set('moveError', null)" class="ml-auto btn btn-ghost btn-xs">x</button>
    </div>
    @endif

    <div class="flex justify-between">
        <div class="flex gap-2">
            <button wire:click="switchView('list')"
                class="btn p-4 {{ $viewMode === 'list' ? 'clr-bg-primary text-base-100' : 'border-2 border-gray-400 clr-primary hover-clr-bg-primary hover:text-base-100 hover:border-none' }}">
                <x-icons.list class="w-4 h-4 inline-block" /> List
            </button>
            <button wire:click="switchView('board')"
                class="btn p-4 {{ $viewMode === 'board' ? 'clr-bg-primary text-base-100' : 'border-2 border-gray-400 clr-primary hover-clr-bg-primary hover:text-base-100 hover:border-none' }}">
                <x-icons.board class="w-4 h-4 inline-block" /> Board View
            </button>
        </div>
        <div class="flex items-center">
            <label class="input focus-within:outline-none bg-transparent focus-within:border-base-300 flex-1">
                <input wire:model.live.debounce.300ms="search" class="w-96 bg-transparent focus:outline-none rounded-xl" type="search" placeholder="Search" />
            </label>
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn w-36 border-2 border-gray rounded-xl m-1 hover-clr-bg-primary hover:text-white "><x-icons.sort class="w-4 h-4 inline-block" /> Filter</button>
                <ul tabindex="-1" class="dropdown-content menu bg-base-100 rounded-box z-50 w-56 p-2 shadow-lg mt-1">
                    <li><a href="#">Alphabetical (A - Z)</a></li>
                    <li><a href="#">Alphabetical (Z - A)</a></li>
                    <li><a href="#">Date (Newest first)</a></li>
                    <li><a href="#">Date (Oldest first)</a></li>
                </ul>
            </div>
            <button wire:click="openAddTaskModal" class="btn clr-bg-primary text-base-100 p-4">+ Add Task</button>
        </div>
    </div>

    {{-- Add Task Modal (wire:key forces re-render when priority count changes so dropdown gets fresh options) --}}
    <dialog class="{{ $showAddTaskModal ? 'modal modal-open' : 'modal' }}" wire:key="add-task-modal-{{ count($taskPriorityMap ?? []) }}">
        <div class="modal-box w-11/12 max-w-5xl overflow-y-auto">
            <div class="modal-action mt-0 mb-2">
                <button type="button" wire:click="closeAddTaskModal" class="btn btn-sm">✕</button>
            </div>
            <h3 class="font-bold text-lg">{{ $taskParentId ? 'New Subtask' : 'New Task' }}</h3>

            @if($errors->any())
                @php
                    $errorMessages = array_filter(array_map('trim', array_unique($errors->all())));
                @endphp
                <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 mt-3 text-sm text-red-700">
                    <p class="font-semibold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errorMessages as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                        @if(empty($errorMessages))
                            <li>An error occurred. Please check your input and try again.</li>
                        @endif
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

                {{-- Priority | Story Point | Start Date | Due Date --}}
                <div class="flex flex-wrap gap-4">
                    <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
                        <label class="font-medium text-sm">Priority <span class="text-red-500">*</span></label>
                        <select name="priorityId" class="select select-bordered w-full text-gray-900 bg-white {{ $errors->has('priorityId') ? 'border-red-500' : '' }}" required wire:key="priority-select-{{ count($taskPriorityMap ?? []) }}">
                            <option value="">Select priority</option>
                            @foreach($taskPriorityMap ?? [] as $pid => $pname)
                                <option value="{{ $pid }}" {{ (string) old('priorityId') === (string) $pid ? 'selected' : '' }}>{{ $pname }}</option>
                            @endforeach
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

                {{-- Assignees (multiple) — same style as project members, no table --}}
                @php
                    $rawOld = old('assigneeIds');
                    $oldAssigneeIds = is_array($rawOld)
                        ? array_map('intval', $rawOld)
                        : array_filter(array_map('intval', array_filter(explode(',', (string) ($rawOld ?? '')))));
                @endphp
                <div class="flex flex-col gap-2"
                     x-data="{
                         selectedIds: {{ json_encode($oldAssigneeIds) }},
                         toggle(id) {
                             const idx = this.selectedIds.indexOf(id);
                             if (idx >= 0) this.selectedIds.splice(idx, 1);
                             else this.selectedIds.push(id);
                         }
                     }">
                    <label class="font-medium text-sm">Assignees</label>
                    <div class="dropdown w-full">
                        <div tabindex="0" role="button"
                             class="border flex items-center justify-between w-full px-3 py-2 rounded-lg cursor-pointer bg-base-100">
                            <div class="flex flex-col">
                                <span class="font-medium text-sm">Select assignees</span>
                                <span class="text-xs text-gray-500" x-text="selectedIds.length ? selectedIds.length + ' selected' : 'Choose one or more assignees'"></span>
                            </div>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                        <ul tabindex="0"
                            class="dropdown-content menu bg-base-100 rounded-box z-[999] w-full shadow-lg border mt-1 max-h-60 overflow-y-auto">
                            @foreach($assignableAccounts as $account)
                                @php
                                    $aid    = $account['id']    ?? $account['Id']    ?? null;
                                    $aname  = $account['name']  ?? $account['Name']  ?? 'Unknown';
                                    $aemail = $account['email'] ?? $account['Email'] ?? '';
                                @endphp
                                @if($aid !== null)
                                    <li class="px-2 py-1">
                                        <x-person-option name="{{ $aname }}" :email="$aemail"
                                                         @click="toggle({{ (int) $aid }})">
                                            <template x-if="selectedIds.includes({{ (int) $aid }})">
                                                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none">
                                                    <rect x="0" y="0" width="20" height="20" rx="4" fill="#111827"/>
                                                    <path d="M5 10.5L8.25 13.75L15 7" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </template>
                                        </x-person-option>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                    <input type="hidden" name="assigneeIds" :value="selectedIds.join(',')" />
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

    {{-- Task detail modal --}}
    <dialog class="{{ $showTaskDetailModal ? 'modal modal-open' : 'modal' }}">
        <div class="modal-box w-11/12 max-w-3xl overflow-y-auto rounded-2xl shadow-xl">
            <div class="flex items-start justify-between gap-4 mb-6">
                <h2 class="font-bold text-2xl text-gray-900 leading-tight flex-1 min-w-0">
                    @if($detailTask)
                        {{ $detailTask['name'] ?? $detailTask['title'] ?? 'Task details' }}
                    @else
                        Task details
                    @endif
                </h2>
                <button type="button" wire:click="closeTaskDetail" class="btn btn-ghost btn-sm btn-circle w-8 h-8 min-h-0 shrink-0 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-full">✕</button>
            </div>
            @if($detailTask)
            @php
                $t = $detailTask;
                $dName = $t['name'] ?? $t['title'] ?? '—';
                $dDesc = trim((string)($t['description'] ?? ''));
                $dStatus = $t['statusName'] ?? $t['status'] ?? '—';
                $dPriority = $t['priorityName'] ?? $t['priority'] ?? '';
                if ($dPriority === '' && isset($t['priorityId'])) {
                    $dPriority = $taskPriorityMap[(int)($t['priorityId'] ?? $t['PriorityId'] ?? 0)] ?? '';
                }
                $dStoryPoints = $t['storyPoints'] ?? $t['storyPoint'] ?? null;
                $dStart = $t['startDate'] ?? $t['StartDate'] ?? null;
                $dDue = $t['dueDate'] ?? $t['dueAt'] ?? null;
                $dAssignee = $t['assigneeName'] ?? $t['assignedToName'] ?? null;
                if ($dAssignee === null || $dAssignee === '') {
                    $aids = $t['assigneeIds'] ?? $t['assigneeId'] ?? [];
                    if (!is_array($aids)) $aids = $aids ? [$aids] : [];
                    $dAssignee = implode(', ', array_filter(array_map(fn($id) => $accountMap[(int)$id] ?? null, $aids))) ?: '—';
                }
                // Assigned by = task creator (who created the task)
                $dAssignedBy = $t['createdByName'] ?? $t['creatorName'] ?? $t['createdBy'] ?? $t['assignedByName'] ?? null;
                if (($dAssignedBy === null || $dAssignedBy === '') && isset($t['createdById'])) {
                    $dAssignedBy = $accountMap[(int)($t['createdById'])] ?? null;
                }
                if (($dAssignedBy === null || $dAssignedBy === '') && isset($t['creatorId'])) {
                    $dAssignedBy = $accountMap[(int)($t['creatorId'])] ?? null;
                }
                if ($dAssignedBy === null || $dAssignedBy === '') {
                    $dAssignedBy = $currentUserName ?? '—';
                }
                $dStartFmt = $dStart ? \Carbon\Carbon::parse($dStart)->format('m/d/Y') : '';
                $dDueFmt = $dDue ? \Carbon\Carbon::parse($dDue)->format('m/d/Y') : '';
            @endphp
            {{-- Task metadata grid --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
                <div class="flex flex-row justify-between">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Story point</p>
                        <p class="text-base text-gray-900">{{ $dStoryPoints !== null && $dStoryPoints !== '' ? $dStoryPoints : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Start date</p>
                        <p class="text-base text-gray-900">{{ $dStartFmt ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Due date</p>
                        <p class="text-base text-gray-900">{{ $dDueFmt ?: '—' }}</p>
                    </div>
                </div>
                <div class="flex flex-row justify-between">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Priority</p>
                        <p class="text-base text-gray-900">{{ $dPriority !== '' ? $dPriority : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Status</p>
                        <p class="text-base text-gray-900">{{ $dStatus }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Assigned by</p>
                        <p class="text-base text-gray-900">{{ $dAssignedBy ?? '—' }}</p>
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Assigned to</p>
                    <p class="text-base text-gray-900">{{ $dAssignee }}</p>
                </div>
            </div>

            <div class="mb-6">
                <p class="text-sm font-semibold text-gray-700 mb-2">Description</p>
                <div class="border border-gray-300 rounded-lg bg-gray-50/50 p-4 min-h-[120px] text-sm text-gray-800 whitespace-pre-wrap">{{ $dDesc !== '' ? $dDesc : 'Description...' }}</div>
            </div>

            <div class="mb-6">
                <p class="text-sm font-semibold text-gray-700 mb-2">Comment</p>
                <div class="border border-gray-300 rounded-lg bg-white p-4 min-h-[80px] flex items-center">
                    <span class="text-gray-400 text-sm cursor-pointer hover:text-gray-600">Write a comment..</span>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" class="btn clr-bg-primary text-base-100 px-6">Send</button>
            </div>
            @endif
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="button" wire:click="closeTaskDetail">close</button>
        </form>
    </dialog>

    <div class="{{ $viewMode !== 'list' ? 'hidden' : '' }} overflow-x-auto max-h-[500px] relative">
        <table class="table w-full table-fixed border-separate [border-spacing:0_0.25rem]">
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

                $fmt = function (array $task) use ($accountMap, $taskPriorityMap) {
                    $taskName = $task['name'] ?? $task['title'] ?? '';

                    // Resolve assignee name: prefer API-provided name fields,
                    // then look up each ID in the accounts map
                    $assignee = $task['assigneeName'] ?? $task['assignedToName'] ?? $task['reporterName'] ?? null;
                    if ($assignee === null || $assignee === '') {
                        $ids = $task['assigneeIds'] ?? $task['assigneeId'] ?? [];
                        if (!is_array($ids)) $ids = [$ids];
                        $names = [];
                        foreach ($ids as $aid) {
                            if ($aid && isset($accountMap[(int) $aid])) {
                                $names[] = $accountMap[(int) $aid];
                            }
                        }
                        $assignee = implode(', ', $names);
                    }
                    $dueDateRaw  = $task['dueDate'] ?? $task['dueAt'] ?? null;
                    $storyPoints = $task['storyPoints'] ?? $task['storyPoint'] ?? $task['points'] ?? null;
                    $status      = $task['statusName'] ?? $task['status'] ?? '';
                    $priority    = $task['priorityName'] ?? $task['priority'] ?? '';
                    if ($priority === '' && isset($task['priorityId'])) {
                        $priority = $taskPriorityMap[(int) ($task['priorityId'] ?? $task['PriorityId'] ?? 0)] ?? '';
                    }

                    $statusBadge = match($status) {
                        'Not Started' => 'badge-ghost',
                        'In Progress' => 'badge-info',
                        'For Review'  => 'badge-warning',
                        'Completed'   => 'badge-success',
                        default       => 'badge-ghost',
                    };
                    $priorityBadge = match($priority) {
                        'Urgent'    => 'badge-error',
                        'Important' => 'badge-error',
                        'Medium'    => 'badge-warning',
                        'Low'       => 'badge-info',
                        default     => 'badge-ghost',
                    };

                    $id = $task['id'] ?? $task['Id'] ?? null;

                    $statusStyle = match($status) {
                        'Not Started' => 'background:#f3f4f6;color:#374151;',
                        'In Progress' => 'background:#dbeafe;color:#1d4ed8;',
                        'For Review'  => 'background:#fef3c7;color:#b45309;',
                        'Completed'   => 'background:#d1fae5;color:#065f46;',
                        default       => 'background:#f3f4f6;color:#374151;',
                    };

                    return compact('id','taskName','assignee','dueDateRaw','storyPoints','status','priority','statusBadge','priorityBadge','statusStyle');
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
                <tr class="hover:bg-gray-50 cursor-pointer" wire:click="openTaskDetail({{ $p['id'] ?? 0 }})">
                    <td wire:click.stop>
                        @if($parentId !== null)
                            <button
                                type="button"
                                class="btn btn-ghost btn-xs"
                                wire:click.stop="toggle({{ $parentId }})"
                            >
                                {{ $isExpanded ? 'v' : '>' }}
                            </button>
                        @endif
                    </td>
                    <td wire:click.stop>
                        <x-checkbox :task-id="$p['id'] ?? 0" :initial-status="$p['status'] ?? ''" />
                    </td>
                    <td>
                        <span class="font-semibold">{{ $p['taskName'] }}</span>
                    </td>
                    <td>{{ $p['assignee'] }}</td>
                    <td>
                        @if($p['dueDateRaw'])
                            {{ \Carbon\Carbon::parse($p['dueDateRaw'])->format('Y-m-d') }}
                        @endif
                    </td>
                    <td>{{ $p['storyPoints'] ?? '' }}</td>
                    <td wire:click.stop>
                        <div x-data="{
                                 status: '{{ addslashes($p['status']) }}',
                                 styles: {
                                     'Not Started': 'background:#f3f4f6;color:#374151;',
                                     'In Progress': 'background:#dbeafe;color:#1d4ed8;',
                                     'For Review':  'background:#fef3c7;color:#b45309;',
                                     'Completed':   'background:#d1fae5;color:#065f46;',
                                 },
                                 get pill() { return this.styles[this.status] || 'background:#f3f4f6;color:#374151;'; }
                             }"
                             class="relative inline-flex items-center rounded-none pl-7 pr-3 py-1"
                             :style="pill">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none flex items-center">
                                <x-icons.circle />
                            </span>
                            <select x-model="status"
                                    class="text-xs font-medium border-0 ring-0 shadow-none outline-none focus:ring-0 focus:outline-none cursor-pointer bg-transparent appearance-none pl-10 pr-2 py-1"
                                    style="border:none;box-shadow:none;"
                                    @change="Livewire.dispatch('task-status-changed', { taskId: {{ $p['id'] ?? 0 }}, newStatus: status })">
                                @foreach($boardStatuses as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                    </td>
                    <td>
                        <span class="badge {{ $p['priorityBadge'] }}">{{ $p['priority'] }}</span>
                    </td>
                    <td wire:click.stop>
                        <button type="button" class="btn btn-ghost btn-xs" wire:click="openTaskDetail({{ $p['id'] ?? 0 }})">details</button>
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
                        <tr class="hover:bg-gray-50 cursor-pointer" wire:click="openTaskDetail({{ $c['id'] ?? 0 }})">
                            <td wire:click.stop>
                                @if($childId !== null)
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-xs"
                                        wire:click.stop="toggle({{ $childId }})"
                                    >
                                        {{ $childExpanded ? 'v' : '>' }}
                                    </button>
                                @endif
                            </td>
                            <td wire:click.stop>
                                <x-checkbox :task-id="$c['id'] ?? 0" :initial-status="$c['status'] ?? ''" />
                            </td>
                            <td class="pl-10">
                                <span class="text-sm">{{ $c['taskName'] }}</span>
                            </td>
                            <td>{{ $c['assignee'] }}</td>
                            <td>
                                @if($c['dueDateRaw'])
                                    {{ \Carbon\Carbon::parse($c['dueDateRaw'])->format('Y-m-d') }}
                                @endif
                            </td>
                            <td>{{ $c['storyPoints'] ?? '' }}</td>
                            <td wire:click.stop>
                                <div x-data="{
                                         status: '{{ addslashes($c['status']) }}',
                                         styles: {
                                             'Not Started': 'background:#f3f4f6;color:#374151;',
                                             'In Progress': 'background:#dbeafe;color:#1d4ed8;',
                                             'For Review':  'background:#fef3c7;color:#b45309;',
                                             'Completed':   'background:#d1fae5;color:#065f46;',
                                         },
                                         get pill() { return this.styles[this.status] || 'background:#f3f4f6;color:#374151;'; }
                                     }"
                                     class="relative inline-flex items-center rounded-full pl-7 pr-3 py-0.5"
                                     :style="pill">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none flex items-center">
                                        <x-icons.circle />
                                    </span>
                                    <select x-model="status"
                                            class="text-xs font-medium border-0 ring-0 shadow-none outline-none focus:ring-0 focus:outline-none cursor-pointer bg-transparent appearance-none pl-10 pr-2 py-1"
                                            style="border:none;box-shadow:none;"
                                            @change="Livewire.dispatch('task-status-changed', { taskId: {{ $c['id'] ?? 0 }}, newStatus: status })">
                                        @foreach($boardStatuses as $s)
                                        <option value="{{ $s }}">{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-sm {{ $c['priorityBadge'] }}">{{ $c['priority'] }}</span>
                            </td>
                            <td wire:click.stop>
                                <button type="button" class="btn btn-ghost btn-xs" wire:click="openTaskDetail({{ $c['id'] ?? 0 }})">Edit</button>
                            </td>
                        </tr>

                        @if($childExpanded && $childId !== null)
                            @foreach($grandChildren as $gc)
                                @php $g = $fmt($gc); @endphp
                                <!-- Grandchild task rows -->
                                <tr class="hover:bg-gray-50 cursor-pointer" wire:click="openTaskDetail({{ $g['id'] ?? 0 }})">
                                    <td wire:click.stop></td>
                                    <td wire:click.stop>
                                        <x-checkbox :task-id="$g['id'] ?? 0" :initial-status="$g['status'] ?? ''" />
                                    </td>
                                    <td class="pl-16 text-xs">
                                        {{ $g['taskName'] }}
                                    </td>
                                    <td>{{ $g['assignee'] }}</td>
                                    <td>
                                        @if($g['dueDateRaw'])
                                            {{ \Carbon\Carbon::parse($g['dueDateRaw'])->format('Y-m-d') }}
                                        @endif
                                    </td>
                                    <td>{{ $g['storyPoints'] ?? '' }}</td>
                                    <td wire:click.stop>
                                        <div x-data="{
                                                 status: '{{ addslashes($g['status']) }}',
                                                 styles: {
                                                     'Not Started': 'background:#f3f4f6;color:#374151;',
                                                     'In Progress': 'background:#dbeafe;color:#1d4ed8;',
                                                     'For Review':  'background:#fef3c7;color:#b45309;',
                                                     'Completed':   'background:#d1fae5;color:#065f46;',
                                                 },
                                                 get pill() { return this.styles[this.status] || 'background:#f3f4f6;color:#374151;'; }
                                             }"
                                             class="relative inline-flex items-center rounded-full pl-7 pr-3 py-0.5"
                                             :style="pill">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none flex items-center">
                                                <x-icons.circle />
                                            </span>
                                            <select x-model="status"
                                                    class="text-xs font-medium border-0 ring-0 shadow-none outline-none focus:ring-0 focus:outline-none cursor-pointer bg-transparent appearance-none pl-10 pr-2 py-1"
                                                    style="border:none;box-shadow:none;"
                                                    @change="Livewire.dispatch('task-status-changed', { taskId: {{ $g['id'] ?? 0 }}, newStatus: status })">
                                                @foreach($boardStatuses as $s)
                                                <option value="{{ $s }}">{{ $s }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-sm {{ $g['priorityBadge'] }}">{{ $g['priority'] }}</span>
                                    </td>
                                    <td wire:click.stop>
                                        <button type="button" class="btn btn-ghost btn-xs" wire:click="openTaskDetail({{ $g['id'] ?? 0 }})">Edit</button>
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

    {{-- Board / Kanban View --}}
    @php
        $colStyles = [
            'Not Started' => 'bg-gray-100 border-gray-300',
            'In Progress' => 'bg-blue-50 border-blue-300',
            'For Review'  => 'bg-yellow-50 border-yellow-300',
            'Completed'   => 'bg-green-50 border-green-300',
        ];
        $priorityBadge = [
            'Urgent'    => 'badge-error',
            'Important' => 'badge-error',
            'Medium'    => 'badge-warning',
            'Low'       => 'badge-info',
        ];
    @endphp
    <div class="{{ $viewMode !== 'board' ? 'hidden' : '' }} flex gap-4 w-full p-4 overflow-x-auto overflow-y-hidden min-h-0 max-h-[calc(100vh-11rem)] rounded-lg">
        @foreach($boardStatuses as $status)
        @php $statusJs = addslashes($status); @endphp
        <div x-data="{ dragOver: false }"
             class="flex flex-col flex-1 min-w-[260px] max-w-[320px] min-h-0 rounded-lg transition-all duration-150 shrink-0"
             :class="dragOver ? 'ring-2 ring-blue-400 bg-blue-50/40' : ''"
             @dragover.prevent
             @dragenter.prevent="dragOver = true"
             @dragleave="if (!$event.relatedTarget || !$el.contains($event.relatedTarget)) dragOver = false"
             @drop.prevent="dragOver = false; var id = parseInt($event.dataTransfer.getData('text/plain')); if (id) Livewire.dispatch('task-status-changed', { taskId: id, newStatus: '{{ $statusJs }}' })">
            <div class="flex items-center justify-between px-3 py-2 rounded-lg border shrink-0 {{ $colStyles[$status] ?? 'bg-gray-100 border-gray-300' }}">
                <span class="font-semibold text-sm">{{ $status }}</span>
                <span class="badge badge-sm">{{ count($boardGrouped[$status] ?? []) }}</span>
            </div>
            <div class="flex flex-col gap-2 flex-1 min-h-0 overflow-y-auto p-3">
                @foreach($boardGrouped[$status] ?? [] as $task)
                @php $boardTaskId = (int)($task['id'] ?? $task['Id'] ?? 0); @endphp
                <div x-data="{ dragging: false }"
                     class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow cursor-grab active:cursor-grabbing"
                     draggable="true"
                     :style="dragging ? 'opacity:0.4' : ''"
                     @dragstart="dragging = true; $event.dataTransfer.setData('text/plain', '{{ $boardTaskId }}'); $event.dataTransfer.effectAllowed = 'move'"
                     @dragend="dragging = false">
                    <a href="#" class="block p-4 flex flex-col gap-3 cursor-pointer min-h-full no-underline text-inherit hover:bg-gray-50/50"
                       wire:click.prevent="openTaskDetail({{ $boardTaskId }})">
                        <span class="font-medium text-sm leading-snug">{{ $task['name'] ?? $task['title'] ?? '' }}</span>
                        <div class="flex flex-wrap gap-1.5 items-center">
                            @if(!empty($task['priority']))
                                <span class="badge badge-sm {{ $priorityBadge[$task['priority']] ?? 'badge-ghost' }}">{{ $task['priority'] }}</span>
                            @endif
                            @if(isset($task['storyPoints']) || isset($task['storyPoint']))
                                <span class="badge badge-sm badge-ghost">{{ $task['storyPoints'] ?? $task['storyPoint'] }} pts</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between text-xs text-gray-500 mt-2">
                            @php
                                $boardAssignee = $task['assigneeName'] ?? $task['assignedToName'] ?? null;
                                if (!$boardAssignee) {
                                    $bids = $task['assigneeIds'] ?? $task['assigneeId'] ?? [];
                                    if (!is_array($bids)) $bids = [$bids];
                                    $bnames = array_filter(array_map(fn($id) => $accountMap[(int)$id] ?? null, $bids));
                                    $boardAssignee = implode(', ', $bnames) ?: 'Unassigned';
                                }
                            @endphp
                            <span>{{ $boardAssignee }}</span>
                            @if(!empty($task['dueDate']) || !empty($task['dueAt']))
                                <span>{{ \Carbon\Carbon::parse($task['dueDate'] ?? $task['dueAt'])->format('M d') }}</span>
                            @endif
                        </div>
                    </a>
                </div>
                @endforeach
                @if(empty($boardGrouped[$status]))
                <div class="text-center text-xs text-gray-400 py-6">No tasks</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

</div>
