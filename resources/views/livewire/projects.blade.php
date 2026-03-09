<div class="">
        <div class="w-full">
            <div class="flex w-full items-center clr-primary ">
                <a href="/projects"
               class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('projects') ? 'clr-primary' : '' }} hover-clr-accent">
                <x-icons.back-btn classes="w-6 h-6" />
                </a>
                <span class="group-hover:block text-xl">Projects</span>
            </div>
            <hr class="border-2 clr-bg-primary">
            <div>
                <div class="flex items-center justify-between p-2 flex-shrink-0">
                    <div>
                        <button type="button" class="btn w-36 border-2 border-gray clr-primary rounded-xl m-1 hover-clr-bg-primary hover:text-white " wire:click="archiveSelected">
                            <x-icons.archive classes="w-4 h-4 inline-block" /> Archive
                        </button>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="input focus-within:outline-none bg-transparent focus-within:border-base-300 flex-1">
                            <input wire:model.live.debounce.300ms="search" class="w-96 bg-transparent focus:outline-none rounded-xl" type="search" placeholder="Search" />
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
                        <a wire:click="openModal" class="btn clr-bg-primary text-base-100 rounded-xl p-4">+ Add Project</a>
                    </div>

                    {{-- Add Project modal (create only) --}}
                    @if($showAddModal)
                    <dialog id="addProjectDialog" class="modal modal-open">
                        <div class="modal-box w-11/12 max-w-5xl overflow-y-auto">
                            <div class="modal-action">
                                <button type="button" wire:click="closeAddModal" class="btn">X</button>
                            </div>
                            <h3 class="font-bold text-lg">New Project</h3>
                            <form method="POST" action="{{ route('projects.store') }}" class="mt-4">
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
                            <h3 class="font-bold text-lg">Edit Project</h3>
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
                                        <h3 class="text-lg font-bold">Confirm Update</h3>
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
                            <h3 class="font-bold text-lg">Confirm Delete</h3>
                            <p class="py-4 text-sm text-gray-700">
                                Are you sure you want to delete
                                <span class="font-semibold break-words">{{ $deletingProjectName ?? 'this project' }}</span>?
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
                        <th>Project Name</th>
                        <th>Project Manager</th>
                        <th>Members</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
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
                        $assigneeIds = $project['assigneeIds'] ?? $project['AssigneeIds'] ?? null;
                        if (is_array($assigneeIds) && !empty($assigneeIds)) {
                            $creatorIdInt = (int)($creatorId ?? 0);
                            foreach ($assigneeIds as $aid) {
                                $aid = (int) $aid;
                                if ($aid === $creatorIdInt) continue;
                                $acc = collect($accounts ?? [])->first(fn ($a) => (int)($a['id'] ?? $a['Id'] ?? 0) === $aid);
                                if ($acc) {
                                    $memberNames[] = trim($acc['name'] ?? $acc['Name'] ?? '');
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
                        }
                        $membersDisplay = is_array($memberNames) && !empty($memberNames) ? implode(', ', $memberNames) : '—';
                        $memberCount = is_array($memberNames) ? count($memberNames) : ($project['memberCount'] ?? '—');
                        $projectLeaderId = $project['createdById'] ?? $project['CreatedById'] ?? null;
                        $isLeader = $projectLeaderId && (int) $projectLeaderId === (int) $creatorId;
                    @endphp
                    <tr class="hover:bg-gray-50 cursor-pointer"
                        @if($projectId)
                            @click="window.location='{{ route('projects.tasks', $projectId) }}'"
                        @endif
                    >
                        <td><span class="underline-offset-2">{{ $name }}</span></td>
                        <td>{{ $leaderDisplay }}</td>
                        <td>
                            @if($membersDisplay !== '—')
                                <span class="block text-sm">{{ $membersDisplay }}</span>
                            @else
                                <span class="text-sm text-gray-400">No members</span>
                            @endif
                        </td>
                        <th>
                            <progress class="progress w-24" value="{{ $project['completionPercentage'] ?? $project['progress'] ?? 0 }}" max="100"></progress>
                        </th>
                        <th>
                            @if($isLeader && $projectId && !empty($projectStatusItems))
                                @php
                                    $statusPillStyle = match($status) {
                                        'Not Started' => 'background:#f3f4f6;color:#374151;',
                                        'Active'      => 'background:#dbeafe;color:#1d4ed8;',
                                        'Completed'   => 'background:#d1fae5;color:#065f46;',
                                        default       => 'background:#f3f4f6;color:#374151;',
                                    };
                                @endphp
                                <div class="relative inline-flex items-center rounded-none pl-6 pr-2 py-1 w-full min-w-[8rem] overflow-visible" style="{{ $statusPillStyle }}" wire:click.stop>
                                    <span class="absolute left-2 top-1/2 -translate-y-1/2 pointer-events-none flex items-center shrink-0 w-1.5 h-1.5">
                                        <svg width="5" height="5" viewBox="0 0 5 5" fill="currentColor" class="text-current"><circle cx="2.5" cy="2.5" r="2.5" fill="currentColor"/></svg>
                                    </span>
                                    <select
                                        class="text-xs font-medium border-0 ring-0 shadow-none outline-none focus:ring-0 focus:outline-none cursor-pointer bg-transparent appearance-none pl-5 pr-1 py-1 w-full min-w-0"
                                        style="border:none;box-shadow:none;background-image:url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 24 24%22 stroke=%22%236b7280%22%3E%3Cpath stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%222%22 d=%22M19 9l-7 7-7-7%22/%3E%3C/svg%3E');background-repeat:no-repeat;background-position:right 0.25rem center;background-size:1rem;"
                                        x-data
                                        @change="$wire.updateProjectStatus({{ (int) $projectId }}, parseInt($event.target.value))"
                                    >
                                        @foreach($projectStatusItems as $item)
                                            @php
                                                $sid = (int) ($item['id'] ?? $item['Id'] ?? 0);
                                                $sname = $item['name'] ?? $item['Name'] ?? '';
                                            @endphp
                                            <option value="{{ $sid }}" {{ $currentStatusId === $sid ? 'selected' : '' }}>{{ $sname }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                @php
                                    $statusBadge = match($status) {
                                        'Not Started' => 'badge-ghost',
                                        'Active'      => 'badge-info',
                                        'Completed'   => 'badge-success',
                                        default       => 'badge-ghost',
                                    };
                                @endphp
                                <span class="badge {{ $statusBadge }}">{{ $status ?: 'Unknown' }}</span>
                            @endif
                        </th>
                        <th>
                            <span>
                                {{ $createdAt ? \Carbon\Carbon::parse($createdAt)->format('m/d/Y') : '—' }}
                            </span>
                        </th>
                        <th>
                            @if($isLeader && $projectId)
                            <div>
                                <button
                                    type="button"
                                    class="btn btn-sm bg-warning text-base-100 border-none hover:opacity-90 p-2"
                                    wire:click.stop="startEdit({{ (int) $projectId }})"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm clr-bg-accent text-base-100 border-none hover:opacity-90 p-2"
                                    wire:click.stop="confirmDelete({{ (int) $projectId }})"
                                >
                                    Delete
                                </button>
                            </div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </th>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-500">No projects yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
            </div>
    </div>

