@php
    $isEdit = ($formContext ?? 'add') === 'edit';
    $ctx = (string) ($formContext ?? 'add');
    $nameValue = $isEdit ? old('name', $formName ?? '') : old('name');
    $descValue = $isEdit ? old('description', $formDescription ?? '') : old('description');
    $startDateValue = $isEdit ? old('startDate', $formStartDate ?? '') : old('startDate');
    $endDateValue = $isEdit ? old('endDate', $formEndDate ?? '') : old('endDate');
    $statusValue = $isEdit ? old('status', $formStatus ?? '') : old('status', '');
    $statusIdValue = $isEdit ? old('statusId', $formStatusId ?? 0) : old('statusId', 0);
@endphp
@if ($errors->has('api_error'))
    <div class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 mb-4 text-sm text-red-700">
        <p class="font-semibold mb-1">Please fix the following:</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach ($errors->get('api_error') as $apiMsg)
                <li>{{ $apiMsg }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $descriptionContent = $isEdit ? '' : (string) ($descValue ?? '');
    $creatorIdInt = (int) ($creatorId ?? 0);
    $scrumMasterIdInt = (int) ($selectedScrumMasterId ?? 0);
    $effectiveScrumMasterId = $scrumMasterIdInt > 0 ? $scrumMasterIdInt : $creatorIdInt;
    $showCreatorAsScrumMaster = $isEdit && $creatorIdInt > 0 && $effectiveScrumMasterId === $creatorIdInt;
    $creatorAccount = null;
    if ($showCreatorAsScrumMaster) {
        $creatorAccount = collect($accounts ?? [])->first(
            fn($a) => (int) ($a['id'] ?? ($a['Id'] ?? 0)) === $creatorIdInt,
        );
    }
    $hiddenIds = array_values(array_map('intval', (array) ($selectedMemberIds ?? [])));
    $hiddenKey = $ctx . '-hidden-' . implode('-', $hiddenIds ?: ['empty']);

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
    $mergeBioSpecLine = function (string $bio, string $spec): string {
        if ($bio !== '' && $spec !== '' && $bio !== $spec) {
            return $bio . ' · ' . $spec;
        }
        return $bio !== '' ? $bio : $spec;
    };
@endphp

<div class="flex flex-col gap-4 my-4">
    <span class="{{ $isEdit ? '' : 'text-xs font-semibold uppercase tracking-wide text-gray-700' }}">Project Name</span>
    <input name="name" type="text" placeholder="Type here"
        class="input input-bordered border-gray-300 focus:border-gray-300 rounded-lg w-full"
        @if ($isEdit) wire:model.lazy="formName" @else value="{{ $nameValue }}" @endif required />
    @foreach ($errors->get('name') as $msg)
        <p class="text-xs text-red-600 font-medium mt-1">{{ $msg }}</p>
    @endforeach
</div>

<div class="flex flex-col gap-4 my-4">
    <span class="{{ $isEdit ? '' : 'text-xs font-semibold uppercase tracking-wide text-gray-700' }}">Description</span>
    @if ($isEdit)
        <textarea name="description" class="textarea textarea-bordered border-gray-300 focus:border-gray-300 rounded-lg w-full min-h-24"
            placeholder="Project Description"
            wire:model.lazy="formDescription">{{ $descriptionContent }}</textarea>
    @else
        <div wire:ignore>
            <x-rich-text-editor name="description" :value="old('description', '')" placeholder="Description here..." />
        </div>
    @endif
    @foreach ($errors->get('description') as $msg)
        <p class="text-xs text-red-600 font-medium mt-1">{{ $msg }}</p>
    @endforeach
</div>

<div class="flex flex-row w-full gap-4 my-4">
    <div class="flex flex-col gap-2 flex-1">
        <span class="{{ $isEdit ? '' : 'text-xs font-semibold uppercase tracking-wide text-gray-700' }}">Start Date</span>
        <input name="startDate" type="date"
            class="input input-bordered rounded-lg w-full {{ $errors->has('startDate') ? 'border-red-500' : 'border-gray-300 focus:border-gray-300' }}"
            @if ($isEdit) wire:model.lazy="formStartDate" @else value="{{ $startDateValue }}" @endif />
        @foreach ($errors->get('startDate') as $msg)
            <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
        @endforeach
    </div>
    <div class="flex flex-col gap-2 flex-1">
        <span class="{{ $isEdit ? '' : 'text-xs font-semibold uppercase tracking-wide text-gray-700' }}">{{ $isEdit ? 'End Date' : 'Due Date' }}</span>
        <input name="endDate" type="date"
            class="input input-bordered rounded-lg w-full {{ $errors->has('endDate') ? 'border-red-500' : 'border-gray-300 focus:border-gray-300' }}"
            @if ($isEdit) wire:model.lazy="formEndDate" @else value="{{ $endDateValue }}" @endif />
        @foreach ($errors->get('endDate') as $msg)
            <p class="text-xs text-red-600 font-medium">{{ $msg }}</p>
        @endforeach
    </div>
</div>

<div class="flex flex-col gap-2 my-4">
    <span class="{{ $isEdit ? '' : 'text-xs font-semibold uppercase tracking-wide text-gray-700' }}">Team Members</span>
    <div class="flex items-center gap-3">
        @if (!empty($selectedMemberIds))
            @php
                $addProfiles = [];
                foreach ((array) $selectedMemberIds as $sid) {
                    $sid = (int) $sid;
                    $acc = collect($accounts ?? [])->first(fn($a) => (int) ($a['id'] ?? ($a['Id'] ?? 0)) === $sid);
                    if (!$acc) continue;
                    $name = (string) ($acc['name'] ?? $acc['Name'] ?? 'Unknown');
                    $parts = preg_split('/\s+/', trim($name));
                    $parts = array_values(array_filter($parts, fn($p) => is_string($p) && trim($p) !== ''));
                    $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
                    [$pBio, $pSpec] = $resolveAccountBioSpec($acc);
                    $addProfiles[] = [
                        'profilePicture' => $acc['profilePicture'] ?? ($acc['ProfilePicture'] ?? null),
                        'initials' => $initials ?: '?',
                        'name' => $name,
                        'email' => (string) ($acc['email'] ?? $acc['Email'] ?? ''),
                        'specialization' => $mergeBioSpecLine($pBio, $pSpec),
                        'role' => 'Member',
                    ];
                }
            @endphp
            <x-avatar-group :profiles="$addProfiles" :visible="5" overlap-class="-space-x-2" />
        @endif
        <div class="dropdown w-full">
            <button tabindex="0" type="button" class="btn btn-sm border-2 border-dotted border-gray-300 bg-white text-gray-800 p-4 rounded-lg">+ Add Member</button>
            <ul tabindex="0"
                class="dropdown-content bg-base-100 rounded-box z-[999] shadow-lg border mt-1 max-h-60 w-full overflow-y-auto p-1">
                @forelse(($accounts ?? []) as $account)
                    @php
                        $aid = $account['id'] ?? ($account['Id'] ?? null);
                        $aname = $account['name'] ?? ($account['Name'] ?? 'Unknown');
                        $aemail = $account['email'] ?? ($account['Email'] ?? '');
                        $apic = $account['profilePicture'] ?? ($account['ProfilePicture'] ?? null);
                        if ($apic && !str_starts_with($apic, 'http') && !str_starts_with($apic, 'data:')) {
                            $apic = 'data:image/jpeg;base64,' . $apic;
                        }
                        $apart = preg_split('/\s+/', trim((string) $aname));
                        $ainitials = mb_strtoupper(mb_substr($apart[0] ?? '', 0, 1) . mb_substr($apart[1] ?? '', 0, 1));
                        [$abio, $aspec] = $resolveAccountBioSpec($account);
                        $checked = in_array((int) $aid, (array) ($selectedMemberIds ?? []), true);
                        $isCreator = $creatorId && (int) $creatorId === (int) $aid;
                    @endphp
                    @if ($aid !== null && !$isCreator)
                        <li class="px-2 py-1" wire:key="{{ $ctx }}-member-option-{{ $aid }}">
                            <x-person-option :checked="$checked" :name="$aname" :email="$aemail" :picture="$apic"
                                :bio="$abio" :specialization="$aspec" initials="{{ $ainitials }}"
                                wire:click="toggleMember({{ (int) $aid }})" />
                        </li>
                    @endif
                @empty
                    <li class="px-3 py-2 text-sm text-gray-400">No accounts loaded.</li>
                @endforelse
            </ul>
        </div>
    </div>
    @foreach ($errors->get('memberIds') as $msg)
        <p class="text-xs text-red-600 font-medium mt-1">{{ $msg }}</p>
    @endforeach

    @if ($isEdit)
        <div id="edit-form-member-ids-data" data-member-ids="{{ json_encode($hiddenIds) }}" class="hidden"
            aria-hidden="true"></div>
    @endif
    <div wire:key="{{ $hiddenKey }}" id="{{ $isEdit ? 'edit-form-member-ids-inputs' : '' }}">
        @foreach ($hiddenIds as $id)
            <input type="hidden" name="memberIds[]" value="{{ $id }}" />
        @endforeach
    </div>
    <input type="hidden" name="scrumMasterId" value="{{ $selectedScrumMasterId ?: $creatorId }}" />
    @if ($isEdit && !empty($editingProjectId))
        <input type="hidden" name="_edit_project_id" value="{{ $editingProjectId }}" />
    @endif
</div>

<div class="overflow-x-auto border-t border-gray-200 pt-2">
    <table class="table table-sm">
        <thead>
            <tr>
                <th>Members</th>
                <th>Email</th>
                <th>Position</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @php
                $accountsById = [];
                foreach ($accounts ?? [] as $acc) {
                    $id = $acc['id'] ?? ($acc['Id'] ?? null);
                    if ($id !== null) {
                        $accountsById[(int) $id] = $acc;
                    }
                }
            @endphp

            @if (($showCreatorAsScrumMaster && $creatorIdInt > 0) || !empty($selectedMemberIds))
                @if ($showCreatorAsScrumMaster && is_array($creatorAccount))
                    @php
                        $cname = $creatorAccount['name'] ?? ($creatorAccount['Name'] ?? 'Project Manager');
                        $cemail = $creatorAccount['email'] ?? ($creatorAccount['Email'] ?? '');
                        $cPic = $creatorAccount['profilePicture'] ?? ($creatorAccount['ProfilePicture'] ?? null);
                        if ($cPic && !str_starts_with($cPic, 'http') && !str_starts_with($cPic, 'data:')) {
                            $cPic = 'data:image/jpeg;base64,' . $cPic;
                        }
                        $cParts = preg_split('/\s+/', trim((string) $cname));
                        $cInitials = mb_strtoupper(mb_substr($cParts[0] ?? '', 0, 1) . mb_substr($cParts[1] ?? '', 0, 1));
                        [$cbio, $cspec] = $resolveAccountBioSpec($creatorAccount);
                        $csub = $mergeBioSpecLine($cbio, $cspec);
                    @endphp
                    <tr wire:key="{{ $ctx }}-member-row-creator-sm-{{ $creatorIdInt }}">
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="avatar">
                                    <div class="w-6 h-6 rounded-full overflow-hidden bg-neutral text-neutral-content flex items-center justify-center">
                                        @if ($cPic)
                                            <img src="{{ $cPic }}" alt="{{ $cname }}" class="w-full h-full object-cover" loading="lazy" referrerpolicy="no-referrer" />
                                        @else
                                            <span class="text-[10px] font-semibold">{{ $cInitials ?: '?' }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span>{{ $cname }}</span>
                                    @if ($csub !== '')
                                        <span class="text-xs text-gray-500 truncate">{{ $csub }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td><span>{{ $cemail }}</span></td>
                        <td>
                            <select class="select h-9 select-bordered select-sm w-full max-w-xs" disabled>
                                <option value="Scrum Master" selected>Scrum Master</option>
                            </select>
                        </td>
                        <th><span class="text-gray-400">—</span></th>
                    </tr>
                @endif
                @foreach ($selectedMemberIds as $memberId)
                    @php
                        $memberId = (int) $memberId;
                        $acc = $accountsById[$memberId] ?? null;
                        if (!$acc) {
                            continue;
                        }
                        $name = $acc['name'] ?? ($acc['Name'] ?? 'Unknown');
                        $email = $acc['email'] ?? ($acc['Email'] ?? '');
                        $pic = $acc['profilePicture'] ?? ($acc['ProfilePicture'] ?? null);
                        if ($pic && !str_starts_with($pic, 'http') && !str_starts_with($pic, 'data:')) {
                            $pic = 'data:image/jpeg;base64,' . $pic;
                        }
                        $parts = preg_split('/\s+/', trim((string) $name));
                        $initials = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
                        $pos = $memberRoles[$memberId] ?? 'Member';
                        // Only one Scrum Master among table members:
                        // disable "Scrum Master" only if SOME OTHER non-creator member is already Scrum Master.
                        // (If the project manager/creator is Scrum Master, keep the option enabled so you can transfer it.)
                        $scrumMasterAlreadyTaken =
                            $scrumMasterIdInt > 0 &&
                            $scrumMasterIdInt !== $creatorIdInt &&
                            $scrumMasterIdInt !== $memberId;
                        [$mbio, $mspec] = $resolveAccountBioSpec($acc);
                        $msub = $mergeBioSpecLine($mbio, $mspec);
                    @endphp
                    <tr wire:key="{{ $ctx }}-member-row-{{ $memberId }}">
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="avatar">
                                    <div class="w-6 h-6 rounded-full overflow-hidden bg-neutral text-neutral-content flex items-center justify-center">
                                        @if ($pic)
                                            <img src="{{ $pic }}" alt="{{ $name }}" class="w-full h-full object-cover" loading="lazy" referrerpolicy="no-referrer" />
                                        @else
                                            <span class="text-[10px] font-semibold">{{ $initials ?: '?' }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span>{{ $name }}</span>
                                    @if ($msub !== '')
                                        <span class="text-xs text-gray-500 truncate">{{ $msub }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td><span>{{ $email }}</span></td>
                        <td>
                            <select class="select h-9 select-bordered select-sm w-full max-w-xs"
                                x-on:change="$wire.setMemberRole({{ $memberId }}, $event.target.value)">
                                <option value="Member" {{ $pos === 'Member' ? 'selected' : '' }}>Member</option>
                                <option value="Scrum Master" {{ $pos === 'Scrum Master' ? 'selected' : '' }}
                                    {{ $scrumMasterAlreadyTaken ? 'disabled' : '' }}>Scrum Master</option>
                            </select>
                        </td>
                        <th>
                            <button type="button" wire:click="removeMember({{ $memberId }})"
                                class="btn btn-ghost btn-xs text-error">Remove</button>
                        </th>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="4" class="text-center text-gray-400 py-4">No members selected. Check members in the
                        list above to add them here.</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
