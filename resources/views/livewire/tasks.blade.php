<div class="flex flex-col gap-4">
    <div class="flex justify-between">
        <div class="flex gap-2">
            <button class="btn clr-bg-primary text-base-100 p-4"><x-icons.list class="w-4 h-4 inline-block" /> List</button>
            <button class="btn border-2 border-gray-400 clr-primary p-4 hover-clr-bg-primary hover:text-base-100 hover:border-none"><x-icons.board class="w-4 h-4 inline-block" /> Board View</button>
        </div>
        <div class="flex items-center">
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
            <button class="btn clr-bg-primary text-base-100 p-4">+ Add Task</button>
        </div>
    </div>

    <div class="overflow-x-auto max-h-[500px] relative">
        <table class="table w-full table-fixed">
            <colgroup>
                <col class="w-8"><!-- expand/collapse -->
                <col class="w-10"><!-- checkbox -->
                <col class="w-1/3"><!-- Task Name -->
                <col class="w-1/5"><!-- Assignee -->
                <col class="w-24"><!-- Due Date -->
                <col class="w-20"><!-- Story Point -->
                <col class="w-24"><!-- Status -->
                <col class="w-24"><!-- Priority -->
                <col class="w-20"><!-- Action -->
            </colgroup>
            <!-- head -->
            <thead>
            <tr class="bg-base-200">
                <th class="sticky top-0 z-10 bg-base-200"></th>
                <th class="sticky top-0 z-10 bg-base-200"></th>
                <th class="sticky top-0 z-10 bg-base-200">Task Name</th>
                <th class="sticky top-0 z-10 bg-base-200">Assignee</th>
                <th class="sticky top-0 z-10 bg-base-200">Due Date</th>
                <th class="sticky top-0 z-10 bg-base-200">Story Point</th>
                <th class="sticky top-0 z-10 bg-base-200">Status</th>
                <th class="sticky top-0 z-10 bg-base-200">Priority</th>
                <th class="sticky top-0 z-10 bg-base-200">Action</th>
            </tr>
            </thead>
            <tbody>
            <!-- Parent task -->
            <tr class="hover:bg-gray-50">
                <td>
                    <button
                        type="button"
                        class="btn btn-ghost btn-xs"
                        wire:click="toggleSubtasks"
                    >
                        {{ $showSubtasks ? '▾' : '▸' }}
                    </button>
                </td>
                <td>
                    <label>
                        <input type="checkbox" class="checkbox" />
                    </label>
                </td>
                <td>
                    <span class="font-semibold">Finish Login Page</span>
                </td>
                <td>Zemlak, Daniel and Leannon</td>
                <td>2026-03-05</td>
                <td>3</td>
                <td>
                    <span class="badge badge-success w-24">In Progress</span>
                </td>
                <td>
                    <span class="badge badge-warning">High</span>
                </td>
                <td>
                    <button class="btn btn-ghost btn-xs">details</button>
                </td>
            </tr>

            @if($showSubtasks)
                <!-- Subtask row -->
                <tr class="hover:bg-gray-50">
                    <td></td>
                    <td>
                        <label>
                            <input type="checkbox" class="checkbox checkbox-xs" />
                        </label>
                    </td>
                    <td class="pl-8">
                        <button
                            type="button"
                            class="btn btn-ghost btn-xs px-0 normal-case"
                            wire:click="toggleGrandchildren"
                        >
                            <span class="mr-1">{{ $showGrandchildren ? '▾' : '▸' }}</span>
                            <span class="text-sm">Design login UI</span>
                        </button>
                    </td>
                    <td>Airone Gamil</td>
                    <td>2026-03-04</td>
                    <td>1</td>
                    <td>
                        <span class="badge badge-success badge-sm">In Progress</span>
                    </td>
                    <td>
                        <span class="badge badge-ghost badge-sm">Medium</span>
                    </td>
                    <td>
                        <button class="btn btn-ghost btn-xs">Edit</button>
                    </td>
                </tr>

                @if($showGrandchildren)
                    <!-- Grandchild task rows -->
                    <tr class="hover:bg-gray-50">
                        <td></td>
                        <td>
                            <label>
                                <input type="checkbox" class="checkbox checkbox-xs" />
                            </label>
                        </td>
                        <td class="pl-14 text-xs">
                            Create Figma mockups
                        </td>
                        <td>Airone Gamil</td>
                        <td>2026-03-03</td>
                        <td>0.5</td>
                        <td>
                            <span class="badge badge-success badge-sm">In Progress</span>
                        </td>
                        <td>
                            <span class="badge badge-ghost badge-sm">Low</span>
                        </td>
                        <td>
                            <button class="btn btn-ghost btn-xs">Edit</button>
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td></td>
                        <td>
                            <label>
                                <input type="checkbox" class="checkbox checkbox-xs" />
                            </label>
                        </td>
                        <td class="pl-14 text-xs">
                            Review with team
                        </td>
                        <td>Airone Gamil</td>
                        <td>2026-03-03</td>
                        <td>0.5</td>
                        <td>
                            <span class="badge badge-ghost badge-sm">Todo</span>
                        </td>
                        <td>
                            <span class="badge badge-ghost badge-sm">Low</span>
                        </td>
                        <td>
                            <button class="btn btn-ghost btn-xs">Edit</button>
                        </td>
                    </tr>
                @endif

                <!-- Another subtask -->
                <tr class="hover:bg-gray-50">
                    <td></td>
                    <td>
                        <label>
                            <input type="checkbox" class="checkbox checkbox-xs" />
                        </label>
                    </td>
                    <td class="pl-8 text-sm">
                        Implement validation
                    </td>
                    <td>Other Member</td>
                    <td>2026-03-06</td>
                    <td>1</td>
                    <td>
                        <span class="badge badge-ghost badge-sm">Todo</span>
                    </td>
                    <td>
                        <span class="badge badge-ghost badge-sm">Medium</span>
                    </td>
                    <td>
                        <button class="btn btn-ghost btn-xs">Edit</button>
                    </td>
                </tr>
            @endif
            </tbody>
        </table>
        </div>
</div>
