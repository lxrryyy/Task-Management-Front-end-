@php
    $isEdit = ($formContext ?? 'add') === 'edit';
    $ctx = (string) ($formContext ?? 'add');
    $nameValue = $isEdit ? old('name', $formName ?? '') : old('name');
    $descValue = $isEdit ? old('description', $formDescription ?? '') : old('description');
@endphp
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
    @error('name')
        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
    @enderror
</div>
<div class="flex flex-col gap-4 my-4">
    <span>Description</span>
    <textarea name="description" class="textarea textarea-bordered w-full" placeholder="Project Description">{{ $descValue }}</textarea>
</div>
<div class="flex flex-row gap-4 my-4">
    <div class="flex flex-col gap-4 my-4">
        <span>Start Date</span>
        <input name="startDate" type="date" class="input input-bordered" />
    </div>
    <div class="flex flex-col gap-4 my-4">
        <span>End Date</span>
        <input name="endDate" type="date" class="input input-bordered" />
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
                    $aid = $account['id'] ?? $account['Id'] ?? null;
                    $aname = $account['name'] ?? $account['Name'] ?? 'Unknown';
                    $aemail = $account['email'] ?? $account['Email'] ?? '';
                    $checked = in_array((int) $aid, (array) ($selectedMemberIds ?? []), true);
                    $isCreator = $creatorId && (int) $creatorId === (int) $aid;
                @endphp
                @if($aid !== null && !$isCreator)
                    <li class="px-2 py-1" wire:key="{{ $ctx }}-member-option-{{ $aid }}">
                        <button type="button"
                                class="w-full flex items-center gap-3 cursor-pointer hover:bg-base-300/50 p-2 rounded"
                                wire:click="toggleMember({{ (int) $aid }})">
                            <span class="inline-flex items-center justify-center h-4 w-4 rounded border border-gray-400 bg-white">
                                @if($checked)
                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="0" y="0" width="20" height="20" rx="4" fill="#111827"/>
                                        <path d="M5 10.5L8.25 13.75L15 7" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                @endif
                            </span>
                            <span class="font-medium text-sm flex-1 text-left">{{ $aname }}</span>
                            @if($aemail)
                                <span class="text-xs text-gray-500">{{ $aemail }}</span>
                            @endif
                        </button>
                    </li>
                @endif
            @empty
                <li class="px-3 py-2 text-sm text-gray-400">No accounts loaded.</li>
            @endforelse
        </ul>
    </div>
    @error('memberIds')
        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
    @enderror
    @foreach(array_values((array) ($selectedMemberIds ?? [])) as $id)
        <input wire:key="{{ $ctx }}-member-hidden-{{ (int) $id }}" type="hidden" name="memberIds[]" value="{{ (int) $id }}" />
    @endforeach
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
