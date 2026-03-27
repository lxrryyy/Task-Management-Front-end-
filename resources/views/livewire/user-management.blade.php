<div class="flex flex-col">
    {{-- Add User Modal --}}
    <dialog id="add_user" class="modal">
        <div class="modal-box overflow-y-auto" style="height: 500px; width: min(90vw, 1100px); max-width: 1100px;">
            <h3 class="text-lg font-bold">Add user</h3>
            <hr>
            <div class="flex flex-col gap-4">
                <div class="flex flex-row gap-4">
                    <div class="flex flex-1 flex-col">
                        <label for="First Name">First Name</label>
                        <input type="text" />
                    </div>
                    <div class="flex flex-1 flex-col">
                        <label for="Last Name">Last Name</label>
                        <input type="text" />
                    </div>
                </div>
                <div class="flex flex-1 flex-col">
                    <label for="Email">Email</label>
                    <input type="text" />
                </div>
                <div class="flex flex-row justify-center items-center gap-4">
                    <div class="flex flex-1 flex-col">
                        <label for="Temporary Password">Temporary Password</label>
                        <input type="password" />
                        <label for="" class="text-xs">A welcome email with login credentials will be sent
                            automatically</label>
                    </div>
                    <div class="flex flex-col justify-center items-center mt-2">
                        <button
                            class="btn border border-gray-400 rounded-lg px-6 hover-clr-bg-primary hover:text-base-100">Generate
                            Password</button>
                    </div>
                </div>
                <hr>
                <div class="flex flex-col">
                    <label for="Bio">Bio/Specalization (Optional)</label>
                    <input type="text" />
                </div>
                <hr>
                <div class="flex justify-end">
                    <button class="btn clr-bg-primary text-base-100 p-4">Add User</button>
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
        <div class="flex flex-row justify-center items-center">
            <x-filter-dropdown></x-filter-dropdown>
            <x-search-input wire:model.live.debounce.300ms="search" />
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
                <tbody>
                    <tr>
                        <td>Cy Ganderton</td>
                        <td>Quality Control Specialist</td>
                        <td>Blue</td>
                    </tr>
                    <tr>
                        <td>Hart Hagerty</td>
                        <td>Desktop Support Technician</td>
                        <td>Purple</td>
                    </tr>
                    <tr>
                        <td>Brice Swyre</td>
                        <td>Tax Accountant</td>
                        <td>Red</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
