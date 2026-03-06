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
                <div class="flex items-center justify-end p-2 flex-shrink-0">
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
                    <dialog id="addProjectDialog" class="{{ $showAddModal ? 'modal modal-open' : 'modal' }}">
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

                    {{-- Edit Project modal (update only) --}}
                    <dialog id="editProjectDialog" class="{{ $showEditModal ? 'modal modal-open' : 'modal' }}">
                        <div class="modal-box w-11/12 max-w-5xl overflow-y-auto">
                            <div class="modal-action">
                                <button type="button" wire:click="closeEditModal" class="btn">X</button>
                            </div>
                            <h3 class="font-bold text-lg">Edit Project</h3>
                            @if($editingProjectId)
                            <div x-data="{ confirmOpen: false }">
                                <form x-ref="editProjectForm" method="POST" action="{{ route('projects.update', $editingProjectId) }}" class="mt-4">
                                    @csrf
                                    @method('PATCH')
                                    @include('livewire.partials.project-form-fields', ['formContext' => 'edit'])
                                    <div class="modal-action">
                                        <button type="button" class="btn clr-bg-primary text-base-100 px-2"
                                                @click="confirmOpen = true">
                                            Update Project
                                        </button>
                                    </div>
                                </form>

                                {{-- Centered card; invisible backdrop blocks clicks without adding extra darkness --}}
                                <div x-show="confirmOpen"
                                     style="display:none"
                                     class="fixed inset-0 z-[9999] flex items-center justify-center">
                                    <div class="absolute inset-0" @click="confirmOpen = false"></div>
                                    <div class="relative bg-gray-100 rounded-2xl shadow-2xl border border-gray-200 p-6 w-94">
                                        <h3 class="text-lg font-bold">Confirm Update</h3>
                                        <p class="py-4 text-sm text-gray-600">Are you sure you want to save the changes to this project?</p>
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="btn btn-ghost clr-bg-primary text-base-100 p-2" @click="confirmOpen = false">Cancel</button>
                                            <button type="button" class="btn clr-bg-primary text-base-100 p-2"
                                                    @click="$refs.editProjectForm.submit()">Yes, Update</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                        <form method="dialog" class="modal-backdrop">
                            <button type="button" wire:click="closeEditModal">close</button>
                        </form>
                    </dialog>
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
                        <th>Leader</th>
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
                        $createdAt = $project['createdAt'] ?? null;

                        // Leader name comes from createdByName
                        $leaderDisplay = $project['createdByName'] ?? '—';

                        // Members: prefer names array, fall back to count
                        $memberNames = $project['memberNames'] ?? $project['members'] ?? [];
                        if (is_array($memberNames)) {
                            // Exclude leader from members list to avoid duplicate display
                            if ($leaderDisplay !== '—') {
                                $memberNames = array_values(array_filter(
                                    $memberNames,
                                    static fn ($m) => trim((string) $m) !== trim((string) $leaderDisplay)
                                ));
                            }
                            $membersDisplay = $memberNames ? implode(', ', $memberNames) : '—';
                            $memberCount = count($memberNames);
                        } else {
                            $membersDisplay = $memberNames ?: '—';
                            $memberCount = $project['memberCount'] ?? '—';
                        }
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
                            @php
                            $statusBadge = match($status) {
                                'Not Started' => 'badge-ghost',
                                'Active'      => 'badge-info',
                                'Completed'   => 'badge-success',
                                default       => 'badge-ghost',
                            };
                        @endphp
                        <span class="badge {{ $statusBadge }}">{{ $status ?: 'Unknown' }}</span>
                        </th>
                        <th>
                            <span>
                                {{ $createdAt ? \Carbon\Carbon::parse($createdAt)->format('m/d/Y') : '—' }}
                            </span>
                        </th>
                        <th>
                            @php
                                $projectLeaderId = $project['createdById'] ?? $project['CreatedById'] ?? null;
                                $isLeader = $projectLeaderId && (int) $projectLeaderId === (int) $creatorId;
                            @endphp
                            @if($isLeader && $projectId)
                                <button
                                    type="button"
                                    class="btn btn-sm bg-warning text-base-100 border-none hover:opacity-90 p-2"
                                    wire:click.stop="startEdit({{ (int) $projectId }})"
                                >
                                    Edit
                                </button>
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

