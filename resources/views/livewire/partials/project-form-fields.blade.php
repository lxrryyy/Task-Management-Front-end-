@php
    $isEdit = ($formContext ?? 'add') === 'edit';
    $ctx = (string) ($formContext ?? 'add');
    $nameValue      = $isEdit ? old('name',        $formName        ?? '') : old('name');
    $descValue      = $isEdit ? old('description', $formDescription ?? '') : old('description');
    $startDateValue = $isEdit ? old('startDate',   $formStartDate   ?? '') : old('startDate');
    $endDateValue   = $isEdit ? old('endDate',     $formEndDate     ?? '') : old('endDate');
    $statusValue    = $isEdit ? old('status',      $formStatus      ?? '') : old('status', '');
@endphp
@if($errors->has('api_error'))
    <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 mb-4 text-sm text-red-700">
        <p class="font-semibold mb-1">Please fix the following:</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->get('api_error') as $apiMsg)
                <li>{{ $apiMsg }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="flex flex-col gap-4 my-4">
    <span>Project Name</span>
    <input
        name="name"
        type="text"
        placeholder="Type here"
        class="input input-bordered w-full"
        value="{{ $nameValue }}"
        required
    />
    @foreach($errors->get('name') as $msg)
        <p class="text-xs text-red-600 font-medium mt-1">{{ $msg }}</p>
    @endforeach
</div>
<div class="flex flex-col gap-4 my-4">
    <span>Description</span>
    <textarea name="description" class="textarea textarea-bordered w-full" placeholder="Project Description">{{ $descValue }}</textarea>
</div>
<div class="flex flex-col gap-2 my-4">
    <span>Status</span>
    <select name="status" class="select select-bordered w-full">
        <option value="">-- Select Status --</option>
        @foreach(($projectStatuses ?? []) as $ps)
            <option value="{{ $ps }}" {{ $statusValue === $ps ? 'selected' : '' }}>{{ $ps }}</option>
        @endforeach
    </select>
</div>
<div class="flex flex-row gap-4 my-4">
    <div class="flex flex-col gap-2 my-4">
        <span>Start Date</span>
        <input name="startDate" type="date"
               class="input input-bordered {{ $errors->has('startDate') ? 'border-red-500' : '' }}"
               value="{{ $startDateValue }}" />
        @foreach($errors->get('startDate') as $msg)
            <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
        @endforeach
    </div>
    <div class="flex flex-col gap-2 my-4">
        <span>End Date</span>
        <input name="endDate" type="date"
               class="input input-bordered {{ $errors->has('endDate') ? 'border-red-500' : '' }}"
               value="{{ $endDateValue }}" />
        @foreach($errors->get('endDate') as $msg)
            <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
        @endforeach
    </div>
</div>

<div class="flex flex-col gap-2 my-4">
    <span>Members</span>
    <div class="dropdown w-full">
        <div tabindex="0" role="button"
             class="border flex items-center justify-between w-full px-3 py-2 rounded-lg cursor-pointer bg-base-100">
            <div class="flex flex-col">
                <span class="font-medium text-sm">Select members</span>
                @php
                    $selectedCount = count((array) ($selectedMemberIds ?? []));
                @endphp
                <span class="text-xs text-gray-500">
                    @if($selectedCount > 0)
                        {{ $selectedCount }} selected
                    @else
                        Choose one or more members
                    @endif
                </span>
            </div>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
        <ul tabindex="0"
            class="dropdown-content menu bg-base-100 rounded-box z-[999] w-full shadow-lg border mt-1 max-h-60 overflow-y-auto">
            @forelse(($accounts ?? []) as $account)
                @php
                    $aid      = $account['id']    ?? $account['Id']    ?? null;
                    $aname    = $account['name']   ?? $account['Name']  ?? 'Unknown';
                    $aemail   = $account['email']  ?? $account['Email'] ?? '';
                    $checked  = in_array((int) $aid, (array) ($selectedMemberIds ?? []), true);
                    $isCreator = $creatorId && (int) $creatorId === (int) $aid;
                @endphp
                @if($aid !== null && !$isCreator)
                    <li class="px-2 py-1" wire:key="{{ $ctx }}-member-option-{{ $aid }}">
                        <x-person-option
                            :checked="$checked"
                            :name="$aname"
                            :email="$aemail"
                            wire:click="toggleMember({{ (int) $aid }})" />
                    </li>
                @endif
            @empty
                <li class="px-3 py-2 text-sm text-gray-400">No accounts loaded.</li>
            @endforelse
        </ul>
    </div>
    @foreach($errors->get('memberIds') as $msg)
        <p class="text-xs text-red-600 font-medium mt-1">{{ $msg }}</p>
    @endforeach
    @php
        $hiddenIds = array_values(array_map('intval', (array) ($selectedMemberIds ?? [])));
        $hiddenKey = $ctx . '-hidden-' . implode('-', $hiddenIds ?: ['empty']);
    @endphp
    {{-- wire:key changes whenever selectedMemberIds changes, forcing Livewire to fully replace this div --}}
    <div wire:key="{{ $hiddenKey }}">
        @foreach($hiddenIds as $id)
            <input type="hidden" name="memberIds[]" value="{{ $id }}" />
        @endforeach
    </div>
    <input type="hidden" name="scrumMasterId" value="{{ $selectedScrumMasterId ?: $creatorId }}" />
    @if($isEdit && !empty($editingProjectId))
        <input type="hidden" name="_edit_project_id" value="{{ $editingProjectId }}" />
    @endif
</div>

<div class="overflow-x-auto">
    <table class="table">
        <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Position</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        @php
            $accountsById = [];
            foreach(($accounts ?? []) as $acc) {
                $id = $acc['id'] ?? $acc['Id'] ?? null;
                if ($id !== null) {
                    $accountsById[(int) $id] = $acc;
                }
            }
        @endphp

        @if(!empty($selectedMemberIds))
            @foreach($selectedMemberIds as $memberId)
                @php
                    $memberId = (int) $memberId;
                    $acc = $accountsById[$memberId] ?? null;
                    if (!$acc) continue;
                    $name = $acc['name'] ?? $acc['Name'] ?? 'Unknown';
                    $email = $acc['email'] ?? $acc['Email'] ?? '';
                    $pos = $memberRoles[$memberId] ?? 'Member';
                @endphp
                <tr wire:key="{{ $ctx }}-member-row-{{ $memberId }}">
                    <td><span>{{ $name }}</span></td>
                    <td><span>{{ $email }}</span></td>
                    <td>
                        <select class="select h-9 select-bordered select-sm w-full max-w-xs"
                                x-on:change="$wire.setMemberRole({{ $memberId }}, $event.target.value)">
                            <option value="Member" {{ $pos === 'Member' ? 'selected' : '' }}>Member</option>
                            <option value="Scrum Master" {{ $pos === 'Scrum Master' ? 'selected' : '' }}>Scrum Master</option>
                        </select>
                    </td>
                    <th>
                        <button type="button" wire:click="removeMember({{ $memberId }})" class="btn btn-ghost btn-xs text-error">Remove</button>
                    </th>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="4" class="text-center text-gray-400 py-4">No members selected. Check members in the list above to add them here.</td>
            </tr>
        @endif
        </tbody>
    </table>
</div>
