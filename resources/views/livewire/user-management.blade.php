<div class="flex flex-col">
    @if($apiError)
        <div class="alert alert-error text-sm flex items-center gap-2 py-2 px-4 rounded-lg m-4">
            <span>{{ $apiError }}</span>
        </div>
    @endif

    {{-- API session role debug removed --}}

    @if($createAccountError)
        <div class="alert alert-error text-sm flex items-center gap-2 py-2 px-4 rounded-lg m-4">
            <span>{{ $createAccountError }}</span>
        </div>
    @endif

    @if(!empty($createAccountErrors))
        <div class="alert alert-error text-sm m-4">
            <div class="font-semibold px-1">API validation error</div>
            <ul class="list-disc list-inside mt-2 px-1">
                @foreach($createAccountErrors as $msg)
                    <li>{{ $msg }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($createAccountSuccess)
        <div class="alert alert-success text-sm flex items-center gap-2 py-2 px-4 rounded-lg m-4">
            <span>{{ $createAccountSuccess }}</span>
        </div>
    @endif

    {{-- Add User Modal --}}
    <dialog id="add_user" class="modal" wire:ignore.self>
        <div class="modal-box overflow-y-auto" style="height: 500px; width: min(90vw, 1100px); max-width: 1100px;">
            <h3 class="text-lg font-bold">Add user</h3>
            <hr>
            <div class="flex flex-col gap-4">
                <div class="flex flex-row gap-4">
                    <div class="flex flex-1 flex-col">
                        <label for="First Name">First Name</label>
                        <input type="text" wire:model.defer="newFirstName" class="input input-bordered rounded-lg w-full" />
                    </div>
                    <div class="flex flex-1 flex-col">
                        <label for="Last Name">Last Name</label>
                        <input type="text" wire:model.defer="newLastName" class="input input-bordered rounded-lg w-full" />
                    </div>
                </div>
                <div class="flex flex-1 flex-col">
                    <label for="Email">Email</label>
                    <input type="text" wire:model.defer="newEmail" class="input input-bordered rounded-lg w-full" />
                </div>
                <div class="flex flex-row justify-center items-center gap-4">
                    <div class="flex flex-1 flex-col">
                        <label for="Temporary Password">Temporary Password</label>
                        <input type="password" wire:model.defer="newTemporaryPassword" class="input input-bordered rounded-lg w-full" />
                        <label for="" class="text-xs">A welcome email with login credentials will be sent
                            automatically</label>
                    </div>
                    <div class="flex flex-col justify-center items-center mt-2">
                        <button
                            type="button"
                            class="btn border border-gray-400 rounded-lg px-6 hover-clr-bg-primary hover:text-base-100"
                            wire:click="generateTemporaryPassword"
                        >
                            Generate Password
                        </button>
                    </div>
                </div>
                <hr>
                <div class="flex flex-col">
                    <label for="Bio">Bio/Specalization (Optional)</label>
                    <input type="text" wire:model.defer="newSpecialization" class="input input-bordered rounded-lg w-full" />
                </div>
                <hr>
                <div class="flex justify-end">
                    <button
                        type="button"
                        class="btn clr-bg-primary text-base-100 p-4"
                        wire:click="createAccount"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove> Add User </span>
                        <span wire:loading>Creating...</span>
                    </button>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
    <div class="flex w-full items-center clr-primary ">
        <a href="/dashboard"
            class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('dashboard') ? 'clr-primary' : '' }} hover-clr-accent">
            <x-icons.back-btn classes="w-6 h-6" />
        </a>
        <span class="group-hover:block text-xl">User Management</span>
    </div>
    <hr class="border-2 clr-bg-primary">
    <div class="flex flex-row justify-between mt-4">
        <div class="flex flex-row justify-center items-center gap-2">
            <x-search-input wire:model.live.debounce.300ms="search" />
            <x-filter-dropdown clear-action="resetFilters">
                <div class="flex flex-col gap-2 text-sm">
                    <span class="text-xs text-gray-500">Search fields</span>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" class="checkbox checkbox-xs"
                            wire:model.live="filterUser" />
                        <span>User</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" class="checkbox checkbox-xs"
                            wire:model.live="filterStatus" />
                        <span>Status</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" class="checkbox checkbox-xs"
                            wire:model.live="filterSpecialization" />
                        <span>Specialization</span>
                    </label>
                </div>
            </x-filter-dropdown>
        </div>
        <div class="flex">
            <button class="btn clr-bg-primary text-base-100 p-4" onclick="add_user.showModal()">+ Add User</button>
        </div>

    </div>
    <div class="">
        <div class="overflow-x-auto">
            <table class="table">
                <!-- head -->
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Specialization</th>
                        <th>Projects</th>
                        <th>Tasks</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody class="[&>tr>td]:border-b [&>tr>td]:border-gray-200">
                    @forelse($filteredUsers as $user)
                        <tr class="hover:bg-gray-50">
                            <td>{{ $user['name'] ?? '—' }}</td>
                            <td>{{ $user['email'] ?? '—' }}</td>
                            <td>{{ $user['specialization'] ?? '—' }}</td>
                            <td>{{ (int) ($user['projectsCount'] ?? 0) }}</td>
                            <td>{{ (int) ($user['tasksCount'] ?? 0) }}</td>
                            <td>{{ $user['status'] ?? '—' }}</td>
                            <td wire:click.stop>
                                <div class="dropdown dropdown-end">
                                    <button
                                        tabindex="0"
                                        type="button"
                                        class="btn btn-ghost btn-sm px-2 rounded-lg border border-gray-200 bg-white shadow-sm hover:bg-gray-50 hover:border-gray-300"
                                    >
                                        <x-icons.three-dot classes="w-5 h-5" />
                                    </button>
                                    <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-50 w-40 p-2 shadow-lg border">
                                        <li class="border-b border-gray-100">
                                            <button
                                                type="button"
                                                wire:click.stop="openUserDetail({{ (int) ($user['id'] ?? 0) }})"
                                            >
                                                Details
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-500">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- User detail modal --}}
    <dialog class="{{ $showUserDetailModal ? 'modal modal-open' : 'modal' }}">
        <div class="modal-box w-11/12 max-w-2xl overflow-y-auto">
            <div class="flex items-start justify-between gap-4 mb-4">
                <h3 class="text-lg font-bold">
                    {{ $selectedUser['name'] ?? 'User' }}
                </h3>
                <button type="button" class="btn btn-ghost btn-sm btn-circle" wire:click="closeUserDetail">✕</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div><span class="text-gray-500">Email</span><div class="font-medium">{{ $selectedUser['email'] ?? '—' }}</div></div>
                <div><span class="text-gray-500">Specialization</span><div class="font-medium">{{ $selectedUser['specialization'] ?? '—' }}</div></div>
                <div><span class="text-gray-500">Projects</span><div class="font-medium">{{ (int) ($selectedUser['projectsCount'] ?? 0) }}</div></div>
                <div><span class="text-gray-500">Tasks</span><div class="font-medium">{{ (int) ($selectedUser['tasksCount'] ?? 0) }}</div></div>
                <div class="sm:col-span-2"><span class="text-gray-500">Status</span><div class="font-medium">{{ $selectedUser['status'] ?? '—' }}</div></div>
            </div>

            <div class="modal-action mt-6">
                <button type="button" class="btn clr-bg-primary text-base-100" wire:click="closeUserDetail">
                    Close
                </button>
            </div>
        </div>
    </dialog>

    <script>
        const bindCloseAddUserModal = () => {
            if (!window.Livewire?.on) return;
            Livewire.on('closeAddUserModal', () => {
                const el = document.getElementById('add_user');
                if (el && typeof el.close === 'function') el.close();
            });
        };

        document.addEventListener('livewire:init', bindCloseAddUserModal);
        document.addEventListener('livewire:load', bindCloseAddUserModal);
    </script>
</div>
