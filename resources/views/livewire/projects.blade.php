<div class="" x-data="projectCreateOptimistic({
    actionUrl: @js(route('projects.store')),
    csrfToken: @js(csrf_token()),
    currentUserName: @js((string) ($currentUserName ?? 'You')),
})">
    <div class="w-full">
        @if ($flashSuccess)
            <div x-data="{ show: true }" x-init="setTimeout(() => { show = false; $wire.dismissFlashSuccess() }, 6500)" x-show="show"
                x-transition.opacity.duration.300ms
                class="alert alert-success text-sm flex items-center gap-2 py-2 px-4 rounded-lg mb-3">
                <span>{{ $flashSuccess }}</span>
            </div>
        @endif
        <div class="flex w-full items-center clr-primary ">
            <a href="/dashboard"
                class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('projects') ? 'clr-primary' : '' }} hover-clr-accent">
                <x-icons.back-btn classes="w-6 h-6" />
            </a>
            <span class="group-hover:block text-xl">Projects</span>
        </div>
        <hr class="border-2 clr-bg-primary">
        <div>
            <div class="flex flex-wrap items-center justify-between p-2 gap-2 flex-shrink-0">
                <div>
                    <button type="button"
                        class="btn w-36 border-2 border-gray clr-bg-primary text-base-100 rounded-xl m-1"
                        wire:click="archiveSelected">
                        <x-icons.archive classes="w-4 h-4 inline-block" /> Archive
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-search-input wire:model.live.debounce.300ms="search" />
                    <x-filter-dropdown
                        button-class="btn border-2 border-gray rounded-lg clr-primary text-base-100 p-4 hover-clr-bg-primary hover:text-base-100"
                        clear-action="clearFilters">
                        <div class="flex flex-col gap-1">
                            <span class="text-gray-600">Status</span>
                            <select wire:model.live="filterStatus"
                                class="select select-bordered w-full bg-white text-gray-900">
                                <option value="">All statuses</option>
                                @foreach ($projectStatuses ?? [] as $ps)
                                    <option value="{{ $ps }}">{{ $ps }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-gray-600">Project Manager</span>
                            <select wire:model.live="filterProjectManager"
                                class="select select-bordered w-full bg-white text-gray-900">
                                <option value="">All managers</option>
                                @foreach ($projectManagerOptions ?? [] as $pm)
                                    <option value="{{ $pm }}">{{ $pm }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-gray-600">Progress</span>
                            <select wire:model.live="filterProgress"
                                class="select select-bordered w-full bg-white text-gray-900">
                                <option value="">All progress</option>
                                <option value="0-25">0% - 25%</option>
                                <option value="26-50">26% - 50%</option>
                                <option value="51-75">51% - 75%</option>
                                <option value="76-99">76% - 99%</option>
                                <option value="100">100%</option>
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

                    <div>
                        <button type="button" wire:click="openModal"
    class="btn border-2 clr-bg-primary rounded-lg text-base-100 w-full p-4 sm:w-36">+ Add Project</button>
                    </div>

                    {{-- Add Project modal (create only) --}}
                    @if ($showAddModal)
                        <dialog id="addProjectDialog" class="modal modal-open">
                            <div class="modal-box w-11/12 max-w-5xl overflow-y-auto">
                                <div class="flex items-start justify-between mb-2">
                                    <h3 class="font-semibold text-2xl">Add New Project</h3>
                                    <button type="button" wire:click="closeAddModal"
                                        class="btn btn-ghost btn-sm btn-circle">✕</button>
                                </div>
                                <form method="POST" action="{{ route('projects.store') }}" class="mt-0"
                                    data-no-global-loader x-on:submit.prevent="submit($event, $wire)">
                                    @csrf
                                    @include('livewire.partials.project-form-fields', [
                                        'formContext' => 'add',
                                    ])
                                    <div class="modal-action">
                                        <button type="submit" class="btn clr-bg-primary text-base-100 px-2"
                                            x-bind:disabled="isSubmitting"
                                            x-bind:class="{ 'opacity-70 cursor-wait': isSubmitting }">
                                            <span x-show="!isSubmitting">Add Project</span>
                                            <span x-show="isSubmitting">Adding...</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <form method="dialog" class="modal-backdrop">
                                <button type="button" wire:click="closeAddModal">close</button>
                            </form>
                        </dialog>
                    @endif

                    {{-- Edit Project modal (update only) --}}
                    @if ($showEditModal)
                        <dialog id="editProjectDialog" class="modal modal-open">
                            <div class="modal-box w-11/12 max-w-5xl overflow-y-auto relative">
                                <button type="button" wire:click="closeDetailsModal"
                                    class="btn btn-sm btn-ghost absolute top-3 right-3">✕</button>
                                <h3 class="font-bold text-lg">Project Details</h3>
                                @if ($editingProjectId)
                                    <div>
                                        <form class="mt-4" @submit.prevent>
                                            @include('livewire.partials.project-form-fields', [
                                                'formContext' => 'edit',
                                            ])
                                            <div class="modal-action">
                                                <button type="button" class="btn clr-bg-primary text-base-100 px-2"
                                                    wire:click="prepareEditSubmit">
                                                    Update Project
                                                </button>
                                            </div>
                                        </form>

                                        {{-- Confirmation: shown after prepareEditSubmit locks member list (fixes member removal) --}}
                                        @if ($showConfirmDialog ?? false)
                                            <div class="fixed inset-0 z-[9999] flex items-center justify-center">
                                                <div class="absolute inset-0" wire:click="cancelConfirmDialog"></div>
                                                <div
                                                    class="relative bg-gray-100 rounded-2xl shadow-2xl border border-gray-200 p-6 w-94">
                                                    <h3 class="text-lg font-normal">Confirm Update</h3>
                                                    <p class="py-4 text-sm text-gray-600">Are you sure you want to save
                                                        the changes to this project?</p>
                                                    <div class="flex justify-end gap-2">
                                                        <button type="button"
                                                            class="btn btn-ghost clr-bg-primary text-base-100 p-2"
                                                            wire:click="cancelConfirmDialog">Cancel</button>
                                                        <button type="button"
                                                            class="btn clr-bg-primary text-base-100 p-2"
                                                            wire:click="submitEditProject">Yes, Update</button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <form method="dialog" class="modal-backdrop">
                                <button type="button" wire:click="closeEditModal">close</button>
                            </form>
                        </dialog>
                    @endif

                    {{-- Delete confirmation --}}
                    @if ($showDeleteConfirmDialog ?? false)
                        <dialog id="deleteProjectDialog" class="modal modal-open">
                            <div class="modal-box w-11/12 max-w-md">
                                <h3 class="font-normal text-lg">Confirm Delete</h3>
                                <p class="py-4 text-sm text-gray-700">
                                    Are you sure you want to delete
                                    <span
                                        class="font-normal break-words">{{ $deletingProjectName ?? 'this project' }}</span>?
                                </p>
                                <div class="modal-action">
                                    <button type="button" class="btn clr-bg-primary text-base-100 p-2"
                                        wire:click="cancelDelete">Cancel</button>
                                    <button type="button"
                                        class="btn bg-red-600 hover:bg-red-700 text-base-100 border-none p-2"
                                        wire:click="deleteProject">Delete</button>
                                </div>
                            </div>
                            <form method="dialog" class="modal-backdrop">
                                <button type="button" wire:click="cancelDelete">close</button>
                            </form>
                        </dialog>
                    @endif

                    {{-- Details modal --}}
                    @if ($showDetailsModal ?? false)
                        <dialog id="projectDetailsDialog" class="modal modal-open">
                            <div class="modal-box w-11/12 max-w-5xl overflow-y-auto relative">
                                <button type="button" wire:click="closeDetailsModal"
                                    class="btn btn-sm btn-ghost absolute top-3 right-3">✕</button>
                                <h3 class="font-bold text-lg">Project Details</h3>
                                <div class="flex flex-col gap-4 my-4">
                                    <span>Project Name</span>
                                    <input type="text"
                                        class="input input-bordered rounded-lg w-full bg-gray-100 text-gray-700"
                                        value="{{ $detailsProjectName ?: '—' }}" readonly />
                                </div>
                                <div class="flex flex-col gap-4 my-4">
                                    <span>Description</span>
                                    <div
                                        class="rounded-lg w-full min-h-[8rem] border border-gray-300 bg-gray-50/80 text-gray-800 p-4 overflow-auto prose prose-sm max-w-none prose-p:my-1 prose-headings:my-2 prose-ul:my-2 prose-ol:my-2">
                                        @if (trim((string) ($detailsProjectDescription ?? '')) !== '')
                                            {!! $detailsProjectDescription !!}
                                        @else
                                            <span class="text-gray-500 not-prose">No description provided.</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex flex-row gap-4 my-4">
                                    <div class="flex flex-col gap-2 my-4">
                                        <span>Start Date</span>
                                        <input type="date"
                                            class="input input-bordered rounded-lg bg-gray-100 text-gray-700"
                                            value="{{ $detailsProjectStartDate }}" readonly />
                                    </div>
                                    <div class="flex flex-col gap-2 my-4">
                                        <span>End Date</span>
                                        <input type="date"
                                            class="input input-bordered rounded-lg bg-gray-100 text-gray-700"
                                            value="{{ $detailsProjectEndDate }}" readonly />
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2 my-4">
                                    <span>Members</span>
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
                                            @forelse(($detailsMemberRows ?? []) as $row)
                                                <tr>
                                                    <td><span>{{ $row['name'] ?? 'Unknown' }}</span></td>
                                                    <td><span>{{ $row['email'] ?? '' }}</span></td>
                                                    <td>
                                                        <input type="text"
                                                            class="input input-bordered input-sm w-full max-w-xs bg-gray-100 text-gray-700"
                                                            value="{{ $row['role'] ?? 'Member' }}" readonly />
                                                    </td>
                                                    <td><span class="text-gray-400">—</span></td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center text-gray-400 py-4">No
                                                        members found for this project.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="modal-action">
                                    <button type="button" class="btn clr-bg-primary text-base-100 p-2"
                                        wire:click="closeDetailsModal">Close</button>
                                </div>
                            </div>
                            <form method="dialog" class="modal-backdrop">
                                <button type="button" wire:click="closeDetailsModal">close</button>
                            </form>
                        </dialog>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-visible" style="height: 80vh;">
        <table class="table">
            <!-- head -->
            <thead>
                <tr>
                    <th class="!font-normal">Project Name</th>
                    <th class="!font-normal">Project Manager</th>
                    <th class="!font-normal">Members</th>
                    <th class="!font-normal">Progress</th>
                    <th class="!font-normal">Status</th>
                    <th class="!font-normal">Start Date</th>
                    <th class="!font-normal">End Date</th>
                    <th class="!font-normal">Action</th>
                </tr>
            </thead>
            <tbody class="[&>tr>td]:border-b [&>tr>td]:border-gray-200 [&>tr>th]:border-b [&>tr>th]:border-gray-200">
                <template x-for="project in optimisticProjects" :key="project.tempId">
                    <tr class="border-b border-gray-200"
                        x-bind:class="project.state === 'pending' ? 'bg-blue-50/30' : 'bg-green-50/20 cursor-pointer hover:bg-gray-50'"
                        @click="if (project.state === 'created' && project.id) { window.location = `/projects/${project.id}/tasks`; }">
                        <td><span class="underline-offset-2" x-text="project.name"></span></td>
                        <td x-text="project.createdByName"></td>
                        <td>
                            <div class="flex items-center gap-1 -space-x-3" x-show="(project.members || []).length > 0">
                                <template x-for="(member, idx) in (project.members || []).slice(0, 3)"
                                    :key="project.tempId + '-m-' + idx">
                                    <div class="avatar" :title="member.name" data-member-avatar>
                                        <div
                                            class="bg-neutral text-neutral-content w-8 h-8 rounded-full flex items-center justify-center relative overflow-hidden">
                                            <span class="text-xs font-semibold leading-none"
                                                x-show="!member.profilePicture"
                                                x-text="member.initials || '?'"></span>
                                            <img x-show="member.profilePicture" x-bind:src="member.profilePicture"
                                                alt=""
                                                class="absolute inset-0 w-full h-full rounded-full object-cover"
                                                loading="lazy" referrerpolicy="no-referrer" />
                                        </div>
                                    </div>
                                </template>
                                <template x-if="(project.members || []).length > 3">
                                    <div class="avatar avatar-placeholder">
                                        <div
                                            class="bg-neutral text-neutral-content w-8 h-8 rounded-full flex items-center justify-center">
                                            <span class="text-xs font-semibold leading-none"
                                                x-text="'+' + ((project.members || []).length - 3)"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <span class="text-sm text-gray-400" x-show="(project.members || []).length === 0">
                                No members
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center gap-2 w-32">
                                <progress class="progress flex-1" value="0" max="100"></progress>
                                <span class="text-xs text-gray-600">0%</span>
                            </div>
                        </td>
                        <th>
                            <div class="inline-flex items-center gap-2 rounded-none px-2 py-1 w-[9.5rem] overflow-hidden"
                                x-bind:style="project.state === 'pending' ? 'background:#fef3c7;color:#92400e;' : 'background:#f3f4f6;color:#374151;'">
                                <span class="shrink-0 text-current">
                                    <x-icons.circle />
                                </span>
                                <span class="text-xs font-medium whitespace-nowrap truncate"
                                    x-text="project.state === 'pending' ? 'Creating...' : (project.status || 'Not Started')"></span>
                            </div>
                        </th>
                        <th>
                            <span class="!font-normal text-sm" x-text="project.createdAt || '—'"></span>
                        </th>
                        <th>
                            <span class="!font-normal text-sm" x-text="project.endDate || '—'"></span>
                        </th>
                        <th>
                            <span class="text-gray-400" x-show="!(project.state === 'created' && project.id)">—</span>
                            <button type="button" class="btn btn-ghost btn-sm px-2"
                                x-show="project.state === 'created' && project.id"
                                @click.stop="window.location = `/projects/${project.id}/tasks`">
                                <x-icons.three-dot classes="w-5 h-5" />
                            </button>
                        </th>
                    </tr>
                </template>
                @if ($loading)
                    @foreach (range(1, 8) as $i)
                        <tr>
                            <td>
                                <div class="h-4 bg-gray-200 rounded animate-pulse w-36"></div>
                            </td>
                            <td>
                                <div class="h-4 bg-gray-200 rounded animate-pulse w-28"></div>
                            </td>
                            <td>
                                <div class="flex gap-1">
                                    <div class="h-6 w-6 bg-gray-200 rounded-full animate-pulse"></div>
                                    <div class="h-6 w-6 bg-gray-200 rounded-full animate-pulse"></div>
                                </div>
                            </td>
                            <td>
                                <div class="h-4 bg-gray-200 rounded animate-pulse w-32"></div>
                            </td>
                            <td>
                                <div class="h-6 bg-gray-200 rounded animate-pulse w-24"></div>
                            </td>
                            <td>
                                <div class="h-4 bg-gray-200 rounded animate-pulse w-20"></div>
                            </td>
                            <td>
                                <div class="h-4 bg-gray-200 rounded animate-pulse w-20"></div>
                            </td>
                            <td>
                                <div class="h-4 bg-gray-200 rounded animate-pulse w-20"></div>
                            </td>
                            <td>
                                <div class="h-4 bg-gray-200 rounded animate-pulse w-8"></div>
                            </td>
                        </tr>
                    @endforeach
                @else
                    @forelse(($filteredProjects ?? []) as $project)
                        @php
                            $projectId = $project['id'] ?? ($project['Id'] ?? null);
                            $name = $project['name'] ?? ($project['projectName'] ?? ($project['title'] ?? '—'));
                            $status = $project['statusName'] ?? ($project['status'] ?? '');
                            $currentStatusId =
                                (int) ($project['statusId'] ??
                                    ($project['StatusId'] ?? ($projectStatusMap[$status] ?? 0)));
                            $createdAt = $project['createdAt'] ?? null;
                            $endDate = $project['endDate'] ?? null;

                            // Leader name comes from createdByName
                            $leaderDisplay = $project['createdByName'] ?? '—';

                            // Members: if API returns assigneeIds use that, else use memberNames (backend sometimes returns stale list after PATCH)
                            $memberNames = [];
                            $memberProfiles = [];
                            $pmId =
                                (int) ($project['projectManagerId'] ??
                                    ($project['ProjectManagerId'] ??
                                        ($project['createdById'] ?? ($project['CreatedById'] ?? 0))));
                            $smId = (int) ($project['scrumMasterId'] ?? ($project['ScrumMasterId'] ?? 0));
                            $assigneeIds = $project['assigneeIds'] ?? ($project['AssigneeIds'] ?? null);
                            if (is_array($assigneeIds) && !empty($assigneeIds)) {
                                $creatorIdInt = (int) ($creatorId ?? 0);
                                foreach ($assigneeIds as $aid) {
                                    $aid = (int) $aid;
                                    if ($aid === $creatorIdInt) {
                                        continue;
                                    }
                                    $acc = collect($accounts ?? [])->first(
                                        fn($a) => (int) ($a['id'] ?? ($a['Id'] ?? 0)) === $aid,
                                    );
                                    if ($acc) {
                                        $memberName = trim($acc['name'] ?? ($acc['Name'] ?? ''));
                                        if ($memberName !== '') {
                                            $memberNames[] = $memberName;
                                        }

                                        $pp = $acc['profilePicture'] ?? ($acc['ProfilePicture'] ?? null);

                                        // Build initials from name (fallback if no picture)
                                        $parts = preg_split('/\s+/', trim($memberName));
                                        $parts = array_values(
                                            array_filter($parts, fn($p) => is_string($p) && trim($p) !== ''),
                                        );
                                        $first = (string) ($parts[0] ?? '');
                                        $last = (string) (!empty($parts) ? implode(' ', array_slice($parts, 1)) : '');
                                        $a0 = mb_substr(trim($first), 0, 1);
                                        $b0 = mb_substr(trim($last), 0, 1);
                                        $initials = '';
                                        if ($a0 !== '' && $b0 !== '') {
                                            $initials = mb_strtoupper($a0 . $b0);
                                        } elseif ($a0 !== '') {
                                            $initials = mb_strtoupper($a0);
                                        }

                                        $memberProfiles[] = [
                                            'profilePicture' => \App\Support\AccountPresentation::profilePictureDisplayUrl(
                                                $pp,
                                            ),
                                            'initials' => $initials ?: '?',
                                            'name' => $memberName,
                                            'email' => (string) ($acc['email'] ?? ($acc['Email'] ?? '')),
                                            'specialization' => \App\Support\AccountPresentation::displaySpecialization(
                                                $acc,
                                            ),
                                            'role' =>
                                                $aid === $pmId
                                                    ? 'Project Manager'
                                                    : ($aid === $smId
                                                        ? 'Scrum Master'
                                                        : 'Member'),
                                        ];
                                    }
                                }
                            }
                            if (empty($memberNames)) {
                                $raw = $project['memberNames'] ?? ($project['members'] ?? []);
                                $memberNames = is_array($raw) ? $raw : [];
                                if (!empty($memberNames)) {
                                    if ($leaderDisplay !== '—') {
                                        $memberNames = array_values(
                                            array_filter(
                                                $memberNames,
                                                static fn($m) => trim((string) $m) !== trim((string) $leaderDisplay),
                                            ),
                                        );
                                    }
                                    $memberNames = array_values(array_unique(array_map('trim', $memberNames)));
                                }

                                // If backend didn't provide assigneeIds, we can only approximate member avatars from names.
    if (!empty($memberNames)) {
        $accountsList = collect($accounts ?? []);
        foreach ($memberNames as $mn) {
            $mn = (string) trim($mn);
            $acc = $accountsList->first(function ($a) use ($mn) {
                $name = (string) ($a['name'] ?? ($a['Name'] ?? ''));
                return trim($name) === trim($mn);
            });

            $pp = $acc ? $acc['profilePicture'] ?? ($acc['ProfilePicture'] ?? null) : null;

            $parts = preg_split('/\s+/', trim($mn));
            $parts = array_values(
                array_filter($parts, fn($p) => is_string($p) && trim($p) !== ''),
            );
            $first = (string) ($parts[0] ?? '');
            $last = (string) (!empty($parts) ? implode(' ', array_slice($parts, 1)) : '');
            $a0 = mb_substr(trim($first), 0, 1);
            $b0 = mb_substr(trim($last), 0, 1);
            $initials = '';
            if ($a0 !== '' && $b0 !== '') {
                $initials = mb_strtoupper($a0 . $b0);
            } elseif ($a0 !== '') {
                $initials = mb_strtoupper($a0);
            }
            $memberProfiles[] = [
                'profilePicture' => \App\Support\AccountPresentation::profilePictureDisplayUrl(
                    $pp,
                ),
                'initials' => $initials ?: '?',
                'name' => $mn,
                'email' => (string) ($acc ? $acc['email'] ?? ($acc['Email'] ?? '') : ''),
                'specialization' => $acc
                    ? \App\Support\AccountPresentation::displaySpecialization($acc)
                    : '',
                'role' => 'Member',
            ];
        }
    }
}
$memberCount = is_array($memberProfiles) ? count($memberProfiles) : 0;
$projectLeaderId = $project['createdById'] ?? ($project['CreatedById'] ?? null);
$currentUserId = (int) ($creatorId ?? 0);
$currentRole = mb_strtolower(
    trim(
        (string) (\Illuminate\Support\Facades\Session::get('user')['role'] ??
            (\Illuminate\Support\Facades\Session::get('user')['Role'] ??
                (\Illuminate\Support\Facades\Session::get('user')['roleName'] ??
                    (\Illuminate\Support\Facades\Session::get('user')['RoleName'] ?? '')))),
    ),
);
$currentName = trim(
    (string) (\Illuminate\Support\Facades\Session::get('user')['name'] ??
        (\Illuminate\Support\Facades\Session::get('user')['Name'] ?? '')),
);
$isAdmin = $currentRole === 'admin';
$isProjectManager = $pmId > 0 && $pmId === $currentUserId;
$isScrumMaster = $smId > 0 && $smId === $currentUserId;

$isMember = false;
if (is_array($assigneeIds) && !empty($assigneeIds)) {
    $isMember = in_array($currentUserId, array_map('intval', $assigneeIds), true);
} elseif (!empty($memberNames) && $currentName !== '') {
                                $isMember = in_array(
                                    mb_strtolower($currentName),
                                    array_map(static fn($n) => mb_strtolower(trim((string) $n)), $memberNames),
                                    true,
                                );
                            }

                            $canOpenActionMenu =
                                $projectId && ($isAdmin || $isProjectManager || $isScrumMaster || $isMember);
                            $canManageProject = $projectId && ($isAdmin || $isProjectManager);
                        @endphp
                        <tr class="hover:bg-gray-50 cursor-pointer border-b border-gray-200"
                            @if ($projectId) @click="window.location='{{ route('projects.tasks', $projectId) }}'" @endif>
                            <td><span class="underline-offset-2">{{ $name }}</span></td>
                            <td>{{ $leaderDisplay }}</td>
                            <td>
                                @if ($memberCount > 0)
                                    @php
                                        $visibleProfiles = array_slice($memberProfiles, 0, 3);
                                        $overflowCount = max(0, (int) $memberCount - 3);
                                    @endphp
                                    <div class="flex items-center gap-1 -space-x-3">
                                        @foreach ($visibleProfiles as $mp)
                                            <div class="relative" x-data="{ open: false }" @mouseenter="open = true"
                                                @mouseleave="open = false">
                                                <div class="avatar" data-member-avatar>
                                                    <div
                                                        class="bg-neutral text-neutral-content w-8 h-8 rounded-full flex items-center justify-center relative overflow-hidden">
                                                        <span data-member-initials
                                                            class="text-xs font-semibold leading-none {{ !empty($mp['profilePicture']) ? 'hidden' : '' }}">
                                                            {{ $mp['initials'] ?? '?' }}
                                                        </span>

                                                        @if (!empty($mp['profilePicture']))
                                                            <img src="{{ $mp['profilePicture'] }}" alt=""
                                                                class="absolute inset-0 w-full h-full rounded-full object-cover"
                                                                loading="lazy" referrerpolicy="no-referrer"
                                                                onerror="this.style.display='none'; var wrap=this.closest('[data-member-avatar]'); if(wrap){var sp=wrap.querySelector('[data-member-initials]'); if(sp){sp.classList.remove('hidden');}}" />
                                                        @endif
                                                    </div>
                                                </div>

                                                <div x-show="open" x-transition
                                                    class="absolute left-1/2 top-full mt-2 -translate-x-1/2 z-50">
                                                    <x-profile-hover-card :name="$mp['name'] ?? ''" :email="$mp['email'] ?? ''"
                                                        :specialization="$mp['specialization'] ?? ''" :role="$mp['role'] ?? ''" :avatar-url="$mp['profilePicture'] ?? null" />
                                                </div>
                                            </div>
                                        @endforeach

                                        @if ($overflowCount > 0)
                                            <div class="avatar avatar-placeholder">
                                                <div
                                                    class="bg-neutral text-neutral-content w-8 h-8 rounded-full flex items-center justify-center">
                                                    <span
                                                        class="text-xs font-semibold leading-none">+{{ $overflowCount }}</span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-sm text-gray-400">No members</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $progressValue =
                                        (int) ($project['completionPercentage'] ?? ($project['progress'] ?? 0));
                                    if ($progressValue < 0) {
                                        $progressValue = 0;
                                    }
                                    if ($progressValue > 100) {
                                        $progressValue = 100;
                                    }
                                @endphp
                                <div class="flex items-center gap-2 w-32">
                                    <progress class="progress flex-1" value="{{ $progressValue }}"
                                        max="100"></progress>
                                    <span class="text-xs text-gray-600">{{ $progressValue }}%</span>
                                </div>
                            </td>
                            <th>
                                @php
                                    // Project status is derived from tasks; display-only (no dropdown).
                                    // Derived in ProjectController@index via POST /api/v1/tasks/stats/batch:
                                    // - Completed only when all tasks are completed
                                    // - Not Started when project has no tasks
                                    // - Active when project has tasks but not all completed
                                    $displayStatus = $project['_derivedStatus'] ?? ($status ?: 'Unknown');
                                    $statusPillStyle = match ($displayStatus) {
                                        'Not Started' => 'background:#f3f4f6;color:#374151;',
                                        'Active' => 'background:#dbeafe;color:#1d4ed8;',
                                        'Completed' => 'background:#d1fae5;color:#065f46;',
                                        default => 'background:#f3f4f6;color:#374151;',
                                    };
                                @endphp
                                <div class="inline-flex items-center gap-2 rounded-none px-2 py-1 w-[9.5rem] overflow-hidden"
                                    style="{{ $statusPillStyle }}">
                                    <span class="shrink-0 text-current">
                                        <x-icons.circle />
                                    </span>
                                    <span
                                        class="text-xs font-medium whitespace-nowrap truncate">{{ $displayStatus }}</span>
                                </div>
                            </th>
                            <th>
                                <span class="!font-normal text-sm">
                                    {{ $createdAt ? \Carbon\Carbon::parse($createdAt)->format('m/d/Y') : '—' }}
                                </span>
                            </th>
                            <th>
                                <span class="!font-normal text-sm">
                                    {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('m/d/Y') : '—' }}
                                </span>
                            </th>
                            <th>
                                @if ($canOpenActionMenu)
                                    <div class="dropdown dropdown-end" wire:click.stop>
                                        <button tabindex="0" type="button" class="btn btn-ghost btn-sm px-2">
                                            <x-icons.three-dot classes="w-5 h-5" />
                                        </button>
                                        <ul tabindex="0"
                                            class="dropdown-content menu bg-base-100 rounded-box z-50 w-40 p-2 shadow-lg border">
                                            <li>
                                                <button type="button"
                                                    wire:click.stop="openDetails({{ (int) $projectId }})">
                                                    Details
                                                </button>
                                            </li>
                                            @if ($canManageProject)
                                                <li>
                                                    <button type="button"
                                                        wire:click.stop="startEdit({{ (int) $projectId }})">
                                                        Edit
                                                    </button>
                                                </li>
                                                <li>
                                                    <button type="button" class="text-red-600"
                                                        wire:click.stop="confirmDelete({{ (int) $projectId }})">
                                                        Delete
                                                    </button>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </th>
                        </tr>
                    @empty
                        <tr class="border-b border-gray-200">
                            <td colspan="7" class="text-center py-8 text-gray-500">No projects yet.</td>
                        </tr>
                    @endforelse
                @endif
            </tbody>
        </table>
    </div>
</div>

@once
    <script>
        function projectCreateOptimistic(config) {
            return {
                isSubmitting: false,
                optimisticProjects: [],
                showSuccessToast(message) {
                    const container = document.getElementById('toast-container');
                    if (!container) return;
                    const toast = document.createElement('div');
                    toast.className =
                        'pointer-events-auto rounded-lg border px-4 py-3 text-sm shadow border-green-200 bg-green-50 text-green-700';
                    toast.textContent = message;
                    container.appendChild(toast);
                    window.setTimeout(() => toast.remove(), 4200);
                },
                toInitials(name) {
                    const parts = (name || '').toString().trim().split(/\s+/).filter(Boolean);
                    if (!parts.length) return '?';
                    if (parts.length === 1) return parts[0].slice(0, 1).toUpperCase();
                    return (parts[0].slice(0, 1) + parts[1].slice(0, 1)).toUpperCase();
                },
                collectSelectedMembers(form) {
                    const members = [];
                    const rows = form.querySelectorAll('.overflow-x-auto table tbody tr');
                    rows.forEach((row) => {
                        const nameCell = row.querySelector('td:first-child span');
                        if (!nameCell) return;
                        const name = (nameCell.textContent || '').toString().trim();
                        if (!name || name.toLowerCase().includes('no members selected')) return;
                        const imageEl = row.querySelector('td:first-child img');
                        const profilePicture = imageEl
                            ? (imageEl.getAttribute('src') || '').toString().trim()
                            : '';
                        members.push({
                            name,
                            initials: this.toInitials(name),
                            profilePicture: profilePicture || null,
                        });
                    });
                    return members;
                },
                showErrorToast(message) {
                    const container = document.getElementById('toast-container');
                    if (!container) return;
                    const toast = document.createElement('div');
                    toast.className =
                        'pointer-events-auto rounded-lg border px-4 py-3 text-sm shadow border-red-200 bg-red-50 text-red-700';
                    toast.textContent = message;
                    container.appendChild(toast);
                    window.setTimeout(() => toast.remove(), 5500);
                },
                async submit(event, wire) {
                    if (this.isSubmitting) return;

                    const form = event.target;
                    if (!(form instanceof HTMLFormElement)) return;

                    const formData = new FormData(form);
                    const projectName = (formData.get('name') || '').toString().trim() || 'Project';
                    const endDate = (formData.get('endDate') || '').toString().trim();
                    const members = this.collectSelectedMembers(form);
                    const tempId = `tmp-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`;
                    const optimisticRow = {
                        tempId,
                        state: 'pending',
                        name: projectName,
                        createdByName: (config?.currentUserName || 'You').toString().trim() || 'You',
                        createdAt: new Date().toISOString().slice(0, 10),
                        endDate: endDate || '—',
                        status: 'Not Started',
                        members,
                    };

                    this.optimisticProjects.unshift(optimisticRow);
                    this.isSubmitting = true;

                    // Optimistic UX: close modal immediately while create runs in background.
                    try {
                        if (wire) {
                            wire.closeAddModal();
                        }
                    } catch (_) {}

                    try {
                        const response = await fetch(config.actionUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': config.csrfToken,
                            },
                            body: formData,
                        });

                        const payload = await response.json().catch(() => null);
                        if (!response.ok || !payload?.ok) {
                            throw new Error(payload?.message || 'Failed to add project. Please try again.');
                        }

                        const idx = this.optimisticProjects.findIndex((p) => p.tempId === tempId);
                        if (idx >= 0) {
                            const normalized = payload?.project || {};
                            this.optimisticProjects[idx] = {
                                ...this.optimisticProjects[idx],
                                state: 'created',
                                id: normalized.id || null,
                                name: (normalized.name || this.optimisticProjects[idx].name || 'Project').toString(),
                                status: (normalized.status || 'Not Started').toString(),
                                createdAt: (normalized.createdAt || this.optimisticProjects[idx].createdAt || '—').toString(),
                                endDate: (normalized.endDate || this.optimisticProjects[idx].endDate || '—').toString(),
                            };
                            // If backend doesn't provide a usable id, refresh list so created project becomes navigable.
                            if (!this.optimisticProjects[idx].id) {
                                window.setTimeout(() => {
                                    window.location.reload();
                                }, 700);
                            }
                        }
                        this.showSuccessToast((payload?.message || 'Project created successfully.').toString());

                        form.reset();
                    } catch (error) {
                        this.optimisticProjects = this.optimisticProjects.filter((p) => p.tempId !== tempId);
                        this.showErrorToast(error?.message || 'Failed to add project. Please try again.');
                        // Reopen modal on failure so user can correct and retry quickly.
                        try {
                            if (wire) {
                                wire.openModal();
                            }
                        } catch (_) {}
                    } finally {
                        this.isSubmitting = false;
                    }
                },
            };
        }
    </script>
@endonce
