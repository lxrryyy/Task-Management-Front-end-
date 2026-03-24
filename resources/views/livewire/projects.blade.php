<div class="">
        <div class="w-full">
            <div class="flex w-full items-center clr-primary ">
                <a href="/dashboard"
               class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('projects') ? 'clr-primary' : '' }} hover-clr-accent">
                <x-icons.back-btn classes="w-6 h-6" />
                </a>
                <span class="group-hover:block text-xl">Projects</span>
            </div>
            <hr class="border-2 clr-bg-primary">
            <div>
                <div class="flex items-center justify-between p-2 flex-shrink-0">
                    <div>
                        <button type="button" class="btn w-36 border-2 border-gray clr-bg-primary text-base-100 rounded-xl m-1" wire:click="archiveSelected">
                            <x-icons.archive classes="w-4 h-4 inline-block" /> Archive
                        </button>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="input focus-within:outline-none bg-transparent focus-within:border-base-300 flex-1">
                            <input wire:model.live.debounce.300ms="search" class="w-40 bg-transparent focus:outline-none rounded-lg" type="search" placeholder="Search" />
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

                    <div>
                        <button type="button" wire:click="openModal" class="btn w-36 border-2 clr-bg-primary rounded-lg text-base-100">+ Add Project</button>
                    </div>

                    {{-- Add Project modal (create only) --}}
                    @if($showAddModal)
                    <dialog id="addProjectDialog" class="modal modal-open">
                        <div class="modal-box w-11/12 max-w-5xl overflow-y-auto">
                            <div class="modal-action">
                                <button type="button" wire:click="closeAddModal" class="btn">X</button>
                            </div>
                            <h3 class="font-normal text-lg">New Project</h3>
                            <form method="POST" action="{{ route('projects.store') }}" class="mt-0">
                                @csrf
                                @include('livewire.partials.project-form-fields', ['formContext' => 'add'])
                                <div class="modal-action">
                                    <button type="submit" class="btn clr-bg-primary text-base-100 px-2">Add Project</button>
                                </div>
                            </form>
                        </div>
                        <form method="dialog" class="modal-backdrop">
                            <button type="button" wire:click="closeAddModal">close</button>
                        </form>
                    </dialog>
                    @endif

                    {{-- Edit Project modal (update only) --}}
                    @if($showEditModal)
                    <dialog id="editProjectDialog" class="modal modal-open">
                        <div class="modal-box w-11/12 max-w-5xl overflow-y-auto">
                            <div class="modal-action">
                                <button type="button" wire:click="closeEditModal" class="btn">X</button>
                            </div>
                            <h3 class="font-normal text-lg">Edit Project</h3>
                            @if($editingProjectId)
                            <div>
                                <form class="mt-4" @submit.prevent>
                                    @include('livewire.partials.project-form-fields', ['formContext' => 'edit'])
                                    <div class="modal-action">
                                        <button type="button" class="btn clr-bg-primary text-base-100 px-2"
                                                wire:click="prepareEditSubmit">
                                            Update Project
                                        </button>
                                    </div>
                                </form>

                                {{-- Confirmation: shown after prepareEditSubmit locks member list (fixes member removal) --}}
                                @if($showConfirmDialog ?? false)
                                <div class="fixed inset-0 z-[9999] flex items-center justify-center">
                                    <div class="absolute inset-0" wire:click="cancelConfirmDialog"></div>
                                    <div class="relative bg-gray-100 rounded-2xl shadow-2xl border border-gray-200 p-6 w-94">
                                        <h3 class="text-lg font-normal">Confirm Update</h3>
                                        <p class="py-4 text-sm text-gray-600">Are you sure you want to save the changes to this project?</p>
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="btn btn-ghost clr-bg-primary text-base-100 p-2" wire:click="cancelConfirmDialog">Cancel</button>
                                            <button type="button" class="btn clr-bg-primary text-base-100 p-2" wire:click="submitEditProject">Yes, Update</button>
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
                    @if($showDeleteConfirmDialog ?? false)
                    <dialog id="deleteProjectDialog" class="modal modal-open">
                        <div class="modal-box w-11/12 max-w-md">
                            <h3 class="font-normal text-lg">Confirm Delete</h3>
                            <p class="py-4 text-sm text-gray-700">
                                Are you sure you want to delete
                                <span class="font-normal break-words">{{ $deletingProjectName ?? 'this project' }}</span>?
                            </p>
                            <div class="modal-action">
                                <button type="button" class="btn clr-bg-primary text-base-100 p-2" wire:click="cancelDelete">Cancel</button>
                                <button type="button" class="btn bg-red-600 hover:bg-red-700 text-base-100 border-none p-2" wire:click="deleteProject">Delete</button>
                            </div>
                        </div>
                        <form method="dialog" class="modal-backdrop">
                            <button type="button" wire:click="cancelDelete">close</button>
                        </form>
                    </dialog>
                    @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <!-- head -->
                <thead>
                    <tr>
                        <th class="!font-normal">Project Name</th>
                        <th class="!font-normal">Project Manager</th>
                        <th class="!font-normal">Members</th>
                        <th class="!font-normal">Progress</th>
                        <th class="!font-normal">Status</th>
                        <th class="!font-normal">Created At</th>
                        <th class="!font-normal">Action</th>
                    </tr>
                </thead>
                <tbody class="[&>tr>td]:border-b [&>tr>td]:border-gray-200 [&>tr>th]:border-b [&>tr>th]:border-gray-200">
                @forelse(($filteredProjects ?? []) as $project)
                    @php
                        $projectId = $project['id'] ?? $project['Id'] ?? null;
                        $name = $project['name'] ?? $project['projectName'] ?? $project['title'] ?? '—';
                        $status = $project['statusName'] ?? $project['status'] ?? '';
                        $currentStatusId = (int) ($project['statusId'] ?? $project['StatusId'] ?? ($projectStatusMap[$status] ?? 0));
                        $createdAt = $project['createdAt'] ?? null;

                        // Leader name comes from createdByName
                        $leaderDisplay = $project['createdByName'] ?? '—';

                        // Members: if API returns assigneeIds use that, else use memberNames (backend sometimes returns stale list after PATCH)
                        $memberNames = [];
                        $memberProfiles = [];
                        $assigneeIds = $project['assigneeIds'] ?? $project['AssigneeIds'] ?? null;
                        if (is_array($assigneeIds) && !empty($assigneeIds)) {
                            $creatorIdInt = (int)($creatorId ?? 0);
                            foreach ($assigneeIds as $aid) {
                                $aid = (int) $aid;
                                if ($aid === $creatorIdInt) continue;
                                $acc = collect($accounts ?? [])->first(fn ($a) => (int)($a['id'] ?? $a['Id'] ?? 0) === $aid);
                                if ($acc) {
                                    $memberName = trim($acc['name'] ?? $acc['Name'] ?? '');
                                    if ($memberName !== '') $memberNames[] = $memberName;

                                    $pp = $acc['profilePicture'] ?? $acc['ProfilePicture'] ?? null;

                                    // Build initials from name (fallback if no picture)
                                    $parts = preg_split('/\s+/', trim($memberName));
                                    $parts = array_values(array_filter($parts, fn ($p) => is_string($p) && trim($p) !== ''));
                                    $first = (string) ($parts[0] ?? '');
                                    $last = (string) (!empty($parts) ? implode(' ', array_slice($parts, 1)) : '');
                                    $a0 = mb_substr(trim($first), 0, 1);
                                    $b0 = mb_substr(trim($last), 0, 1);
                                    $initials = '';
                                    if ($a0 !== '' && $b0 !== '') $initials = mb_strtoupper($a0 . $b0);
                                    elseif ($a0 !== '') $initials = mb_strtoupper($a0);

                                    $memberProfiles[] = [
                                        'profilePicture' => $pp,
                                        'initials' => $initials ?: '?',
                                    ];
                                }
                            }
                        }
                        if (empty($memberNames)) {
                            $raw = $project['memberNames'] ?? $project['members'] ?? [];
                            $memberNames = is_array($raw) ? $raw : [];
                            if (!empty($memberNames)) {
                                if ($leaderDisplay !== '—') {
                                    $memberNames = array_values(array_filter(
                                        $memberNames,
                                        static fn ($m) => trim((string) $m) !== trim((string) $leaderDisplay)
                                    ));
                                }
                                $memberNames = array_values(array_unique(array_map('trim', $memberNames)));
                            }

                            // If backend didn't provide assigneeIds, we can only approximate member avatars from names.
                            if (!empty($memberNames)) {
                                $accountsList = collect($accounts ?? []);
                                foreach ($memberNames as $mn) {
                                    $mn = (string) trim($mn);
                                    $acc = $accountsList->first(function ($a) use ($mn) {
                                        $name = (string) ($a['name'] ?? $a['Name'] ?? '');
                                        return trim($name) === trim($mn);
                                    });

                                    $pp = $acc ? ($acc['profilePicture'] ?? $acc['ProfilePicture'] ?? null) : null;

                                    $parts = preg_split('/\s+/', trim($mn));
                                    $parts = array_values(array_filter($parts, fn ($p) => is_string($p) && trim($p) !== ''));
                                    $first = (string) ($parts[0] ?? '');
                                    $last = (string) (!empty($parts) ? implode(' ', array_slice($parts, 1)) : '');
                                    $a0 = mb_substr(trim($first), 0, 1);
                                    $b0 = mb_substr(trim($last), 0, 1);
                                    $initials = '';
                                    if ($a0 !== '' && $b0 !== '') $initials = mb_strtoupper($a0 . $b0);
                                    elseif ($a0 !== '') $initials = mb_strtoupper($a0);
                                    $memberProfiles[] = ['profilePicture' => $pp, 'initials' => $initials ?: '?'];
                                }
                            }
                        }
                        $memberCount = is_array($memberProfiles) ? count($memberProfiles) : 0;
                        $projectLeaderId = $project['createdById'] ?? $project['CreatedById'] ?? null;
                        $isLeader = $projectLeaderId && (int) $projectLeaderId === (int) $creatorId;
                    @endphp
                    <tr class="hover:bg-gray-50 cursor-pointer border-b border-gray-200"
                        @if($projectId)
                            @click="window.location='{{ route('projects.tasks', $projectId) }}'"
                        @endif
                    >
                        <td><span class="underline-offset-2">{{ $name }}</span></td>
                        <td>{{ $leaderDisplay }}</td>
                        <td>
                            @if($memberCount > 0)
                                @php
                                    $visibleProfiles = array_slice($memberProfiles, 0, 3);
                                    $overflowCount = max(0, (int) $memberCount - 3);
                                @endphp
                                <div class="avatar-group -space-x-3">
                                    @foreach($visibleProfiles as $mp)
                                        <div class="avatar" data-member-avatar>
                                            <div
                                                class="bg-neutral text-neutral-content w-6 h-6 rounded-full flex items-center justify-center relative overflow-hidden"
                                            >
                                                <span
                                                    data-member-initials
                                                    class="text-xs font-semibold leading-none {{ !empty($mp['profilePicture']) ? 'hidden' : '' }}"
                                                >
                                                    {{ $mp['initials'] ?? '?' }}
                                                </span>

                                                @if(!empty($mp['profilePicture']))
                                                    <img
                                                        src="{{ $mp['profilePicture'] }}"
                                                        alt=""
                                                        class="absolute inset-0 w-full h-full rounded-full object-cover"
                                                        loading="lazy"
                                                        referrerpolicy="no-referrer"
                                                        onerror="this.style.display='none'; var wrap=this.closest('[data-member-avatar]'); if(wrap){var sp=wrap.querySelector('[data-member-initials]'); if(sp){sp.classList.remove('hidden');}}"
                                                    />
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach

                                    @if($overflowCount > 0)
                                        <div class="avatar avatar-placeholder">
                                            <div class="bg-neutral text-neutral-content w-6 h-6 rounded-full flex items-center justify-center">
                                                <span class="text-xs font-semibold leading-none">+{{ $overflowCount }}</span>
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
                                $progressValue = (int) ($project['completionPercentage'] ?? $project['progress'] ?? 0);
                                if ($progressValue < 0) $progressValue = 0;
                                if ($progressValue > 100) $progressValue = 100;
                            @endphp
                            <div class="flex items-center gap-2 w-32">
                                <progress class="progress flex-1" value="{{ $progressValue }}" max="100"></progress>
                                <span class="text-xs text-gray-600">{{ $progressValue }}%</span>
                            </div>
                        </td>
                        <th>
                            @php
                                // Project status is derived from tasks; display-only (no dropdown).
                                // Derived in ProjectController@index using GetTasksByProject:
                                // - Completed only when all tasks are completed
                                // - Not Started when project has no tasks
                                // - Active when project has tasks but not all completed
                                $displayStatus = $project['_derivedStatus'] ?? ($status ?: 'Unknown');
                                $statusPillStyle = match($displayStatus) {
                                    'Not Started' => 'background:#f3f4f6;color:#374151;',
                                    'Active'      => 'background:#dbeafe;color:#1d4ed8;',
                                    'Completed'   => 'background:#d1fae5;color:#065f46;',
                                    default       => 'background:#f3f4f6;color:#374151;',
                                };
                            @endphp
                            <div class="inline-flex items-center gap-2 rounded-none px-2 py-1 w-[9.5rem] overflow-hidden" style="{{ $statusPillStyle }}">
                                <span class="shrink-0 text-current">
                                    <x-icons.circle />
                                </span>
                                <span class="text-xs font-medium whitespace-nowrap truncate">{{ $displayStatus }}</span>
                            </div>
                        </th>
                        <th>
                            <span class="!font-normal text-sm">
                                {{ $createdAt ? \Carbon\Carbon::parse($createdAt)->format('m/d/Y') : '—' }}
                            </span>
                        </th>
                        <th>
                            @if($isLeader && $projectId)
                            <div class="dropdown dropdown-end" wire:click.stop>
                                <button tabindex="0" type="button" class="btn btn-ghost btn-sm px-2">
                                    <x-icons.three-dot classes="w-5 h-5" />
                                </button>
                                <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-50 w-40 p-2 shadow-lg border">
                                    <li>
                                        <button type="button" wire:click.stop="startEdit({{ (int) $projectId }})">
                                            Edit
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="text-red-600" wire:click.stop="confirmDelete({{ (int) $projectId }})">
                                            Delete
                                        </button>
                                    </li>
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
                </tbody>
            </table>
            </div>
    </div>

