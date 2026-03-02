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

    <div class="overflow-x-auto">
        <table class="table">
            <!-- head -->
            <thead>
            <tr>
                <th></th>
                <th>
                </th>
                <th>Task Name</th>
                <th>Assignee</th>
                <th>Due Date</th>
                <th>Story Point</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <!-- row 1 -->
            <tr>
                <th><x-icons.vector class="w-4 h-4 inline-block" /></th>
                <th>
                <label>
                    <input type="checkbox" class="checkbox" />
                </label>
                </th>
                <td>
                    <span>Finish Login Page</span>
                </td>
                <td>
                Zemlak, Daniel and Leannon
                </td>
                <td>Purple</td>
                <th>
                <button class="btn btn-ghost btn-xs">details</button>
                </th>
                <th>
                <button class="btn btn-ghost btn-xs">details</button>
                </th>
                <th>
                <button class="btn btn-ghost btn-xs">details</button>
                </th>
                <th>
                <button class="btn btn-ghost btn-xs">details</button>
                </th>
            </tr>
            </tbody>
        </table>
        </div>
</div>