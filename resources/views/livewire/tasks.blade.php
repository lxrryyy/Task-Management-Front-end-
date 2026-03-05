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
                @php
                    $oldAssigneeId = old('assigneeId', '');
                    $oldAssigneeName = 'Choose assignee';
                    foreach ($assignableAccounts as $_acc) {
                        $_aid = $_acc['id'] ?? $_acc['Id'] ?? null;
                        if ($_aid && (string) $_aid === (string) $oldAssigneeId) {
                            $oldAssigneeName = $_acc['name'] ?? $_acc['Name'] ?? 'Unknown';
                            break;
                        }
                    }
                @endphp
                <div class="flex flex-col gap-1"
                     x-data="{
                         open: false,
                         selectedId: '{{ $oldAssigneeId }}',
                         selectedName: '{{ addslashes($oldAssigneeName) }}'
                     }">
                    <label class="font-medium text-sm">Assignee</label>

                    {{-- Trigger --}}
                    <div @click="open = !open" @click.outside="open = false" tabindex="0" role="button"
                         class="border flex items-center justify-between w-full px-3 py-2 rounded-lg cursor-pointer bg-base-100">
                        <div class="flex flex-col">
                            <span class="font-medium text-sm">Assignee</span>
                            <span class="text-xs text-gray-500" x-text="selectedName"></span>
                        </div>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>

                    {{-- Dropdown list --}}
                    <ul x-show="open" x-transition
                        class="bg-base-100 rounded-box z-[999] w-full shadow-lg border mt-1 max-h-60 overflow-y-auto">

                        {{-- Unassigned --}}
                        <li class="px-2 py-1">
                            <x-person-option name="Unassigned"
                                             @click="selectedId = ''; selectedName = 'Choose assignee'; open = false">
                                <template x-if="selectedId === ''">
                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none">
                                        <rect x="0" y="0" width="20" height="20" rx="4" fill="#111827"/>
                                        <path d="M5 10.5L8.25 13.75L15 7" stroke="#FFFFFF" stroke-width="2"
                                              stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </template>
                            </x-person-option>
                        </li>

                        @foreach($assignableAccounts as $account)
                            @php
                                $aid    = $account['id']    ?? $account['Id']    ?? null;
                                $aname  = $account['name']  ?? $account['Name']  ?? 'Unknown';
                                $aemail = $account['email'] ?? $account['Email'] ?? '';
                            @endphp
                            @if($aid !== null)
                                <li class="px-2 py-1">
                                    <x-person-option
                                        :name="$aname"
                                        :email="$aemail"
                                        @click="selectedId = '{{ $aid }}'; selectedName = '{{ addslashes($aname) }}'; open = false">
                                        <template x-if="selectedId == '{{ $aid }}'">
                                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none">
                                                <rect x="0" y="0" width="20" height="20" rx="4" fill="#111827"/>
                                                <path d="M5 10.5L8.25 13.75L15 7" stroke="#FFFFFF" stroke-width="2"
                                                      stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </template>
                                    </x-person-option>
                                </li>
                            @endif
                        @endforeach
                    </ul>

                    {{-- Hidden input carries the selected ID on form submit --}}
                    <input type="hidden" name="assigneeId" :value="selectedId">
                </div>

                {{-- Priority | Story Point | Start Date | Due Date --}}
                <div class="flex flex-wrap gap-4">
                    <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
                        <label class="font-medium text-sm">Priority</label>
                        <select name="priority" class="select select-bordered w-full">
                            <option value="">Priority</option>
                            @foreach($taskPriorities as $pr)
                                <option value="{{ $pr }}" {{ old('priority') === $pr ? 'selected' : '' }}>{{ $pr }}</option>
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

    <div class="{{ $viewMode !== 'list' ? 'hidden' : '' }} overflow-x-auto max-h-[500px] relative">
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

                $fmt = function (array $task) use ($accountMap) {
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
                <tr class="hover:bg-gray-50">
                    <td>
                        @if($parentId !== null)
                            <button
                                type="button"
                                class="btn btn-ghost btn-xs"
                                wire:click="toggle({{ $parentId }})"
                            >
                                {{ $isExpanded ? 'v' : '>' }}
                            </button>
                        @endif
                    </td>
                    <td>
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
                    <td>
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
                                    class="text-xs font-medium border-0 ring-0 shadow-none outline-none focus:ring-0 focus:outline-none cursor-pointer bg-transparent appearance-none pl-9"
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
                                        {{ $childExpanded ? 'v' : '>' }}
                                    </button>
                                @endif
                            </td>
                            <td>
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
                            <td>
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
                                            class="text-xs font-medium border-0 ring-0 shadow-none outline-none focus:ring-0 focus:outline-none cursor-pointer bg-transparent appearance-none pl-9"
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
                            <td>
                                <button class="btn btn-ghost btn-xs">Edit</button>
                            </td>
                        </tr>

                        @if($childExpanded && $childId !== null)
                            @foreach($grandChildren as $gc)
                                @php $g = $fmt($gc); @endphp
                                <!-- Grandchild task rows -->
                                <tr class="hover:bg-gray-50">
                                    <td></td>
                                    <td>
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
                                    <td>
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
                                                    class="text-xs font-medium border-0 ring-0 shadow-none outline-none focus:ring-0 focus:outline-none cursor-pointer bg-transparent appearance-none pl-9"
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
    <div class="{{ $viewMode !== 'board' ? 'hidden' : '' }} flex gap-4 w-full pb-4">
        @foreach($boardStatuses as $status)
        @php $statusJs = addslashes($status); @endphp
        <div x-data="{ dragOver: false }"
             class="flex flex-col gap-3 flex-1 min-w-0 rounded-lg transition-all duration-150"
             :class="dragOver ? 'ring-2 ring-blue-400 bg-blue-50/40' : ''"
             @dragover.prevent
             @dragenter.prevent="dragOver = true"
             @dragleave="if (!$event.relatedTarget || !$el.contains($event.relatedTarget)) dragOver = false"
             @drop.prevent="dragOver = false; var id = parseInt($event.dataTransfer.getData('text/plain')); if (id) Livewire.dispatch('task-status-changed', { taskId: id, newStatus: '{{ $statusJs }}' })">
            <div class="flex items-center justify-between px-3 py-2 rounded-lg border {{ $colStyles[$status] ?? 'bg-gray-100 border-gray-300' }}">
                <span class="font-semibold text-sm">{{ $status }}</span>
                <span class="badge badge-sm">{{ count($boardGrouped[$status] ?? []) }}</span>
            </div>
            <div class="flex flex-col gap-2 min-h-[100px]">
                @foreach($boardGrouped[$status] ?? [] as $task)
                <div x-data="{ dragging: false }"
                     class="bg-white rounded-lg border border-gray-200 shadow-sm p-3 flex flex-col gap-2 hover:shadow-md transition-shadow cursor-grab"
                     draggable="true"
                     :style="dragging ? 'opacity:0.4' : ''"
                     @dragstart="dragging = true; $event.dataTransfer.setData('text/plain', '{{ (int)($task['id'] ?? $task['Id'] ?? 0) }}'); $event.dataTransfer.effectAllowed = 'move'"
                     @dragend="dragging = false">
                    <span class="font-medium text-sm leading-tight">{{ $task['name'] ?? $task['title'] ?? '' }}</span>
                    <div class="flex flex-wrap gap-1 items-center">
                        @if(!empty($task['priority']))
                            <span class="badge badge-sm {{ $priorityBadge[$task['priority']] ?? 'badge-ghost' }}">{{ $task['priority'] }}</span>
                        @endif
                        @if(isset($task['storyPoints']) || isset($task['storyPoint']))
                            <span class="badge badge-sm badge-ghost">{{ $task['storyPoints'] ?? $task['storyPoint'] }} pts</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
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
