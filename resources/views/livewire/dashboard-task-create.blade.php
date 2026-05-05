<div>
    {{-- Success toast: parent Dashboard shows session flash (avoid double mount consuming flash). --}}

    @php
        // Keep modal heights consistent between "select project" and "create task".
        $modalBoxClass = 'modal-box w-11/12 max-w-5xl overflow-y-auto h-[40rem]';
    @endphp

    {{-- Step 1: Select project --}}
    <dialog class="{{ $showSelectProjectModal ? 'modal modal-open' : 'modal' }}">
        <div class="modal-box w-11/12 max-w-5xl overflow-y-auto">
            <div class="modal-action mt-0 mb-2">
                <button type="button" wire:click="closeAll" class="btn btn-sm">✕</button>
            </div>
            <h3 class="font-bold text-lg">Select a project</h3>
            <p class="text-sm text-gray-500 mt-1">Choose a project first before creating a task.</p>

            <div class="mt-4 border rounded-lg overflow-hidden h-[35rem]">
                <div class="h-full overflow-y-auto">
                    @forelse($projects as $p)
                        @php
                            $pid = (int) ($p['id'] ?? ($p['Id'] ?? 0));
                            $pname = $p['name'] ?? ($p['Name'] ?? ($p['title'] ?? 'Project'));
                        @endphp
                        @if ($pid > 0)
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
    <dialog class="{{ $showAddTaskModal ? 'modal modal-open' : 'modal' }}"
        wire:key="dashboard-add-task-modal-{{ count($taskPriorityMap ?? []) }}">
        <div class="{{ $modalBoxClass }}">
            <div class="modal-action mt-0 mb-2 flex justify-between items-center">
                <button type="button" wire:click="backToProjectSelect" class="btn btn-sm">Back</button>
                <button type="button" wire:click="closeAll" class="btn btn-sm">✕</button>
            </div>
            <h3 class="font-bold text-lg">New Task</h3>

            @php
                $liveErrs = $errors->any()
                    ? array_values(array_filter(array_unique(array_map('trim', $errors->all()))))
                    : [];
                $modalErrorMessages = array_values(array_unique(array_merge($flashErrorMessages, $liveErrs)));
            @endphp
            @if (!empty($modalErrorMessages))
                <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 mt-3 text-sm text-red-700">
                    <p class="font-semibold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($modalErrorMessages as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($selectedProjectId)
                <form method="POST" action="{{ route('tasks.store', $selectedProjectId) }}"
                    class="mt-4 flex flex-col gap-4" data-due-calc="true">
                    @csrf
                    <input type="hidden" name="projectId" value="{{ (int) $selectedProjectId }}" />
                    <input type="hidden" name="redirect_to" value="dashboard" />

                    {{-- Assignees --}}
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
                            queueMicrotask(() => window.__dashDueCalc?.recalc?.(this.$root?.closest('form')));
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
                                        if (
                                            $apic &&
                                            !str_starts_with($apic, 'http') &&
                                            !str_starts_with($apic, 'data:')
                                        ) {
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
                                            <x-person-option name="{{ $aname }}" :email="$aemail"
                                                :picture="$apic" :bio="$abio" :specialization="$aspec"
                                                initials="{{ $ainitials }}"
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
                                @if (empty($assignableAccounts))
                                    <li class="px-3 py-2 text-sm text-gray-400">No members found for this project.</li>
                                @endif
                            </ul>
                        </div>
                        <input type="hidden" name="assigneeIds" :value="selectedIds.join(',')" />
                    </div>

                    {{-- Task Name --}}
                    <div class="flex flex-col gap-1">
                        <label class="font-medium text-sm">Task Name</label>
                        <input name="name" type="text" placeholder="Enter task name"
                            class="input input-bordered !rounded-lg w-full {{ $errors->has('name') ? 'border-red-500' : '' }}"
                            style="border-radius:0.5rem;" value="{{ old('name') }}" required />
                        @foreach ($errors->get('name') as $msg)
                            <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
                        @endforeach
                    </div>

                    {{-- Priority | Story Point | Start Date | Due Date --}}
                    <div class="flex flex-wrap gap-4">
                        <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
                            <label class="font-medium text-sm">Priority <span class="text-red-500">*</span></label>
                            <select name="priorityId"
                                class="select select-bordered !rounded-lg w-full text-gray-900 bg-white {{ $errors->has('priorityId') ? 'border-red-500' : '' }}"
                                style="border-radius:0.5rem;" required
                                wire:key="dashboard-priority-select-{{ count($taskPriorityMap ?? []) }}">
                                <option value="">Select priority</option>
                                @foreach ($taskPriorityMap ?? [] as $pid => $pname)
                                    <option value="{{ $pid }}"
                                        {{ (string) old('priorityId') === (string) $pid ? 'selected' : '' }}>
                                        {{ $pname }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex flex-col gap-1 flex-1 min-w-[120px]">
                            <label class="font-medium text-sm">Story Point</label>
                            @php $storyPointOptions = [1,2,3,5,8,13,21]; @endphp
                            <select name="storyPoints"
                                onchange="window.__dashDueCalc?.recalc?.(this.form)"
                                class="select select-bordered !rounded-lg w-full text-gray-900 bg-white"
                                style="border-radius:0.5rem;">
                                <option value="">Select</option>
                                @foreach ($storyPointOptions as $sp)
                                    <option value="{{ $sp }}" @selected(old('storyPoints') == $sp)>
                                        {{ $sp }}</option>
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
                                onchange="window.__dashDueCalc?.recalc?.(this.form)"
                                oninput="window.__dashDueCalc?.recalc?.(this.form)"
                                class="input input-bordered !rounded-lg w-full {{ $errors->has('startDate') ? 'border-red-500' : '' }}"
                                style="border-radius:0.5rem;" value="{{ $oldStartVal }}" />
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
                                class="input input-bordered !rounded-lg w-full {{ $errors->has('dueDate') ? 'border-red-500' : '' }}"
                                style="border-radius:0.5rem;" value="{{ $oldDueVal }}" />
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
                                <div class="space-y-2" data-overload-warnings-list></div>
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
                    <div class="flex flex-col gap-1">
                        <label class="font-medium text-sm">Description</label>
                        <textarea name="description" class="textarea textarea-bordered !rounded-lg w-full h-32" style="border-radius:0.5rem;"
                            placeholder="Task description">{{ old('description') }}</textarea>
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

<script>
    (function() {
    const toDateOnly = (v) => (v || '').toString().trim().substring(0, 10);
    const toDateTimeLocal = (v) => (v || '').toString().trim().substring(0, 16); // YYYY-MM-DDTHH:MM

    function setOverloadWarnings(form, warnings) {
        const box = form?.querySelector('[data-overload-warnings]');
        const list = form?.querySelector('[data-overload-warnings-list]');
        if (!box || !list) return;

        const msgs = Array.isArray(warnings) ? warnings.filter(Boolean).map(String) : [];
        list.innerHTML = msgs.map(m =>
            `<div class="rounded border border-yellow-300 bg-yellow-100 px-2 py-1">${m.replaceAll('<','&lt;').replaceAll('>','&gt;')}</div>`
        ).join('');
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
        console.debug('[due-calc-debug][dashboard] recalc:start', {
            startDate: start,
            storyPoints: sp,
            assigneeIdsRaw: assignees?.value ?? '',
            projectId: projectId?.value ?? ''
        });
        if (!start || !sp) {
            dueInput.min = '';
            dueInput.max = '';
            setOverloadWarnings(form, []);
            setDueCalcState(form, false);
            console.debug('[due-calc-debug][dashboard] recalc:skipped-missing-input', {
                hasStartDate: Boolean(start),
                hasStoryPoints: Boolean(sp)
            });
            return;
        }

        try {
            setDueCalcState(form, true);
            // Keep original datetime-local shape that worked in app flow.
            const startDateParam = toDateTimeLocal(start);
            const aidRaw = assignees?.value ? String(assignees.value) : '';
            const pid = projectId?.value ? String(projectId.value) : '';
            const params = new URLSearchParams();
            params.set('startDate', startDateParam);
            params.set('storyPoints', String(sp));
            if (pid) params.set('projectId', pid);
            aidRaw.split(',').map(s => s.trim()).filter(Boolean).forEach(id => {
                params.append('assigneeIds[]', id);
            });
            const url = `/tasks/calculate-due-date?${params.toString()}`;
            console.debug('[due-calc-debug][dashboard] request', {
                url,
                assigneeIds: aidRaw.split(',').map(s => s.trim()).filter(Boolean)
            });
            const r = await fetch(url, {
                headers: {
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });
            console.debug('[due-calc-debug][dashboard] response-meta', {
                ok: r.ok,
                status: r.status
            });
            if (!r.ok) {
                let errData = null;
                try { errData = await r.json(); } catch (_) {}
                console.debug('[due-calc-debug][dashboard] response-error-body', errData);
                setOverloadWarnings(form, errData?.warnings || []);
                return;
            }
            const data = await r.json();
            console.debug('[due-calc-debug][dashboard] response-body', data);

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
        } catch (err) {
            console.error('[due-calc-debug][dashboard] recalc:error', err);
        } finally {
            setDueCalcState(form, false);
        }
    }

    window.__dashDueCalc = {
        __init: true,
        recalc(formEl = null) {
            const form = formEl && formEl.matches?.('form[data-due-calc="true"]')
                ? formEl
                : document.querySelector('form[data-due-calc="true"]');
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
    })();
</script>
