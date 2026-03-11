<div>
    @php
        // Keep modal heights consistent between "select project" and "create task".
        $modalBoxClass = 'modal-box w-11/12 max-w-5xl overflow-y-auto h-[32rem]';
    @endphp

    {{-- Step 1: Select project --}}
    <dialog class="{{ $showSelectProjectModal ? 'modal modal-open' : 'modal' }}">
        <div class="{{ $modalBoxClass }}">
            <div class="modal-action mt-0 mb-2">
                <button type="button" wire:click="closeAll" class="btn btn-sm">✕</button>
            </div>
            <h3 class="font-bold text-lg">Select a project</h3>
            <p class="text-sm text-gray-500 mt-1">Choose a project first before creating a task.</p>

            <div class="mt-4 border rounded-lg overflow-hidden">
                <div class="max-h-80 overflow-y-auto">
                    @forelse($projects as $p)
                        @php
                            $pid = (int) ($p['id'] ?? $p['Id'] ?? 0);
                            $pname = $p['name'] ?? $p['Name'] ?? $p['title'] ?? 'Project';
                        @endphp
                        @if($pid > 0)
                            <button type="button" wire:click="chooseProject({{ $pid }})"
                                    class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 border-b last:border-b-0">
                                <span class="font-medium text-gray-900">{{ $pname }}</span>
                                <span class="text-xs text-gray-500">Select</span>
                            </button>
                        @endif
                    @empty
                        <div class="px-4 py-6 text-sm text-gray-400">No projects found.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="button" wire:click="closeAll">close</button>
        </form>
    </dialog>

    {{-- Step 2: Create task (same flow as Tasks module) --}}
    <dialog class="{{ $showAddTaskModal ? 'modal modal-open' : 'modal' }}" wire:key="dashboard-add-task-modal-{{ count($taskPriorityMap ?? []) }}">
        <div class="{{ $modalBoxClass }}">
            <div class="modal-action mt-0 mb-2 flex justify-between items-center">
                <button type="button" wire:click="backToProjectSelect" class="btn btn-sm">Back</button>
                <button type="button" wire:click="closeAll" class="btn btn-sm">✕</button>
            </div>
            <h3 class="font-bold text-lg">New Task</h3>

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

            @if($selectedProjectId)
                <form method="POST" action="{{ route('tasks.store', $selectedProjectId) }}" class="mt-4 flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="redirect_to" value="dashboard" />

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
                            <select name="priorityId" class="select select-bordered w-full text-gray-900 bg-white {{ $errors->has('priorityId') ? 'border-red-500' : '' }}" required wire:key="dashboard-priority-select-{{ count($taskPriorityMap ?? []) }}">
                                <option value="">Select priority</option>
                                @foreach($taskPriorityMap ?? [] as $pid => $pname)
                                    <option value="{{ $pid }}" {{ (string) old('priorityId') === (string) $pid ? 'selected' : '' }}>{{ $pname }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
                            <label class="font-medium text-sm">Story Point</label>
                            <input name="storyPoints" type="number" min="0" placeholder="0" class="input input-bordered w-full" value="{{ old('storyPoints') }}" />
                        </div>

                        <div class="flex flex-col gap-1 flex-1 min-w-[140px]">
                            <label class="font-medium text-sm">Start Date</label>
                            <input name="startDate" type="date" class="input input-bordered w-full {{ $errors->has('startDate') ? 'border-red-500' : '' }}" value="{{ old('startDate') }}" />
                            @foreach($errors->get('startDate') as $msg)
                                <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
                            @endforeach
                        </div>

                        <div class="flex flex-col gap-1 flex-1 min-w-[140px]">
                            <label class="font-medium text-sm">Due Date</label>
                            <input name="dueDate" type="date" class="input input-bordered w-full {{ $errors->has('dueDate') ? 'border-red-500' : '' }}" value="{{ old('dueDate') }}" />
                            @foreach($errors->get('dueDate') as $msg)
                                <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
                            @endforeach
                        </div>
                    </div>

                    {{-- Assignees --}}
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
                                @if(empty($assignableAccounts))
                                    <li class="px-3 py-2 text-sm text-gray-400">No members found for this project.</li>
                                @endif
                            </ul>
                        </div>
                        <input type="hidden" name="assigneeIds" :value="selectedIds.join(',')" />
                    </div>

                    {{-- Description --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-medium text-sm">Description</label>
                        <textarea name="description" class="textarea textarea-bordered w-full h-32" placeholder="Task description">{{ old('description') }}</textarea>
                    </div>

                    <div class="modal-action">
                        <button type="submit" class="btn clr-bg-primary text-base-100 px-6">+ Add tasks</button>
                    </div>
                </form>
            @else
                <div class="mt-4 text-sm text-gray-400">Please select a project first.</div>
            @endif
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="button" wire:click="closeAll">close</button>
        </form>
    </dialog>
</div>

