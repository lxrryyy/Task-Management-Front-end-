<div x-data="{ open: false }">
    <div class="flex w-full items-center clr-primary">
        <a href="/dashboard" class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('projects') ? 'clr-primary' : '' }} hover-clr-accent">
            <x-icons.back-btn classes="w-6 h-6" />
        </a>
        <span class="group-hover:block text-xl">Audit Logs</span>
    </div>
    <hr class="border-2 border-gray-300" />

    <div class="flex flex-row justify-between mt-4">
        <div class="flex flex-row gap-4">
            <button @click="open = !open" class="btn border-2 border-gray clr-primary text-base-100 p-4 hover-clr-bg-primary hover:text-base-100">
                <x-icons.sort class="w-4 h-4 inline-block" /> Filter
            </button>
            <label class="input focus-within:outline-none bg-transparent focus-within:border-base-300 flex-1">
                <input wire:model.live.debounce.300ms="search" class="w-40 bg-transparent focus:outline-none rounded-lg" type="search" placeholder="Search" />
            </label>
        </div>
        <div class="flex flex-row gap-4">
            <button class="btn clr-bg-primary text-base-100 rounded-lg p-4"><x-icons.export classes="w-6 h-6" /> Export</button>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div x-show="open" x-transition class="flex flex-row gap-4 border border-gray-300 rounded-lg p-4 mt-2 bg-white">
        <div class="flex flex-col flex-1">
            <span>User</span>
            <select>
                <option>All User</option>
                <option>User 1</option>
                <option>User 2</option>
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Role</span>
            <select>
                <option>All roles</option>
                <option>Role 1</option>
                <option>Role 2</option>
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Project Name</span>
            <select>
                <option>All projects</option>
                <option>Project 1</option>
                <option>Project 2</option>
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Action</span>
            <select>
                <option>All actions</option>
                <option>Action 1</option>
                <option>Action 2</option>
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Status</span>
            <select>
                <option>All statuses</option>
                <option>Status 1</option>
                <option>Status 2</option>
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Date From</span>
            <input type="date" class="input input-sm input-bordered w-full" />
        </div>
        <div class="flex flex-col flex-1">
            <span>Date To</span>
            <input type="date" class="input input-sm input-bordered w-full" />
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto mt-4">
        <table class="table">
            <thead>
                <tr>
                    <th></th>
                    <th>Name</th>
                    <th>Job</th>
                    <th>Favorite Color</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th>1</th>
                    <td>Cy Ganderton</td>
                    <td>Quality Control Specialist</td>
                    <td>Blue</td>
                </tr>
                <tr>
                    <th>2</th>
                    <td>Hart Hagerty</td>
                    <td>Desktop Support Technician</td>
                    <td>Purple</td>
                </tr>
                <tr>
                    <th>3</th>
                    <td>Brice Swyre</td>
                    <td>Tax Accountant</td>
                    <td>Red</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
