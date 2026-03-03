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
            @php
                // Group tasks by parentTaskId (null => parents)
                $byParent = [];
                foreach ($tasks as $task) {
                    $pid = $task['parentTaskId'] ?? $task['parentId'] ?? $task['parentID'] ?? null;
                    $key = $pid ?? '__root__';
                    $byParent[$key][] = $task;
                }

                $parents = $byParent['__root__'] ?? [];

                $fmt = function (array $task) {
                    $taskName    = $task['name'] ?? $task['title'] ?? '—';
                    $assignee    = $task['assigneeName'] ?? $task['assignedToName'] ?? $task['reporterName'] ?? '—';
                    $dueDateRaw  = $task['dueDate'] ?? $task['dueAt'] ?? null;
                    $storyPoints = $task['storyPoints'] ?? $task['storyPoint'] ?? $task['points'] ?? null;
                    $status      = $task['status'] ?? '—';
                    $priority    = $task['priority'] ?? '—';

                    return compact('taskName','assignee','dueDateRaw','storyPoints','status','priority');
                };
            @endphp

            @forelse($parents as $parent)
                @php
                    $p = $fmt($parent);
                    $parentId = $parent['id'] ?? null;
                    $children = $parentId !== null ? ($byParent[$parentId] ?? []) : [];
                    $hasChildren = !empty($children);
                    $isExpanded = $parentId !== null && ($expanded[$parentId] ?? false);
                @endphp
                <!-- Parent task -->
                <tr class="hover:bg-gray-50">
                    <td>
                        @if($hasChildren && $parentId !== null)
                            <button
                                type="button"
                                class="btn btn-ghost btn-xs"
                                wire:click="toggle({{ $parentId }})"
                            >
                                {{ $isExpanded ? '▾' : '▸' }}
                            </button>
                        @endif
                    </td>
                    <td>
                        <label>
                            <input type="checkbox" class="checkbox" />
                        </label>
                    </td>
                    <td>
                        <span class="font-semibold">{{ $p['taskName'] }}</span>
                    </td>
                    <td>{{ $p['assignee'] }}</td>
                    <td>
                        @if($p['dueDateRaw'])
                            {{ \Carbon\Carbon::parse($p['dueDateRaw'])->format('Y-m-d') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $p['storyPoints'] ?? '—' }}</td>
                    <td>
                        <span class="badge badge-success w-24">{{ $p['status'] }}</span>
                    </td>
                    <td>
                        <span class="badge badge-warning">{{ $p['priority'] }}</span>
                    </td>
                    <td>
                        <button class="btn btn-ghost btn-xs">details</button>
                    </td>
                </tr>

                @if($isExpanded && $parentId !== null)
                    @foreach($children as $child)
                        @php
                            $c = $fmt($child);
                            $childId = $child['id'] ?? null;
                            $grandChildren = $childId !== null ? ($byParent[$childId] ?? []) : [];
                            $childHasChildren = !empty($grandChildren);
                            $childExpanded = $childId !== null && ($expanded[$childId] ?? false);
                        @endphp
                        <!-- Subtask row -->
                        <tr class="hover:bg-gray-50">
                            <td>
                                @if($childHasChildren && $childId !== null)
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-xs"
                                        wire:click="toggle({{ $childId }})"
                                    >
                                        {{ $childExpanded ? '▾' : '▸' }}
                                    </button>
                                @endif
                            </td>
                            <td>
                                <label>
                                    <input type="checkbox" class="checkbox checkbox-xs" />
                                </label>
                            </td>
                            <td class="pl-10">
                                <span class="text-sm">{{ $c['taskName'] }}</span>
                            </td>
                            <td>{{ $c['assignee'] }}</td>
                            <td>
                                @if($c['dueDateRaw'])
                                    {{ \Carbon\Carbon::parse($c['dueDateRaw'])->format('Y-m-d') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $c['storyPoints'] ?? '—' }}</td>
                            <td>
                                <span class="badge badge-success badge-sm">{{ $c['status'] }}</span>
                            </td>
                            <td>
                                <span class="badge badge-ghost badge-sm">{{ $c['priority'] }}</span>
                            </td>
                            <td>
                                <button class="btn btn-ghost btn-xs">Edit</button>
                            </td>
                        </tr>

                        @if($childExpanded && $childId !== null)
                            @foreach($grandChildren as $gc)
                                @php($g = $fmt($gc))
                                <!-- Grandchild task rows -->
                                <tr class="hover:bg-gray-50">
                                    <td></td>
                                    <td>
                                        <label>
                                            <input type="checkbox" class="checkbox checkbox-xs" />
                                        </label>
                                    </td>
                                    <td class="pl-16 text-xs">
                                        {{ $g['taskName'] }}
                                    </td>
                                    <td>{{ $g['assignee'] }}</td>
                                    <td>
                                        @if($g['dueDateRaw'])
                                            {{ \Carbon\Carbon::parse($g['dueDateRaw'])->format('Y-m-d') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $g['storyPoints'] ?? '—' }}</td>
                                    <td>
                                        <span class="badge badge-ghost badge-sm">{{ $g['status'] }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-ghost badge-sm">{{ $g['priority'] }}</span>
                                    </td>
                                    <td>
                                        <button class="btn btn-ghost btn-xs">Edit</button>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                @endif
            @empty
                <tr>
                    <td colspan="9" class="text-center py-8 text-gray-500">
                        No tasks for this project yet.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
