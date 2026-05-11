<div class="flex flex-col">
    @if ($apiError)
        <div class="alert alert-error text-sm flex items-center gap-2 py-2 px-4 rounded-lg m-4">
            <span>{{ $apiError }}</span>
        </div>
    @endif

    {{-- API session role debug removed --}}

    @if ($createAccountSuccess)
        <div class="alert alert-success text-sm flex items-center gap-2 py-2 px-4 rounded-lg m-4">
            <span>{{ $createAccountSuccess }}</span>
        </div>
    @endif

    {{-- Add User Modal --}}
    <dialog class="{{ $showAddUserModal ? 'modal modal-open' : 'modal' }}">
        <div class="modal-box overflow-y-auto" style="max-height: 90vh; width: min(90vw, 1100px); max-width: 1100px;">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium">Add user</h3>
                <button type="button" class="btn btn-ghost btn-sm btn-circle" wire:click="closeAddUserModal">✕</button>
            </div>
            <hr>
            <div class="flex flex-col gap-4">
                <div class="flex flex-row gap-4">
                    <div class="flex flex-1 flex-col">
                        <label>First Name</label>
                        <input type="text" wire:model="newFirstName"
                            class="input input-bordered rounded-lg w-full" />
                        @error('newFirstName')
                            <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="flex flex-1 flex-col">
                        <label>Last Name</label>
                        <input type="text" wire:model="newLastName" class="input input-bordered rounded-lg w-full" />
                        @error('newLastName')
                            <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="flex flex-1 flex-col">
                    <label>Email</label>
                    <input type="text" wire:model="newEmail" class="input input-bordered rounded-lg w-full" />
                    @error('newEmail')
                        <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                    @enderror
                </div>
                <div class="flex flex-row justify-center items-center gap-4">
                    <div class="flex flex-1 flex-col">
                        <label>Temporary Password</label>
                        <div class="relative">
                            <input type="{{ $showPassword ? 'text' : 'password' }}" id="generated-password"
                                wire:model="newTemporaryPassword"
                                class="input input-bordered rounded-lg w-full pr-10" />
                            <button type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                                wire:click="toggleShowPassword">
                                @if (!$showPassword)
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21" />
                                    </svg>
                                @endif
                            </button>
                        </div>
                        @error('newTemporaryPassword')
                            <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                        @enderror
                        <label class="text-xs">A welcome email with login credentials will be sent automatically</label>
                    </div>
                    <div class="flex flex-col justify-center items-center mt-2">
                        <button type="button"
                            class="btn border border-gray-400 rounded-lg px-6 hover-clr-bg-primary hover:text-base-100"
                            wire:click="generateTemporaryPassword">
                            Generate Password
                        </button>
                    </div>
                </div>
                <hr>
                <div class="flex flex-col">
                    <label>Bio/Specialization (Optional)</label>
                    <input type="text" wire:model="newSpecialization"
                        class="input input-bordered rounded-lg w-full" />
                    @error('newSpecialization')
                        <span class="text-xs text-red-600 mt-1">{{ $message }}</span>
                    @enderror
                </div>
                <hr>
                <div class="flex justify-end">
                    <button type="button" class="btn clr-bg-primary text-base-100 p-4" wire:click="createAccount"
                        wire:target="createAccount" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="createAccount">Add User</span>
                        <span wire:loading wire:target="createAccount" class="inline-flex items-center gap-2">
                            <span class="loading loading-spinner loading-xs"></span>
                            Adding...
                        </span>
                    </button>
                </div>
            </div>
        </div>
        <div class="modal-backdrop" wire:click="closeAddUserModal"></div>
    </dialog>

    {{-- Edit User Modal --}}
    <dialog class="{{ $showEditUserModal ? 'modal modal-open' : 'modal' }}">
        <div class="modal-box overflow-y-auto" style="width: min(90vw, 980px); max-width: 980px;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium">Edit User</h3>
                <button type="button" class="btn btn-ghost btn-sm btn-circle"
                    wire:click="closeEditUserModal">✕</button>
            </div>
            <hr>

            @if ($editUserError)
                <div class="alert alert-error text-sm py-2 px-4 rounded-lg mt-3">
                    {{ $editUserError }}
                </div>
            @endif

            <div class="flex flex-col gap-4 mt-4">
                <div class="border border-gray-300 rounded-lg p-2 flex items-center gap-3">
                    <div class="avatar" data-user-mgmt-edit-avatar>
                        @php
                            $ef = trim((string) ($editFirstName ?? ''));
                            $el = trim((string) ($editLastName ?? ''));
                            $editInitials = mb_strtoupper(mb_substr($ef, 0, 1) . mb_substr($el, 0, 1));
                            $editPicRaw = is_string($editProfilePicture ?? null) ? trim($editProfilePicture) : '';
                            $editPic = \App\Support\AccountPresentation::profilePictureDisplayUrl(
                                $editPicRaw !== '' ? $editPicRaw : null,
                            ) ?? '';
                        @endphp
                        <div
                            class="w-12 h-12 rounded-full bg-amber-200 text-gray-700 flex items-center justify-center text-sm font-medium {{ $editPic !== '' ? 'hidden' : '' }}"
                            data-user-mgmt-edit-initials>
                            {{ $editInitials !== '' ? $editInitials : '?' }}
                        </div>
                        @if ($editPic !== '')
                            <img src="{{ $editPic }}" alt=""
                                class="w-12 h-12 rounded-full object-cover"
                                onerror="this.style.display='none'; var w=this.closest('[data-user-mgmt-edit-avatar]'); if(w){var i=w.querySelector('[data-user-mgmt-edit-initials]'); if(i){i.classList.remove('hidden');}}" />
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">{{ trim(($editFirstName ?? '') . ' ' . ($editLastName ?? '')) ?: 'User' }}</p>
                        <p class="text-xs text-gray-500">{{ $editEmail ?: '—' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col">
                        <label class="text-xs font-medium">First Name</label>
                        <input type="text" wire:model.defer="editFirstName" class="input input-bordered rounded-lg h-9 w-full" />
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-medium">Last Name</label>
                        <input type="text" wire:model.defer="editLastName" class="input input-bordered rounded-lg h-9 w-full" />
                    </div>
                </div>

                <div class="flex flex-col">
                    <label>Email</label>
                    <input type="text" value="{{ $editEmail }}"
                        class="input input-bordered rounded-lg h-9 w-full bg-gray-100" disabled />
                </div>
                <hr class="border-gray-200">

                <div class="grid grid-cols-[1fr_auto] gap-4 items-end">
                    <div class="flex flex-col">
                        <label class="text-xs font-medium">Bio / Specialization (optional)</label>
                        <input type="text" wire:model.defer="editSpecialization"
                            class="input input-bordered rounded-lg h-9 w-full" />
                    </div>
                    <label class="inline-flex items-center gap-2 mb-1">
                        <span class="text-base font-medium text-gray-800">Active</span>
                        <input type="checkbox" wire:model.defer="editIsActive" class="checkbox checkbox-sm rounded-lg" />
                    </label>
                </div>

                <div class="flex flex-col">
                    <label class="text-xs font-medium">Role</label>
                    <select wire:model.defer="editRole" class="select select-bordered rounded-lg h-9 w-full">
                        <option value="">...</option>
                        <option value="User">User</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <button type="button" class="btn clr-bg-primary text-base-100 p-4" wire:click="saveEditUser">
                    Save Changes
                </button>
            </div>
        </div>
        <div class="modal-backdrop" wire:click="closeEditUserModal"></div>
    </dialog>

    <div class="flex w-full items-center clr-primary ">
        <a href="/dashboard"
            class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('dashboard') ? 'clr-primary' : '' }} hover-clr-accent">
            <x-icons.back-btn classes="w-6 h-6" />
        </a>
        <span class="group-hover:block text-xl">User Management</span>
    </div>
    <hr class="border-2 clr-bg-primary">
    <div class="flex flex-wrap gap-2 justify-between mt-4">
        <div class="flex flex-row justify-center items-center gap-2">
            <x-search-input wire:model.live.debounce.300ms="search" />
        </div>
        <div class="flex">
            <button class="btn clr-bg-primary text-base-100 p-4" wire:click="openAddUserModal">+ Add User</button>
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
                    @if ($loading)
                        @foreach (range(1, 8) as $i)
                            <tr>
                                <td>
                                    <div class="h-4 bg-gray-200 rounded animate-pulse w-32"></div>
                                </td>
                                <td>
                                    <div class="h-4 bg-gray-200 rounded animate-pulse w-48"></div>
                                </td>
                                <td>
                                    <div class="h-4 bg-gray-200 rounded animate-pulse w-24"></div>
                                </td>
                                <td>
                                    <div class="h-4 bg-gray-200 rounded animate-pulse w-8"></div>
                                </td>
                                <td>
                                    <div class="h-4 bg-gray-200 rounded animate-pulse w-8"></div>
                                </td>
                                <td>
                                    <div class="h-4 bg-gray-200 rounded animate-pulse w-16"></div>
                                </td>
                                <td>
                                    <div class="h-4 bg-gray-200 rounded animate-pulse w-8"></div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        @forelse($filteredUsers as $user)
                            <tr class="hover:bg-gray-50">
                                <td>{{ $user['name'] ?? '—' }}</td>
                                <td>{{ $user['email'] ?? '—' }}</td>
                                <td>{{ $user['specialization'] ?? '—' }}</td>
                                <td>{{ (int) ($user['projectsCount'] ?? 0) }}</td>
                                <td>{{ (int) ($user['tasksCount'] ?? 0) }}</td>
                                <td>
                                    @if (isset($user['isActive']))
                                        <span
                                            class="px-2 py-1 text-xs rounded-full {{ $user['isActive'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}">
                                            {{ $user['isActive'] ? 'Active' : 'Inactive' }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td wire:click.stop>
                                    <div class="dropdown dropdown-end">
                                        <button tabindex="0" type="button"
                                            class="btn btn-ghost btn-sm px-2 rounded-lg border border-gray-200 bg-white shadow-sm hover:bg-gray-50 hover:border-gray-300">
                                            <x-icons.three-dot classes="w-5 h-5" />
                                        </button>
                                        <ul tabindex="0"
                                            class="dropdown-content menu bg-base-100 rounded-box z-50 w-40 p-2 shadow-lg border">
                                            <li class="border-b border-gray-100">
                                                <button type="button"
                                                    wire:click.stop="openUserDetail({{ (int) ($user['id'] ?? 0) }})">Details</button>
                                            </li>
                                            <li class="border-b border-gray-100">
                                                <button type="button"
                                                    wire:click.stop="editUser({{ (int) ($user['id'] ?? 0) }})">Edit</button>
                                            </li>
                                            <li>
                                                <button type="button"
                                                    wire:click.stop="toggleUserStatus({{ (int) ($user['id'] ?? 0) }}, {{ $user['isActive'] ? 'true' : 'false' }})">
                                                    {{ $user['isActive'] ? 'Deactivate' : 'Activate' }}
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
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- User detail modal --}}
    <dialog class="{{ $showUserDetailModal ? 'modal modal-open' : 'modal' }}">
        <div class="modal-box overflow-y-auto" style="width: min(90vw, 980px); max-width: 980px;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium">User Details</h3>
                <button type="button" class="btn btn-ghost btn-sm btn-circle"
                    wire:click="closeUserDetail">✕</button>
            </div>
            <hr>

            @php
                $su = is_array($selectedUser ?? null) ? $selectedUser : [];
                $detailName = (string) ($su['name'] ?? '');
                $parts = preg_split('/\s+/', trim($detailName));
                $parts = array_values(array_filter($parts, fn($p) => is_string($p) && trim($p) !== ''));
                $detailFirst = (string) ($parts[0] ?? '');
                $detailLast = (string) (count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '');
                $detailInitials = mb_strtoupper(mb_substr($detailFirst, 0, 1) . mb_substr($detailLast, 0, 1));
                $detailStatus = (bool) ($su['isActive'] ?? false);
                $detailPicRaw = $su['profilePicture'] ?? null;
                $detailPicTrim = is_string($detailPicRaw) ? trim($detailPicRaw) : '';
                $detailPic = \App\Support\AccountPresentation::profilePictureDisplayUrl(
                    $detailPicTrim !== '' ? $detailPicTrim : null,
                ) ?? '';
            @endphp

            <div class="flex flex-col gap-4 mt-4">
                <div class="border border-gray-300 rounded-lg p-2 flex items-center gap-3">
                    <div class="avatar" data-user-mgmt-detail-avatar>
                        <div
                            class="w-12 h-12 rounded-full bg-amber-200 text-gray-700 flex items-center justify-center text-sm font-medium {{ $detailPic !== '' ? 'hidden' : '' }}"
                            data-user-mgmt-detail-initials>
                            {{ $detailInitials !== '' ? $detailInitials : '?' }}
                        </div>
                        @if ($detailPic !== '')
                            <img src="{{ $detailPic }}" alt=""
                                class="w-12 h-12 rounded-full object-cover"
                                onerror="this.style.display='none'; var w=this.closest('[data-user-mgmt-detail-avatar]'); if(w){var i=w.querySelector('[data-user-mgmt-detail-initials]'); if(i){i.classList.remove('hidden');}}" />
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">{{ $su['name'] ?? 'User' }}</p>
                        <p class="text-xs text-gray-500">{{ $su['email'] ?? '—' }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col">
                        <label class="text-xs font-medium">First Name</label>
                        <input type="text" class="input input-bordered rounded-lg h-9 w-full bg-gray-100"
                            value="{{ $detailFirst ?: '—' }}" readonly />
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs font-medium">Last Name</label>
                        <input type="text" class="input input-bordered rounded-lg h-9 w-full bg-gray-100"
                            value="{{ $detailLast ?: '—' }}" readonly />
                    </div>
                </div>

                <div class="flex flex-col">
                    <label>Email</label>
                    <input type="text" class="input input-bordered rounded-lg h-9 w-full bg-gray-100"
                        value="{{ $su['email'] ?? '—' }}" readonly />
                </div>
                <hr class="border-gray-200">

                <div class="grid grid-cols-[1fr_auto] gap-4 items-end">
                    <div class="flex flex-col">
                        <label class="text-xs font-medium">Bio / Specialization (optional)</label>
                        <input type="text" class="input input-bordered rounded-lg h-9 w-full bg-gray-100"
                            value="{{ ($su['specialization'] ?? '—') === '—' ? '' : ($su['specialization'] ?? '') }}" readonly />
                    </div>
                    <label class="inline-flex items-center gap-2 mb-1">
                        <span class="text-base font-medium text-gray-800">Active</span>
                        <input type="checkbox" class="checkbox checkbox-sm rounded-lg" {{ $detailStatus ? 'checked' : '' }} disabled />
                    </label>
                </div>

                <div class="flex flex-col">
                    <label class="text-xs font-medium">Role</label>
                    <input type="text" class="input input-bordered rounded-lg h-9 w-full bg-gray-100"
                        value="{{ $su['status'] ?? '—' }}" readonly />
                </div>
            </div>

            <div class="modal-action mt-6">
                <button type="button" class="btn clr-bg-primary text-base-100 p-4" wire:click="closeUserDetail">
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

        document.addEventListener('livewire:init', () => {
            Livewire.on('password-generated', ({
                password
            }) => {
                const input = document.getElementById('generated-password');
                if (input) {
                    input.value = password;
                    input.dispatchEvent(new Event('input'));
                }
            });
        });
    </script>
</div>
