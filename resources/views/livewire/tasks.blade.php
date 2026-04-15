<div class="flex flex-col gap-4">

    {{-- Success banner (Livewire-persisted flash so it is not removed on the next component update) --}}
    @if ($flashSuccess)
        <div x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.dismissFlashSuccess() }, 6500)" x-show="show"
            x-transition.opacity.duration.300ms
            class="alert alert-success text-sm flex items-center gap-2 py-2 px-4 rounded-lg">
            <span>{{ $flashSuccess }}</span>
        </div>
    @endif

    {{-- Warning is shown under Due Date in the modal (not here) --}}

    {{-- API error banner --}}
    @if ($moveError)
        <div class="alert alert-error text-sm flex items-center gap-2 py-2 px-4 rounded-lg">
            <span>{{ $moveError }}</span>
            <button wire:click="$set('moveError', null)" class="ml-auto btn btn-ghost btn-xs">x</button>
        </div>
    @endif

    <div class="flex w-full items-center clr-primary ">
        <a href="/projects"
            class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('projects') ? 'clr-primary' : '' }} hover-clr-accent">
            <x-icons.back-btn classes="w-6 h-6" />
        </a>
        <span class="group-hover:block text-xl">Tasks</span>
    </div>
    <hr class="border-2 clr-bg-primary">

    @if (($currentParentTaskId ?? null) !== null)
        <div class="flex items-center gap-2 text-sm text-gray-600">
            <button type="button" class="hover:underline" wire:click="goToTaskLevel(null)">Tasks</button>
            @foreach (($currentBreadcrumb ?? []) as $crumb)
                <span>/</span>
                <button type="button" class="hover:underline"
                    wire:click="goToTaskLevel({{ (int) ($crumb['id'] ?? 0) }})">
                    {{ $crumb['name'] ?? 'Task' }}
                </button>
            @endforeach
        </div>
    @endif

    <div class="flex justify-between">
        <div class="flex gap-2">
            <button wire:click="switchView('list')" data-viewmode-btn data-viewmode="list"
                class="btn p-4 {{ $viewMode === 'list' ? 'clr-bg-primary text-base-100' : 'border-2 border-gray-400 clr-primary hover-clr-bg-primary hover:text-base-100 hover:border-none' }}">
                <x-icons.list class="w-4 h-4 inline-block" /> List
            </button>
            <button wire:click="switchView('board')" data-viewmode-btn data-viewmode="board"
                class="btn p-4 {{ $viewMode === 'board' ? 'clr-bg-primary text-base-100' : 'border-2 border-gray-400 clr-primary hover-clr-bg-primary hover:text-base-100 hover:border-none' }}">
                <x-icons.board class="w-4 h-4 inline-block" /> Board View
            </button>

            <script>
                (function () {
                    document.addEventListener('click', function (e) {
                        var btn = e.target && e.target.closest ? e.target.closest('[data-viewmode-btn]') : null;
                        if (!btn) return;
                        var mode = btn.getAttribute('data-viewmode');

                        try {
                            var url = new URL(window.location.href);
                            url.searchParams.set('view', mode);
                            window.history.replaceState({}, '', url.toString());
                        } catch (e2) {}
                    });
                })();
            </script>
        </div>
        <div class="flex items-center gap-2">
            <x-search-input wire:model.live.debounce.300ms="search" input-class="w-64 bg-transparent focus:outline-none rounded-lg" />
            <x-filter-dropdown
                button-class="btn border-2 border-gray rounded-lg clr-primary text-base-100 p-4 hover-clr-bg-primary hover:text-base-100"
                clear-action="clearTaskFilters">
                <div class="flex flex-col gap-1">
                    <span class="text-gray-600">Status</span>
                    <select wire:model.live="filterStatus" class="select select-bordered w-full bg-white text-gray-900">
                        <option value="">All statuses</option>
                        @foreach ($boardStatuses ?? [] as $statusOption)
                            <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <span class="text-gray-600">Priority</span>
                    <select wire:model.live="filterPriority"
                        class="select select-bordered w-full bg-white text-gray-900">
                        <option value="">All priorities</option>
                        @foreach ($taskPriorityNames ?? [] as $priorityOption)
                            <option value="{{ $priorityOption }}">{{ $priorityOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="flex flex-col gap-1">
                        <span class="text-gray-600">Date From</span>
                        <input wire:model.live="filterDateFrom" type="date"
                            class="input input-bordered w-full bg-white text-gray-900" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-gray-600">Date To</span>
                        <input wire:model.live="filterDateTo" type="date"
                            class="input input-bordered w-full bg-white text-gray-900" />
                    </div>
                </div>
            </x-filter-dropdown>
            <button wire:click="openAddTaskModal" class="btn clr-bg-primary text-base-100 p-4">+ Add Task</button>
        </div>
    </div>

    {{-- Add Task Modal (wire:key forces re-render when priority count changes so dropdown gets fresh options) --}}
    <dialog class="{{ $showAddTaskModal ? 'modal modal-open' : 'modal' }}"
        wire:key="add-task-modal-{{ count($taskPriorityMap ?? []) }}">
        <div class="modal-box w-11/12 max-w-5xl overflow-y-auto">
            <div class="modal-action mt-0 mb-2">
                <button type="button" wire:click="closeAddTaskModal" class="btn btn-sm">✕</button>
            </div>
            <h3 class="font-normal text-lg">{{ $taskParentId ? 'New Subtask' : 'New Task' }}</h3>

            @php
                $liveErrs = $errors->any()
                    ? array_values(array_filter(array_unique(array_map('trim', $errors->all()))))
                    : [];
                $modalErrorMessages = array_values(array_unique(array_merge($flashErrorMessages, $liveErrs)));
            @endphp
            @if (!empty($modalErrorMessages))
                <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 mt-3 text-sm text-red-700">
                    <p class="font-normal mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($modalErrorMessages as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('tasks.store', $projectId) }}" class="mt-4 flex flex-col gap-4"
                data-due-calc="true">
                @csrf
                <input type="hidden" name="projectId" value="{{ (int) $projectId }}" />
                @if ($taskParentId)
                    <input type="hidden" name="parentTaskId" value="{{ $taskParentId }}" />
                @endif

                {{-- Assignees (multiple) — same style as project members, no table --}}
                @php
                    $rawOld = old('assigneeIds');
                    $oldAssigneeIds = is_array($rawOld)
                        ? array_map('intval', $rawOld)
                        : array_filter(array_map('intval', array_filter(explode(',', (string) ($rawOld ?? '')))));
                    $resolveAccountBioSpec = function (array $account): array {
                        $bio = '';
                        foreach (['bio', 'Bio', 'about', 'About', 'summary', 'Summary'] as $key) {
                            $raw = $account[$key] ?? null;
                            if ($raw === null || $raw === '') {
                                continue;
                            }
                            $t = trim((string) $raw);
                            if ($t !== '') {
                                $bio = $t;
                                break;
                            }
                        }
                        $spec = '';
                        foreach (
                            [
                                'specialization', 'Specialization', 'specialisations', 'Specialisations',
                                'jobTitle', 'JobTitle', 'position', 'Position',
                                'title', 'Title', 'department', 'Department',
                            ] as $key
                        ) {
                            $raw = $account[$key] ?? null;
                            if ($raw === null || $raw === '') {
                                continue;
                            }
                            $t = trim((string) $raw);
                            if ($t !== '') {
                                $spec = $t;
                                break;
                            }
                        }
                        return [$bio, $spec];
                    };
                @endphp
                <div class="flex flex-col gap-2" x-data="{
                    selectedIds: {{ json_encode($oldAssigneeIds) }},
                    toggle(id) {
                        const idx = this.selectedIds.indexOf(id);
                        if (idx >= 0) this.selectedIds.splice(idx, 1);
                        else this.selectedIds.push(id);
                        // Trigger due-date recalculation + overload precheck
                        queueMicrotask(() => window.__tasksDueCalc?.recalc?.());
                    }
                }">
                    <label class="font-medium text-sm">Assignees</label>
                    <div class="dropdown w-full">
                        <div tabindex="0" role="button"
                            class="border flex items-center justify-between w-full px-3 py-2 rounded-lg cursor-pointer bg-base-100">
                            <div class="flex flex-col">
                                <span class="font-medium text-sm">Select assignees</span>
                                <span class="text-xs text-gray-500"
                                    x-text="selectedIds.length ? selectedIds.length + ' selected' : 'Choose one or more assignees'"></span>
                            </div>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                        <ul tabindex="0"
                            class="dropdown-content bg-base-100 rounded-box z-[999] w-full shadow-lg border mt-1 max-h-60 overflow-y-auto">
                            @foreach ($assignableAccounts as $account)
                                @php
                                    $aid = $account['id'] ?? ($account['Id'] ?? null);
                                    $aname = $account['name'] ?? ($account['Name'] ?? 'Unknown');
                                    $aemail = $account['email'] ?? ($account['Email'] ?? '');
                                    $apic = $account['profilePicture'] ?? ($account['ProfilePicture'] ?? null);
                                    if ($apic && !str_starts_with($apic, 'http') && !str_starts_with($apic, 'data:')) {
                                        $apic = 'data:image/jpeg;base64,' . $apic;
                                    }
                                    $parts = preg_split('/\s+/', trim($aname));
                                    $ainitials = mb_strtoupper(
                                        mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1),
                                    );
                                    [$abio, $aspec] = $resolveAccountBioSpec($account);
                                @endphp
                                @if ($aid !== null)
                                    <li class="px-2 py-1">
                                        <x-person-option name="{{ $aname }}" :email="$aemail" :picture="$apic"
                                            :bio="$abio" :specialization="$aspec" initials="{{ $ainitials }}"
                                            @click="toggle({{ (int) $aid }})">
                                            <template x-if="selectedIds.includes({{ (int) $aid }})">
                                                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none">
                                                    <rect x="0" y="0" width="20" height="20" rx="4"
                                                        fill="#111827" />
                                                    <path d="M5 10.5L8.25 13.75L15 7" stroke="#FFFFFF"
                                                        stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round" />
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

                {{-- Task Name --}}
                <div class="flex flex-col gap-1">
                    <label class="font-medium text-sm">Task Name</label>
                    <input name="name" type="text" placeholder="Enter task name"
                        class="input input-bordered rounded-lg w-full {{ $errors->has('name') ? 'border-red-500' : '' }}"
                        value="{{ old('name') }}" required />
                    @foreach ($errors->get('name') as $msg)
                        <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
                    @endforeach
                </div>

                {{-- Priority | Story Point | Start Date | Due Date --}}
                <div class="flex flex-wrap gap-4">
                    <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
                        <label class="font-medium text-sm">Priority <span class="text-red-500">*</span></label>
                        <select name="priorityId"
                            class="select select-bordered w-full text-gray-900 rounded-lg bg-white {{ $errors->has('priorityId') ? 'border-red-500' : '' }}"
                            required wire:key="priority-select-{{ count($taskPriorityMap ?? []) }}">
                            <option value="">Select priority</option>
                            @foreach ($taskPriorityMap ?? [] as $pid => $pname)
                                <option value="{{ $pid }}"
                                    {{ (string) old('priorityId') === (string) $pid ? 'selected' : '' }}>
                                    {{ $pname }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
                        <label class="font-medium text-sm">Story Point</label>
                        @php $storyPointOptions = [1,2,3,5,8,13,21]; @endphp
                        <select name="storyPoints"
                            class="select select-bordered rounded-lg w-full text-gray-900 bg-white">
                            <option value="">Select</option>
                            @foreach ($storyPointOptions as $sp)
                                <option value="{{ $sp }}" @selected(old('storyPoints') == $sp)>{{ $sp }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col gap-1 flex-1 min-w-[140px]">
                        <label class="font-medium text-sm">Start Date</label>
                        @php
                            $oldStartRaw = old('startDate');
                            $oldStartVal = '';
                            if ($oldStartRaw) {
                                try {
                                    $oldStartVal = \Carbon\Carbon::parse($oldStartRaw)->format('Y-m-d\TH:i');
                                } catch (\Throwable) {
                                    $oldStartVal = (string) $oldStartRaw;
                                }
                            }
                        @endphp
                        <input name="startDate" type="datetime-local"
                            class="input input-bordered rounded-lg w-full {{ $errors->has('startDate') ? 'border-red-500' : '' }}"
                            value="{{ $oldStartVal }}" />
                        @foreach ($errors->get('startDate') as $msg)
                            <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
                        @endforeach
                    </div>

                    <div class="flex flex-col gap-1 flex-1 min-w-[140px]">
                        <label class="font-medium text-sm">Due Date</label>
                        @php
                            $oldDueRaw = old('dueDate');
                            $oldDueVal = '';
                            if ($oldDueRaw) {
                                try {
                                    $oldDueVal = \Carbon\Carbon::parse($oldDueRaw)->format('Y-m-d\TH:i');
                                } catch (\Throwable) {
                                    $oldDueVal = (string) $oldDueRaw;
                                }
                            }
                        @endphp
                        <input name="dueDate" type="datetime-local"
                            class="input input-bordered rounded-lg w-full {{ $errors->has('dueDate') ? 'border-red-500' : '' }}"
                            value="{{ $oldDueVal }}" />
                        @foreach ($errors->get('dueDate') as $msg)
                            <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
                        @endforeach

                        <div class="mt-2 hidden items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-900"
                            data-due-calc-hint>
                            <span class="loading loading-spinner loading-xs"></span>
                            <span>Auto-computing due date...</span>
                        </div>

                        <div class="mt-2 rounded-md border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs text-yellow-900 hidden"
                            data-overload-warnings>
                            <p class="font-semibold mb-1">Task created with warnings:</p>
                            <ul class="list-disc list-inside space-y-0.5" data-overload-warnings-list></ul>
                        </div>

                        @if (!empty($taskWarnings))
                            <div
                                class="mt-2 rounded-md border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs text-yellow-900">
                                <p class="font-semibold mb-1">Task created with warnings:</p>
                                <ul class="list-disc list-inside space-y-0.5">
                                    @foreach ($taskWarnings as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Description --}}
                <div wire:ignore class="flex flex-col gap-1">
                    <label class="font-medium text-sm">Description</label>
                    <x-rich-text-editor name="description" :value="old('description', '')" placeholder="Task description" />
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
        <div class="modal-box w-11/12 max-w-3xl max-h-[90vh] rounded-2xl shadow-xl overflow-auto">
            <div class="flex items-start justify-between gap-4 mb-6">
                <div class="flex-1 min-w-0">
                    @if (!empty($detailBreadcrumb) && count($detailBreadcrumb) > 1)
                        <div class="text-lg text-gray-500 mb-1 truncate">
                            @foreach ($detailBreadcrumb as $i => $bt)
                                @php
                                    $bName = $bt['name'] ?? ($bt['title'] ?? '—');
                                    $bId = (int) ($bt['id'] ?? $bt['Id'] ?? 0);
                                @endphp
                                @if ($i > 0)
                                    <span class="mx-1">/</span>
                                @endif
                                <button type="button" class="hover:underline"
                                    wire:click="openTaskDetail({{ $bId }})">
                                    {{ $bName }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <h2 class="font-normal text-2xl text-gray-900 leading-tight">
                    @if ($detailTask)
                        {{ $detailTask['name'] ?? ($detailTask['title'] ?? 'Task details') }}
                    @else
                        Task details
                    @endif
                    </h2>
                </div>
                <button type="button" wire:click="closeTaskDetail"
                    class="btn btn-ghost btn-sm btn-circle w-8 h-8 min-h-0 shrink-0 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-full">✕</button>
            </div>
            @if ($detailTask)
                @php
                    $t = $detailTask;
                    $dName = $t['name'] ?? ($t['title'] ?? '—');
                    $dDesc = trim((string) ($t['description'] ?? ''));
                    $dStatus = $t['statusName'] ?? ($t['status'] ?? '—');
                    $dPriority = $t['priorityName'] ?? ($t['priority'] ?? '');
                    if ($dPriority === '' && isset($t['priorityId'])) {
                        $dPriority = $taskPriorityMap[(int) ($t['priorityId'] ?? ($t['PriorityId'] ?? 0))] ?? '';
                    }
                    $dPriorityStyle = match ($dPriority) {
                        'Urgent' => 'background:#fee2e2;color:#ef4444;',
                        'Important' => 'background:#fce7f3;color:#ec4899;',
                        'Medium' => 'background:#dbeafe;color:#3b82f6;',
                        'Low' => 'background:#e5e7eb;color:#374151;',
                        default => 'background:#f3f4f6;color:#6b7280;',
                    };
                    $dStoryPoints = $t['storyPoints'] ?? ($t['storyPoint'] ?? null);
                    $dStart = $t['startDate'] ?? ($t['StartDate'] ?? null);
                    $dDue = $t['dueDate'] ?? ($t['dueAt'] ?? null);
                    $dAssignee = $t['assigneeName'] ?? ($t['assignedToName'] ?? null);
                    $dAssigneeProfiles = [];
                    $aids = $t['assigneeIds'] ?? ($t['assigneeId'] ?? []);
                    if (!is_array($aids)) {
                        $aids = $aids ? [$aids] : [];
                    }
                    if ($dAssignee === null || $dAssignee === '') {
                        $dAssignee =
                            implode(', ', array_filter(array_map(fn($id) => $accountMap[(int) $id] ?? null, $aids))) ?:
                            '—';
                    }
                    if (!empty($aids)) {
                        $profilesById = [];
                        foreach ($aids as $aid) {
                            $aidInt = (int) $aid;
                            if ($aidInt <= 0) {
                                continue;
                            }
                            if (isset($accountProfiles[$aidInt])) {
                                $profilesById[$aidInt] = array_merge(['id' => $aidInt], $accountProfiles[$aidInt]);
                                continue;
                            }
                            $name = $accountMap[$aidInt] ?? null;
                            $parts = preg_split('/\s+/', trim((string) ($name ?? '')));
                            $parts = array_values(array_filter($parts, fn($p) => is_string($p) && trim($p) !== ''));
                            $first = (string) ($parts[0] ?? '');
                            $last = (string) (!empty($parts) ? implode(' ', array_slice($parts, 1)) : '');
                            $a0 = mb_substr(trim($first), 0, 1);
                            $b0 = mb_substr(trim($last), 0, 1);
                            if ($a0 !== '' && $b0 !== '') {
                                $initials = mb_strtoupper($a0.$b0);
                            } elseif ($a0 !== '') {
                                $initials = mb_strtoupper($a0);
                            } else {
                                $initials = '?';
                            }
                            $profilesById[$aidInt] = [
                                'id' => $aidInt,
                                'profilePicture' => null,
                                'initials' => $initials,
                                'name' => $name,
                            ];
                        }
                        $dAssigneeProfiles = array_values($profilesById);
                    }
                    // Assigned by = task creator (who created the task)
                    $dAssignedBy =
                        $t['createdByName'] ??
                        ($t['creatorName'] ?? ($t['createdBy'] ?? ($t['assignedByName'] ?? null)));
                    if (($dAssignedBy === null || $dAssignedBy === '') && isset($t['createdById'])) {
                        $dAssignedBy = $accountMap[(int) $t['createdById']] ?? null;
                    }
                    if (($dAssignedBy === null || $dAssignedBy === '') && isset($t['creatorId'])) {
                        $dAssignedBy = $accountMap[(int) $t['creatorId']] ?? null;
                    }
                    if ($dAssignedBy === null || $dAssignedBy === '') {
                        $dAssignedBy = $currentUserName ?? '—';
                    }
                    $dStartFmt = $dStart ? \Carbon\Carbon::parse($dStart)->format('m/d/Y') : '';
                    $dDueFmt = $dDue ? \Carbon\Carbon::parse($dDue)->format('m/d/Y') : '';
                @endphp
                {{-- Task metadata grid: 3 columns, 3 rows --}}
                <div class="mb-6"
                    style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.25rem 2rem;">
                    {{-- Row 1 --}}
                    <div>
                        <p class="text-xs font-normal text-gray-500 uppercase tracking-wide mb-1">Story Point</p>
                        <p class="text-sm text-gray-900">
                            {{ $dStoryPoints !== null && $dStoryPoints !== '' ? $dStoryPoints : '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-normal text-gray-500 uppercase tracking-wide mb-1">Start Date</p>
                        <p class="text-sm text-gray-900">{{ $dStartFmt ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-normal text-gray-500 uppercase tracking-wide mb-1">Due Date</p>
                        <p class="text-sm text-gray-900">{{ $dDueFmt ?: '—' }}</p>
                    </div>
                    {{-- Row 2 --}}
                    <div>
                        <p class="text-xs font-normal text-gray-500 uppercase tracking-wide mb-1">Priority</p>
                        <p class="text-sm text-gray-900">{{ $dPriority ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-normal text-gray-500 uppercase tracking-wide mb-1">Status</p>
                        <p class="text-sm text-gray-900">{{ $dStatus ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-normal text-gray-500 uppercase tracking-wide mb-1">Assigned By</p>
                        <p class="text-sm text-gray-900">{{ $dAssignedBy ?? '—' }}</p>
                    </div>
                    {{-- Row 3 --}}
                    <div style="grid-column:1/-1;">
                        <p class="text-xs font-normal text-gray-500 uppercase tracking-wide mb-1">Assigned To</p>
                        <div class="flex items-center gap-3">
                            @if (!empty($dAssigneeProfiles))
                                <x-avatar-group :profiles="$dAssigneeProfiles" :visible="3" overlap-class="-space-x-3" data-prefix="assignee" />
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <p class="text-sm font-normal text-gray-700 mb-2">Description</p>
                    <div
                        class="border border-gray-300 rounded-lg bg-gray-50/50 p-4 min-h-[120px] text-sm text-gray-800">
                        @if ($dDesc !== '')
                            {!! $dDesc !!}
                        @else
                            <span class="text-gray-400">Description...</span>
                        @endif
                    </div>
                </div>

                <div class="mb-6">
                    <p class="text-sm font-normal text-gray-700 mb-2">Comment</p>
                    <div class="border border-gray-300 rounded-lg bg-white p-4 min-h-[80px]">
                        @if ($commentError)
                            <div
                                class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                                {{ $commentError }}
                            </div>
                        @endif

                        <div class="flex flex-col gap-2 mb-4">
                            <div wire:ignore>
                                <x-rich-text-editor name="newComment" :value="''"
                                    placeholder="Write a comment..." />
                            </div>
                            <div class="flex justify-end">
                                <button type="button" wire:click="addComment"
                                    wire:target="addComment" wire:loading.attr="disabled"
                                    class="btn clr-bg-primary text-base-100 px-4">
                                    <span wire:loading.remove wire:target="addComment">Send</span>
                                    <span wire:loading wire:target="addComment"
                                        class="inline-flex items-center gap-2">
                                        <span class="loading loading-spinner loading-xs"></span>
                                        Sending...
                                    </span>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 max-h-60 overflow-y-auto pr-1">
                            @forelse($taskComments as $cmt)
                                @php
                                    $isMine = (int) ($cmt['accountId'] ?? 0) === (int) $currentUserId;
                                    $isEditing = (int) ($editingCommentId ?? 0) === (int) ($cmt['id'] ?? 0);
                                @endphp
                                <div id="task-cmt-{{ (int) ($cmt['id'] ?? 0) }}"
                                    class="rounded-lg border border-gray-200 p-3 bg-gray-50/40 transition-shadow">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ $cmt['accountName'] ?? 'User' }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                @if (!empty($cmt['createdAt']))
                                                    {{ \Carbon\Carbon::parse($cmt['createdAt'])->setTimezone('Asia/Manila')->format('M d, Y h:i A') }}
                                                @else
                                                    —
                                                @endif
                                            </p>
                                        </div>
                                        @if ($isMine)
                                            <div class="flex items-center gap-2">
                                                @if (!$isEditing)
                                                    <button type="button"
                                                        wire:click="startEditComment({{ (int) ($cmt['id'] ?? 0) }})"
                                                        class="text-xs text-blue-600 hover:underline">Edit</button>
                                                @endif
                                                <button type="button"
                                                    wire:click="deleteComment({{ (int) ($cmt['id'] ?? 0) }})"
                                                    wire:target="deleteComment({{ (int) ($cmt['id'] ?? 0) }})"
                                                    wire:loading.attr="disabled"
                                                    class="text-xs text-red-600 hover:underline">
                                                    <span wire:loading.remove
                                                        wire:target="deleteComment({{ (int) ($cmt['id'] ?? 0) }})">Delete</span>
                                                    <span wire:loading
                                                        wire:target="deleteComment({{ (int) ($cmt['id'] ?? 0) }})">Deleting...</span>
                                                </button>
                                            </div>
                                        @endif
                                    </div>

                                    @if ($isEditing)
                                        <div class="mt-2 flex flex-col gap-2 w-full"
                                            wire:key="task-cmt-edit-{{ (int) ($cmt['id'] ?? 0) }}">
                                            <x-rich-text-editor name="editingCommentContent"
                                                :value="$editingCommentContent" placeholder="Edit comment..." />
                                            <div class="flex flex-wrap items-center gap-2 justify-end">
                                                <button type="button"
                                                    wire:click="updateComment({{ (int) ($cmt['id'] ?? 0) }})"
                                                    wire:target="updateComment({{ (int) ($cmt['id'] ?? 0) }})"
                                                    wire:loading.attr="disabled"
                                                    class="btn btn-xs clr-bg-primary text-base-100">
                                                    <span wire:loading.remove
                                                        wire:target="updateComment({{ (int) ($cmt['id'] ?? 0) }})">Save</span>
                                                    <span wire:loading
                                                        wire:target="updateComment({{ (int) ($cmt['id'] ?? 0) }})">Saving...</span>
                                                </button>
                                                <button type="button" wire:click="cancelEditComment"
                                                    class="btn btn-xs">Cancel</button>
                                            </div>
                                        </div>
                                    @else
                                        <div class="mt-2 text-sm text-gray-800">
                                            {!! $cmt['content'] ?? '' !!}
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-400">No comments yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <script>
            const toDateOnly = (v) => (v || '').toString().trim().substring(0, 10);
            const toDateTimeLocal = (v) => (v || '').toString().trim().substring(0, 16); // YYYY-MM-DDTHH:MM

            function setOverloadWarnings(form, warnings) {
                const box = form?.querySelector('[data-overload-warnings]');
                const list = form?.querySelector('[data-overload-warnings-list]');
                if (!box || !list) return;

                const msgs = Array.isArray(warnings) ? warnings.filter(Boolean).map(String) : [];
                list.innerHTML = msgs.map(m => `<li>${m.replaceAll('<','&lt;').replaceAll('>','&gt;')}</li>`).join('');
                box.classList.toggle('hidden', msgs.length === 0);
            }

            function setDueCalcState(form, isCalculating) {
                const hint = form?.querySelector('[data-due-calc-hint]');
                if (!hint) return;
                hint.classList.toggle('hidden', !isCalculating);
                hint.classList.toggle('flex', isCalculating);
            }

            async function recalcDueAndWarnings(form) {
                if (!form) return;
                const startInput = form.querySelector('input[name="startDate"]');
                const spSelect = form.querySelector('select[name="storyPoints"]');
                const dueInput = form.querySelector('input[name="dueDate"]');
                const assignees = form.querySelector('input[name="assigneeIds"]');
                const projectId = form.querySelector('input[name="projectId"]');
                if (!startInput || !spSelect || !dueInput) return;

                const start = startInput.value;
                const sp = spSelect.value;
                if (!start || !sp) {
                    dueInput.min = '';
                    dueInput.max = '';
                    setOverloadWarnings(form, []);
                    setDueCalcState(form, false);
                    return;
                }

                try {
                    setDueCalcState(form, true);
                    // Use the full datetime-local value so the API can calculate correctly.
                    const startDateParam = toDateTimeLocal(start);
                    const aid = assignees?.value ? String(assignees.value) : '';
                    const pid = projectId?.value ? String(projectId.value) : '';
                    const url =
                        `/tasks/calculate-due-date?startDate=${encodeURIComponent(startDateParam)}&storyPoints=${encodeURIComponent(sp)}&assigneeIds=${encodeURIComponent(aid)}&projectId=${encodeURIComponent(pid)}`;
                    const r = await fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    });
                    if (!r.ok) return;
                    const data = await r.json();

                    if (data?.dueDate) {
                        const dueRaw = String(data.dueDate);
                        const maxDue = dueRaw.includes('T') ? toDateTimeLocal(dueRaw) : `${toDateOnly(dueRaw)}T23:59`;

                        let current = dueInput.value || maxDue;
                        if (current < start) current = start;
                        if (current > maxDue) current = maxDue;
                        dueInput.value = current;
                        dueInput.min = start;
                        dueInput.max = maxDue;
                    }

                    setOverloadWarnings(form, data?.warnings || []);
                } catch {
                    // ignore
                } finally {
                    setDueCalcState(form, false);
                }
            }

            // Expose helper so assignee toggles can trigger recalculation too.
            window.__tasksDueCalc = {
                recalc() {
                    const form = document.querySelector('form[data-due-calc="true"]');
                    recalcDueAndWarnings(form);
                }
            };

            document.addEventListener('change', async function(e) {
                const target = e.target;
                if (!target) return;
                const name = target.getAttribute('name');
                if (name !== 'startDate' && name !== 'storyPoints') return;
                const form = target.closest('form[data-due-calc="true"]');
                recalcDueAndWarnings(form);
            });
        </script>

        <script>
            // Keep a fixed-height scroll container, but don't clip dropdowns.
            (function() {
                const wrap = document.querySelector('[data-tasks-table-scroll]');
                if (!wrap) return;

                const enableOverflowVisible = () => {
                    wrap.classList.remove('overflow-y-auto');
                    wrap.classList.add('overflow-visible');
                };
                const disableOverflowVisible = () => {
                    wrap.classList.remove('overflow-visible');
                    wrap.classList.add('overflow-y-auto');
                };

                document.addEventListener('click', (e) => {
                    const t = e.target;
                    if (!(t instanceof Element)) return;

                    if (t.closest('[data-action-dropdown-trigger]')) {
                        enableOverflowVisible();
                        return;
                    }
                    if (t.closest('[data-action-dropdown-menu]')) return;
                    disableOverflowVisible();
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') disableOverflowVisible();
                });
            })();
        </script>
        <form method="dialog" class="modal-backdrop">
            <button type="button" wire:click="closeTaskDetail">close</button>
        </form>
    </dialog>

    {{-- Delete confirmation modal --}}
    <dialog class="{{ $showDeleteConfirmModal ? 'modal modal-open' : 'modal' }}">
        <div class="modal-box max-w-sm">
            <h3 class="text-lg font-normal">Confirm Delete</h3>
            <p class="py-4 text-sm text-gray-700">
                Are you sure you want to delete {{ $pendingDeleteTaskName ?? 'this task' }}?
            </p>

            <div class="modal-action">
                <button type="button" class="btn btn-ghost p-4" wire:click="cancelDeleteTask">Cancel</button>
                <button type="button" class="btn bg-red-600 hover:bg-red-700 text-base-100 border-none p-4"
                    wire:click="deleteTask({{ (int) ($pendingDeleteTaskId ?? 0) }})">
                    Delete
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="button" wire:click="cancelDeleteTask">close</button>
        </form>
    </dialog>

    <div class="{{ $viewMode !== 'list' ? 'hidden' : '' }} h-[80vh] relative overflow-y-auto"
        data-tasks-table-scroll>
        <table class="table w-full table-fixed border-collapse">
            <colgroup>
                <col class="w-8"><!-- logical col 1 of 3 (tree cell uses colspan) -->
                <col class="w-10">
                <col><!-- Task name / flex remainder -->
                <col class="w-1/5"><!-- Assignee -->
                <col class="w-36"><!-- Due Date -->
                <col style="width: 8rem; max-width: 8rem;"><!-- Story Point -->
                <col style="width: 11.5rem; max-width: 11.5rem;"><!-- Status -->
                <col style="width: 7.5rem; min-width: 7.5rem;"><!-- Priority -->
                <col style="width: 5.5rem; min-width: 5.5rem;"><!-- Action -->
            </colgroup>
            <thead>
                <tr class="bg-base-200">
                    <th colspan="3" class="sticky top-0 z-10 bg-base-200 !font-normal pl-0 text-left">Task Name</th>
                    <th class="sticky top-0 z-10 bg-base-200 !font-normal">Assignee</th>
                    <th class="sticky top-0 z-10 bg-base-200 !font-normal">Due Date</th>
                    <th class="sticky top-0 z-10 bg-base-200 !font-normal">Story Point</th>
                    <th class="sticky top-0 z-10 bg-base-200 !font-normal">Status</th>
                    <th class="sticky top-0 z-10 bg-base-200 !font-normal">Priority</th>
                    <th class="sticky top-0 z-10 bg-base-200 !font-normal">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @php
                    $fmt = function (array $task) use ($accountMap, $accountProfiles, $taskPriorityMap) {
                        $taskName = $task['name'] ?? ($task['title'] ?? '');

                        // Resolve assignee name: prefer API-provided name fields,
                        // then look up each ID in the accounts map
                        $assignee =
                            $task['assigneeName'] ?? ($task['assignedToName'] ?? ($task['reporterName'] ?? null));
                        $assigneeProfiles = [];
                        if ($assignee === null || $assignee === '') {
                            $ids = $task['assigneeIds'] ?? ($task['assigneeId'] ?? []);
                            if (!is_array($ids)) {
                                $ids = [$ids];
                            }
                            $names = [];
                            foreach ($ids as $aid) {
                                if ($aid && isset($accountMap[(int) $aid])) {
                                    $names[] = $accountMap[(int) $aid];
                                }
                            }
                            $assignee = implode(', ', $names);
                        }

                        // Build assignee avatar profiles from IDs (if present).
                        // API can send many shapes/keys: array, scalar, "1,2", or array of objects.
                        $rawIds =
                            $task['assigneeIds']
                            ?? ($task['assigneeIDs'] ?? null)
                            ?? ($task['AssigneeIds'] ?? null)
                            ?? ($task['assigneeId'] ?? null)
                            ?? ($task['AssigneeId'] ?? null)
                            ?? [];

                        if (is_string($rawIds)) {
                            $rawIds = preg_split('/[,\s;]+/', $rawIds);
                            $rawIds = array_filter(array_map('trim', $rawIds));
                        } elseif (!is_array($rawIds)) {
                            $rawIds = [$rawIds];
                        }

                        $profilesById = [];
                        foreach ($rawIds as $aid) {
                            if (is_array($aid)) {
                                $aid = $aid['id'] ?? $aid['Id'] ?? $aid['accountId'] ?? $aid['userId'] ?? null;
                            }

                            $aidInt = (int) $aid;
                            if ($aidInt <= 0) {
                                continue;
                            }

                            if (isset($accountProfiles[$aidInt])) {
                                $profilesById[$aidInt] = array_merge(['id' => $aidInt], $accountProfiles[$aidInt]);
                                continue;
                            }

                            // Fallback avatar when accounts list doesn't include this id.
                            $name = $accountMap[$aidInt] ?? null;
                            $parts = preg_split('/\s+/', trim((string) ($name ?? '')));
                            $parts = array_values(array_filter($parts, fn ($p) => is_string($p) && trim($p) !== ''));
                            $first = (string) ($parts[0] ?? '');
                            $last = (string) (!empty($parts) ? implode(' ', array_slice($parts, 1)) : '');
                            $a0 = mb_substr(trim($first), 0, 1);
                            $b0 = mb_substr(trim($last), 0, 1);
                            if ($a0 !== '' && $b0 !== '') {
                                $initials = mb_strtoupper($a0.$b0);
                            } elseif ($a0 !== '') {
                                $initials = mb_strtoupper($a0);
                            } else {
                                $initials = '?';
                            }

                            $profilesById[$aidInt] = [
                                'id' => $aidInt,
                                'profilePicture' => null,
                                'initials' => $initials,
                                'name' => $name,
                            ];
                        }
                        // IDs are already unique because we key by id.
                        $assigneeProfiles = array_values($profilesById);
                        $dueDateRaw = $task['dueDate'] ?? ($task['dueAt'] ?? null);
                        $storyPoints = $task['storyPoints'] ?? ($task['storyPoint'] ?? ($task['points'] ?? null));
                        $status = $task['statusName'] ?? ($task['status'] ?? '');
                        $priority = $task['priorityName'] ?? ($task['priority'] ?? '');
                        if ($priority === '' && isset($task['priorityId'])) {
                            $priority =
                                $taskPriorityMap[(int) ($task['priorityId'] ?? ($task['PriorityId'] ?? 0))] ?? '';
                        }

                        $statusBadge = match ($status) {
                            'Not Started' => 'badge-ghost rounded-none',
                            'In Progress' => 'badge-info rounded-none',
                            'For Review' => 'badge-warning rounded-none',
                            'Completed' => 'badge-success rounded-none',
                            default => 'badge-ghost rounded-none',
                        };
                        $id = $task['id'] ?? ($task['Id'] ?? null);

                        $statusStyle = match ($status) {
                            'Not Started' => 'background:#fee2e2;color:#ef4444;',
                            'In Progress' => 'background:#dbeafe;color:#3b82f6;',
                            'For Review' => 'background:#e5e7eb;color:#374151;',
                            'Completed' => 'background:#dcfce7;color:#22c55e;',
                            default => 'background:#f3f4f6;color:#374151;',
                        };

                        $priorityStyle = match ($priority) {
                            'Urgent' => 'background:#fee2e2;color:#ef4444;',
                            'Important' => 'background:#fce7f3;color:#ec4899;',
                            'Medium' => 'background:#dbeafe;color:#3b82f6;',
                            'Low' => 'background:#e5e7eb;color:#374151;',
                            default => 'background:#f3f4f6;color:#6b7280;',
                        };

                        return compact(
                            'id',
                            'taskName',
                            'assignee',
                            'assigneeProfiles',
                            'dueDateRaw',
                            'storyPoints',
                            'status',
                            'priority',
                            'statusBadge',
                            'priorityStyle',
                            'statusStyle',
                        );
                    };
                @endphp

                @if ($loading)
                    @foreach (range(1, 6) as $i)
                        <tr>
                            <td colspan="3" class="py-2 pr-2">
                                <div class="flex items-center gap-2 pl-0">
                                    <div class="h-6 w-6 shrink-0 rounded bg-gray-200 animate-pulse"></div>
                                    <div class="h-5 w-5 shrink-0 rounded bg-gray-200 animate-pulse"></div>
                                    <div class="h-4 flex-1 max-w-xs rounded bg-gray-200 animate-pulse"></div>
                                </div>
                            </td>
                            <td>
                                <div class="h-6 w-6 bg-gray-200 rounded-full animate-pulse"></div>
                            </td>
                            <td>
                                <div class="h-4 bg-gray-200 rounded animate-pulse w-24"></div>
                            </td>
                            <td>
                                <div class="h-4 bg-gray-200 rounded animate-pulse w-8"></div>
                            </td>
                            <td>
                                <div class="h-6 bg-gray-200 rounded animate-pulse w-28"></div>
                            </td>
                            <td>
                                <div class="h-6 bg-gray-200 rounded animate-pulse w-16"></div>
                            </td>
                            <td></td>
                        </tr>
                    @endforeach
                @else
                    @if (($currentParentTaskId ?? null) !== null)
                        @forelse($listRows ?? [] as $row)
                            @php
                                $rowType = (string) ($row['type'] ?? 'task');
                            @endphp
                            @if ($rowType === 'add')
                                @php
                                    $addParentId = (int) ($row['parentId'] ?? 0);
                                    $addDepth = (int) ($row['depth'] ?? 0);
                                    $addIndentPx = max(0, $addDepth) * 20;
                                    $addLabelText = (string) ($row['label'] ?? 'Add subtask');
                                @endphp
                                <tr class="hover:bg-blue-50 cursor-pointer" wire:click="addSubtask({{ $addParentId }})">
                                    <td colspan="3" class="py-2 pr-2 align-middle">
                                        <div class="flex min-w-0 items-center gap-2 pl-0">
                                            <span class="inline-block h-6 w-6 shrink-0"
                                                style="margin-left: {{ $addIndentPx }}px" aria-hidden="true"></span>
                                            <span class="text-sm font-medium clr-primary">{{ $addLabelText }}</span>
                                        </div>
                                    </td>
                                    <td colspan="6"></td>
                                </tr>
                                @continue
                            @endif
                            @php
                                $taskRow = (array) ($row['task'] ?? []);
                                $p = $fmt($taskRow);
                                $rowId = (int) ($row['id'] ?? 0);
                                $depth = (int) ($row['depth'] ?? 0);
                                $canToggle = (bool) ($row['canToggle'] ?? false);
                                $hasChildren = (bool) ($row['hasChildren'] ?? false);
                                $isExpanded = $rowId > 0 && ($expanded[$rowId] ?? false);
                                $indentPx = max(0, $depth) * 20;
                                $addChildLabel = match ($depth) {
                                    0 => 'Add grandchild task',
                                    1 => 'Add great grandchild task',
                                    default => 'Add subtask',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50 cursor-pointer" wire:click="openTaskDetail({{ $rowId }})">
                                <td colspan="3" class="py-2 pr-2 align-middle">
                                    <div class="flex min-w-0 items-center gap-2 pl-0">
                                        <div class="flex h-6 w-6 shrink-0 items-center justify-center"
                                            style="margin-left: {{ $indentPx }}px" wire:click.stop>
                                            @if ($canToggle)
                                                <button type="button"
                                                    class="btn btn-ghost btn-xs flex h-6 w-6 items-center justify-center p-0"
                                                    wire:click.stop="toggle({{ $rowId }})"
                                                    title="{{ $isExpanded ? 'Collapse' : 'Expand' }}">
                                                    <svg class="h-3.5 w-3.5 text-gray-500" fill="none" stroke="currentColor"
                                                        style="transition:transform 180ms ease;transform:rotate({{ $isExpanded ? '0' : '-90' }}deg);"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 items-center pr-1" wire:click.stop>
                                            <x-checkbox :task-id="$p['id'] ?? 0" :initial-status="$p['status'] ?? ''" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <span class="font-normal">{{ $p['taskName'] }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @php $profiles = is_array($p['assigneeProfiles'] ?? null) ? $p['assigneeProfiles'] ?? [] : []; @endphp
                                    @if (!empty($profiles))
                                        <x-avatar-group :profiles="$profiles" :visible="3" overlap-class="-space-x-3"
                                            data-prefix="assignee" />
                                    @else
                                        <span class="text-sm">{{ $p['assignee'] ?: '—' }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($p['dueDateRaw'])
                                        {{ \Carbon\Carbon::parse($p['dueDateRaw'])->format('Y-m-d') }}
                                    @endif
                                </td>
                                <td>{{ $p['storyPoints'] ?? '' }}</td>
                                <td wire:click.stop class="w-0 max-w-[11.5rem] min-w-[10rem] pr-2 overflow-visible">
                                    <div x-data="{
                                        status: '{{ addslashes($p['status']) }}',
                                        styles: {
                                            'Not Started': 'background:#fee2e2;color:#ef4444;',
                                            'In Progress': 'background:#dbeafe;color:#3b82f6;',
                                            'For Review': 'background:#e5e7eb;color:#374151;',
                                            'Completed': 'background:#dcfce7;color:#22c55e;',
                                        },
                                        get pill() { return this.styles[this.status] || 'background:#f3f4f6;color:#374151;'; }
                                    }"
                                        class="relative inline-flex items-center rounded-none pl-6 pr-2 py-1 w-full min-w-0 overflow-visible"
                                        :style="pill">
                                        <span
                                            class="absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none flex items-center shrink-0 w-1.5 h-1.5">
                                            <x-icons.circle />
                                        </span>
                                        <select x-model="status"
                                            class="text-xs font-medium border-0 ring-0 shadow-none outline-none focus:ring-0 focus:outline-none cursor-pointer bg-transparent appearance-none pl-5 pr-1 py-1 w-full min-w-0"
                                            style="border:none;box-shadow:none;"
                                            @change="Livewire.dispatch('task-status-changed', { taskId: {{ $rowId }}, newStatus: status })">
                                            @foreach ($boardStatuses as $s)
                                                <option value="{{ $s }}">{{ $s }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                                <td class="min-w-[5rem] pl-1">
                                    <span class="px-2 py-0.5 text-xs"
                                        style="display:flex;align-items:center;justify-content:center;gap:0.5rem;height:2rem;width:6rem;{{ $p['priorityStyle'] }}"><span>•</span>
                                        {{ $p['priority'] }}</span>
                                </td>
                                <td wire:click.stop>
                                    <div class="dropdown dropdown-end">
                                        <button tabindex="0" type="button" class="btn btn-ghost btn-sm px-2"
                                            data-action-dropdown-trigger>
                                            <x-icons.three-dot classes="w-5 h-5" />
                                        </button>
                                        <ul tabindex="0"
                                            class="dropdown-content menu bg-base-100 rounded-box z-50 w-40 p-2 shadow-lg border">
                                            @if ($hasChildren)
                                                <li class="border-b border-gray-100">
                                                    <button type="button" wire:click.stop="toggle({{ $rowId }})">
                                                        {{ $isExpanded ? 'Collapse' : 'Expand' }}
                                                    </button>
                                                </li>
                                            @endif
                                            <li class="border-b border-gray-100">
                                                <button type="button" wire:click.stop="openTaskDetail({{ $rowId }})">
                                                    Details
                                                </button>
                                            </li>
                                            <li class="border-b border-gray-100">
                                                <button type="button" wire:click.stop="addSubtask({{ $rowId }})">
                                                    {{ $addChildLabel }}
                                                </button>
                                            </li>
                                            @if (!empty($canDeleteTasks))
                                                <li class="border-b border-gray-100">
                                                    <button type="button" class="text-red-600 hover:text-red-700"
                                                        wire:click.stop="confirmDeleteTask({{ $rowId }})">
                                                        Delete
                                                    </button>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500">
                                    No tasks in this level yet.
                                </td>
                            </tr>
                        @endforelse
                    @else
                        @forelse($filteredTasks as $taskRow)
                            @php
                                $p = $fmt($taskRow);
                                $rowId = (int) ($p['id'] ?? 0);
                                $childCount = count($childrenMap[$rowId] ?? []);
                                $hasChildren = $childCount > 0;
                            @endphp
                            <tr class="hover:bg-gray-50 cursor-pointer"
                                wire:click="{{ $hasChildren ? 'enterTaskLevel('.$rowId.')' : 'openTaskDetail('.$rowId.')' }}">
                                <td colspan="3" class="py-2 pr-2 align-middle">
                                    <div class="flex min-w-0 items-center gap-2 pl-0">
                                        <div class="flex h-6 w-6 shrink-0 items-center justify-center">
                                            @if ($hasChildren)
                                                <svg class="h-3.5 w-3.5 text-gray-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 5l7 7-7 7" />
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 items-center pr-1" wire:click.stop>
                                            <x-checkbox :task-id="$p['id'] ?? 0" :initial-status="$p['status'] ?? ''" />
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <span class="font-normal">{{ $p['taskName'] }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @php $profiles = is_array($p['assigneeProfiles'] ?? null) ? $p['assigneeProfiles'] ?? [] : []; @endphp
                                    @if (!empty($profiles))
                                        <x-avatar-group :profiles="$profiles" :visible="3" overlap-class="-space-x-3"
                                            data-prefix="assignee" />
                                    @else
                                        <span class="text-sm">{{ $p['assignee'] ?: '—' }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($p['dueDateRaw'])
                                        {{ \Carbon\Carbon::parse($p['dueDateRaw'])->format('Y-m-d') }}
                                    @endif
                                </td>
                                <td>{{ $p['storyPoints'] ?? '' }}</td>
                                <td wire:click.stop class="w-0 max-w-[11.5rem] min-w-[10rem] pr-2 overflow-visible">
                                    <div x-data="{
                                        status: '{{ addslashes($p['status']) }}',
                                        styles: {
                                            'Not Started': 'background:#fee2e2;color:#ef4444;',
                                            'In Progress': 'background:#dbeafe;color:#3b82f6;',
                                            'For Review': 'background:#e5e7eb;color:#374151;',
                                            'Completed': 'background:#dcfce7;color:#22c55e;',
                                        },
                                        get pill() { return this.styles[this.status] || 'background:#f3f4f6;color:#374151;'; }
                                    }"
                                        class="relative inline-flex items-center rounded-none pl-6 pr-2 py-1 w-full min-w-0 overflow-visible"
                                        :style="pill">
                                        <span
                                            class="absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none flex items-center shrink-0 w-1.5 h-1.5">
                                            <x-icons.circle />
                                        </span>
                                        <select x-model="status"
                                            class="text-xs font-medium border-0 ring-0 shadow-none outline-none focus:ring-0 focus:outline-none cursor-pointer bg-transparent appearance-none pl-5 pr-1 py-1 w-full min-w-0"
                                            style="border:none;box-shadow:none;"
                                            @change="Livewire.dispatch('task-status-changed', { taskId: {{ $rowId }}, newStatus: status })">
                                            @foreach ($boardStatuses as $s)
                                                <option value="{{ $s }}">{{ $s }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                                <td class="min-w-[5rem] pl-1">
                                    <span class="px-2 py-0.5 text-xs"
                                        style="display:flex;align-items:center;justify-content:center;gap:0.5rem;height:2rem;width:6rem;{{ $p['priorityStyle'] }}"><span>•</span>
                                        {{ $p['priority'] }}</span>
                                </td>
                                <td wire:click.stop>
                                    <div class="dropdown dropdown-end">
                                        <button tabindex="0" type="button" class="btn btn-ghost btn-sm px-2"
                                            data-action-dropdown-trigger>
                                            <x-icons.three-dot classes="w-5 h-5" />
                                        </button>
                                        <ul tabindex="0"
                                            class="dropdown-content menu bg-base-100 rounded-box z-50 w-40 p-2 shadow-lg border">
                                            @if ($hasChildren)
                                                <li class="border-b border-gray-100">
                                                    <button type="button" wire:click.stop="enterTaskLevel({{ $rowId }})">
                                                        Open
                                                    </button>
                                                </li>
                                            @endif
                                            <li class="border-b border-gray-100">
                                                <button type="button" wire:click.stop="openTaskDetail({{ $rowId }})">
                                                    Details
                                                </button>
                                            </li>
                                            <li class="border-b border-gray-100">
                                                <button type="button" wire:click.stop="addSubtask({{ $rowId }})">
                                                    Add subtask
                                                </button>
                                            </li>
                                            @if (!empty($canDeleteTasks))
                                                <li class="border-b border-gray-100">
                                                    <button type="button" class="text-red-600 hover:text-red-700"
                                                        wire:click.stop="confirmDeleteTask({{ $rowId }})">
                                                        Delete
                                                    </button>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500">
                                    No tasks in this level yet.
                                </td>
                            </tr>
                        @endforelse
                    @endif
                @endif
            </tbody>
        </table>
    </div>

    {{-- Board / Kanban View --}}
    @php
        $colStyles = [
            'Not Started' => 'bg-gray-100 border-gray-300',
            'In Progress' => 'bg-blue-50 border-blue-300',
            'For Review' => 'bg-yellow-50 border-yellow-300',
            'Completed' => 'bg-green-50 border-green-300',
        ];
        $priorityStyle = [
            'Urgent' => 'background:#fee2e2;color:#ef4444;',
            'Important' => 'background:#fce7f3;color:#ec4899;',
            'Medium' => 'background:#dbeafe;color:#3b82f6;',
            'Low' => 'background:#e5e7eb;color:#374151;',
        ];
    @endphp
    <div
        class="{{ $viewMode !== 'board' ? 'hidden' : '' }} flex items-stretch gap-4 w-full p-4 overflow-x-auto rounded-lg">
        @if ($loading)
            @foreach (range(1, 4) as $i)
                <div class="flex flex-col flex-1 min-w-[260px] max-w-[320px] rounded-lg shrink-0">
                    <div class="h-10 bg-gray-200 rounded-lg animate-pulse mb-3"></div>
                    @foreach (range(1, 3) as $j)
                        <div class="bg-white rounded-lg border-2 border-gray-100 p-4 mb-2 flex flex-col gap-3">
                            <div class="h-4 bg-gray-200 rounded animate-pulse w-3/4"></div>
                            <div class="h-4 bg-gray-200 rounded animate-pulse w-1/2"></div>
                            <div class="h-4 bg-gray-200 rounded animate-pulse w-1/4"></div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            @foreach ($boardStatuses as $status)
                @php $statusJs = addslashes($status); @endphp
                <div x-data="{ dragOver: false }"
                    class="flex flex-col gap-2 flex-1 min-w-[260px] max-w-[320px] rounded-lg border border-gray-200 bg-white transition-all duration-150 shrink-0"
                    :class="dragOver ? 'ring-2 ring-blue-400 bg-blue-50/40' : ''" @dragover.prevent
                    @dragenter.prevent="dragOver = true"
                    @dragleave="if (!$event.relatedTarget || !$el.contains($event.relatedTarget)) dragOver = false"
                    @drop.prevent="dragOver = false; var id = parseInt($event.dataTransfer.getData('text/plain')); if (id) Livewire.dispatch('task-status-changed', { taskId: id, newStatus: '{{ $statusJs }}' })">
                    <div
                        class="flex items-center justify-between px-3 py-2 rounded-lg border shrink-0 {{ $colStyles[$status] ?? 'bg-gray-100 border-gray-300' }}">
                        <span class="font-normal text-sm">{{ $status }}</span>
                        <span class="badge badge-sm">{{ count($boardGrouped[$status] ?? []) }}</span>
                    </div>
                    <div class="flex flex-col gap-2 p-3 rounded-lg border border-gray-200 min-h-[80vh]">
                        @foreach ($boardGrouped[$status] ?? [] as $task)
                            @php $boardTaskId = (int)($task['id'] ?? $task['Id'] ?? 0); @endphp
                            @php
                                $boardChildCount = count(($childrenMap ?? [])[$boardTaskId] ?? []);
                                $boardHasChildren = $boardChildCount > 0;
                            @endphp
                            <div x-data="{ dragging: false, dragStarted: false, moved: false }"
                                class="bg-white rounded-lg border-2 border-gray-200 shadow-sm overflow-visible hover:shadow-md transition-shadow cursor-pointer"
                                draggable="true" :style="dragging ? 'opacity:0.4' : ''" @mousedown="moved = false"
                                @mousemove="moved = true"
                                @dragstart="if (!moved || $event.target?.closest('.kanban-no-drag')) { $event.preventDefault(); return; } dragStarted = true; dragging = true; $event.dataTransfer.setData('text/plain', '{{ $boardTaskId }}'); $event.dataTransfer.effectAllowed = 'move'"
                                @dragend="dragStarted = false; dragging = false; moved = false">
                                <div
                                    class="block p-4 flex flex-col gap-3 min-h-full no-underline text-inherit hover:bg-gray-50/50"
                                    wire:click="{{ $boardHasChildren ? 'enterTaskLevel('.$boardTaskId.')' : 'openTaskDetail('.$boardTaskId.')' }}">
                                    @php
                                        $cardStatus = $task['statusName'] ?? ($task['status'] ?? '');
                                        $cardStatusStyle = match ($cardStatus) {
                                            'Not Started' => 'background:#fee2e2;color:#ef4444;',
                                            'In Progress' => 'background:#dbeafe;color:#3b82f6;',
                                            'For Review' => 'background:#e5e7eb;color:#374151;',
                                            'Completed' => 'background:#dcfce7;color:#22c55e;',
                                            default => 'background:#f3f4f6;color:#374151;',
                                        };
                                    @endphp
                                    @if ($cardStatus !== '')
                                    <div class="flex justify-between">
                                        <span class="inline-flex items-center gap-2 rounded-xl px-4 py-0.5 text-[11px] font-medium"
                                            style="{{ $cardStatusStyle }}">
                                           <span>•</span> <span>{{ $cardStatus }}</span>
                                        </span>
                                        <button type="button" draggable="false" @mousedown.stop
                                            @dragstart.stop.prevent x-data="{ loading: false }"
                                            @click.stop="if(!loading){ loading = true; $wire.openTaskDetail({{ $boardTaskId }}).then(() => loading = false).catch(() => loading = false) }"
                                            :disabled="loading"
                                            class="kanban-no-drag btn btn-ghost btn-sm px-2 py-1 min-h-0 h-7 w-7 hover:bg-base-200 hover:text-base-content rounded-lg relative">
                                            <span x-show="!loading">
                                                <x-icons.three-dot classes="w-4 h-4" />
                                            </span>
                                            <span x-show="loading"
                                                class="loading loading-spinner loading-xs text-base-content"></span>
                                        </button>
                                    </div>
                                    @endif
                                    <div class="flex items-start justify-between gap-2">
                                        <span
                                            class="font-medium text-xl leading-snug">{{ $task['name'] ?? ($task['title'] ?? '') }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5 items-center">
                                        @if (!empty($task['priority']))
                                            <span class="px-2 py-0.5 text-xs"
                                                style="display:flex;align-items:center;justify-content:center;width:6rem;{{ $priorityStyle[$task['priority']] ?? 'background:#f3f4f6;color:#6b7280;' }}">•
                                                {{ $task['priority'] }}</span>
                                        @endif
                                    </div>
                                    <div class="flex flex-col text-xs text-gray-500 mt-2 gap-1">
                                        @php
                                            // Prefer API-provided profiles if present (same shape as list view),
                                            // otherwise build from ids. Some APIs send assigneeIds as "1,2,3".
                                            $profiles = is_array($task['assigneeProfiles'] ?? null)
                                                ? $task['assigneeProfiles'] ?? []
                                                : [];

                                            if (empty($profiles)) {
                                                $rawIds =
                                                    $task['assigneeIds']
                                                    ?? ($task['assigneeIDs'] ?? null)
                                                    ?? ($task['AssigneeIds'] ?? null)
                                                    ?? ($task['assigneeId'] ?? null)
                                                    ?? ($task['AssigneeId'] ?? null)
                                                    ?? [];

                                                // Normalize into a flat array of scalar IDs.
                                                if (is_string($rawIds)) {
                                                    // Handle "1,2,3" or "1;2;3" or "1 2 3"
                                                    $rawIds = preg_split('/[,\s;]+/', $rawIds);
                                                    $rawIds = array_filter(array_map('trim', $rawIds));
                                                } elseif (!is_array($rawIds)) {
                                                    $rawIds = [$rawIds];
                                                }

                                                $ids = [];
                                                foreach ($rawIds as $aid) {
                                                    if (is_array($aid)) {
                                                        $aid = $aid['id'] ?? $aid['Id'] ?? $aid['accountId'] ?? $aid['userId'] ?? null;
                                                    }
                                                    if ($aid === null || $aid === '') {
                                                        continue;
                                                    }
                                                    $ids[] = (int) $aid;
                                                }
                                                $ids = array_values(array_unique(array_filter($ids, fn($v) => $v > 0)));

                                                $profilesById = [];
                                                foreach ($ids as $aidInt) {
                                                    if (isset($accountProfiles[$aidInt])) {
                                                        $profilesById[$aidInt] = array_merge(['id' => $aidInt], $accountProfiles[$aidInt]);
                                                        continue;
                                                    }

                                                    $name = $accountMap[$aidInt] ?? null;
                                                    $parts = preg_split('/\s+/', trim((string) ($name ?? '')));
                                                    $parts = array_values(array_filter($parts, fn($p) => is_string($p) && trim($p) !== ''));
                                                    $first = (string) ($parts[0] ?? '');
                                                    $last = (string) (! empty($parts) ? implode(' ', array_slice($parts, 1)) : '');
                                                    $a0 = mb_substr(trim($first), 0, 1);
                                                    $b0 = mb_substr(trim($last), 0, 1);
                                                    if ($a0 !== '' && $b0 !== '') {
                                                        $initials = mb_strtoupper($a0.$b0);
                                                    } elseif ($a0 !== '') {
                                                        $initials = mb_strtoupper($a0);
                                                    } else {
                                                        $initials = '?';
                                                    }

                                                    // Fallback avatar when accountProfiles is missing this id.
                                                    $profilesById[$aidInt] = [
                                                        'id' => $aidInt,
                                                        'profilePicture' => null,
                                                        'initials' => $initials,
                                                        'name' => $name,
                                                    ];
                                                }

                                                $profiles = array_values($profilesById);
                                            }

                                            // IDs are already unique because we key by id.
                                            $assigneeCount = count($profiles);
                                            $subtaskCount = (int) ($boardChildCount ?? 0);
                                        @endphp
                                        <div class="flex flex-row border-b-2 py-2 items-center justify-between">
                                            <span class="block text-[11px] font-medium text-gray-500 mb-1">Assignees:</span>
                                            @if ($assigneeCount > 0)
                                                <x-avatar-group :profiles="$profiles" :visible="3" overlap-class="-space-x-3" data-prefix="assignee" />
                                            @else
                                                <span class="text-[11px] text-gray-400">—</span>
                                            @endif
                                        </div>

                                        <div class="flex items-center justify-between mt-1">
                                            <span class="text-[11px] text-gray-500">
                                                {{ $subtaskCount }} subtask{{ $subtaskCount === 1 ? '' : 's' }}
                                            </span>
                                            @if (!empty($task['dueDate']) || !empty($task['dueAt']))
                                                <span class="text-[11px] text-gray-500">
                                                    {{ \Carbon\Carbon::parse($task['dueDate'] ?? $task['dueAt'])->format('M d') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @if (empty($boardGrouped[$status]))
                            <div class="text-center text-xs text-gray-400 py-6">No tasks</div>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </div>

</div>
