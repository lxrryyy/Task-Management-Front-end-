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
                    <dialog class="{{ $showModal ? 'modal modal-open' : 'modal' }}">
                        <div class="modal-box w-11/12 max-w-5xl overflow-y-auto">
                            <div class="modal-action">
                                <button wire:click="closeModal" class="btn">X</button>
                            </div>
                            <h3 class="font-bold text-lg">New Project</h3>
                            <div class="flex flex-col gap-4 my-4">
                                <span>Project Name</span>
                                <input type="text" placeholder="Type here" class="input input-bordered w-full" />
                            </div>
                            <div class="flex flex-col gap-4 my-4">
                                <span>Description</span>
                                <textarea class="textarea textarea-bordered w-full" placeholder="Project Description"></textarea>
                            </div>
                            <div class="flex flex-row gap-4 my-4">
                                <div class="flex flex-col gap-4 my-4">
                                    <span>Priority</span>
                                    <fieldset class="fieldset">
                                        <select class="select">
                                            <option disabled selected>Priority</option>
                                            <option>Urgent</option>
                                            <option>High</option>
                                            <option>Medium</option>
                                            <option>Low</option>
                                        </select>
                                    </fieldset>
                                </div>

                                <div class="flex flex-col gap-4 my-4">
                                    <span>Start Date</span>
                                    <input disabled type="date" class="input input-bordered" />
                                </div>

                                <div class="flex flex-col gap-4 my-4">
                                    <span>End Date</span>
                                    <input disabled type="date" class="input input-bordered" />
                                </div>

                            </div>

                            <div class="flex flex-col gap-4 my-4">
                                <span>Members</span>
                                <div class="form-control w-full">
                                <div class="dropdown w-full">
                                    <div tabindex="0" role="button" class="border-2 flex items-center gap-2 w-full px-3 py-2 cursor-pointer">
                                        <x-icons.user-icon class="w-6 h-6" />
                                        <span class="grow text-gray-400">Select member...</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                    <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[999] w-full shadow-lg border mt-1">
                                        <li><a>Member 1</a></li>
                                        <li><a>Member 2</a></li>
                                        <li><a>Member 3</a></li>
                                    </ul>
                                </div>
                            </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="table">
                                    <!-- head -->
                                    <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Position</th>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <!-- row -->
                                    <tr>
                                        <td>
                                            <span>Tristan Labjata</span>
                                        </td>
                                        <td>
                                            <span>labjata123@gmail.com</span>
                                        </td>
                                        <td>
                                            <div class="flex flex-col gap-4 my-4">
                                            <fieldset class="fieldset">
                                                <div class="dropdown w-full">
                                                    {{-- Trigger Button --}}
                                                    <div tabindex="0" role="button" class="select select-bordered w-full flex items-center justify-between">
                                                        <span x-text="selected ?? 'Member'">Member</span>
                                                    </div>

                                                    {{-- Options --}}
                                                    <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box shadow-lg border w-full z-[9999] mt-1">
                                                        <li><a wire:click="$set('role', 'Member')">Member</a></li>
                                                        <li><a wire:click="$set('role', 'Scrum Master')">Scrum Master</a></li>
                                                    </ul>
                                                </div>
                                            </fieldset>
                                        </div>
                                        </td>
                                        <th>
                                        <button class="btn btn-ghost btn-xs">details</button>
                                        </th>
                                    </tr>
                                    </tbody>
                                </table>
                                </div>
                        </div>
                        {{-- Backdrop click to close --}}
                        <form method="dialog" class="modal-backdrop">
                            <button wire:click="closeModal">close</button>
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
                <!-- row -->
                <tr>
                    <td>
                        <span>Email Bulk Sender</span>
                    </td>
                    <td>
                    Clarence May Espina
                    </td>
                    <td>Purple</td>
                    <th>
                        <progress class="progress w-24" value="50" max="100"></progress>
                    </th>
                    <th>
                        <span class="badge badge-success">Active</span>
                    </th>
                    <th>
                        <span>05/05/2025</span>
                    </th>
                    <th>
                        <button class="btn btn-ghost btn-xs">details</button>
                    </th>
                </tr>
                </tbody>
            </table>
            </div>
    </div>
