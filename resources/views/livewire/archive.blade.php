<div class="">
    <div class="w-full">
        <div class="flex w-full items-center clr-primary ">
            <a href="/projects"
               class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap hover-clr-accent">
                <x-icons.back-btn classes="w-6 h-6" />
            </a>
            <span class="group-hover:block text-xl">Archived Projects</span>
        </div>
        <hr class="border-2 clr-bg-primary">

        <div class="flex items-center justify-end p-2 flex-shrink-0">
            <label class="input focus-within:outline-none bg-transparent focus-within:border-base-300 flex-1">
                <input wire:model.live.debounce.300ms="search" class="w-96 bg-transparent focus:outline-none rounded-xl" type="search" placeholder="Search" />
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Project Name</th>
                    <th>Project Manager</th>
                    <th>Members</th>
                    <th>Deleted At</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse(($filteredProjects ?? []) as $project)
                    @php
                        $projectId = (int) ($project['id'] ?? $project['Id'] ?? 0);
                        $name = $project['name'] ?? $project['projectName'] ?? $project['title'] ?? '—';
                        $projectManagerName = $project['projectManagerName'] ?? $project['ProjectManagerName'] ?? $project['createdByName'] ?? '—';
                        $projectManagerId = (int) ($project['projectManagerId'] ?? $project['ProjectManagerId'] ?? 0);
                        $status = $project['statusName'] ?? $project['status'] ?? '—';

                        // Prefer assigneeIds if present, else memberNames
                        $memberNames = [];
                        $assigneeIds = $project['assigneeIds'] ?? $project['AssigneeIds'] ?? null;
                        if (is_array($assigneeIds) && !empty($assigneeIds)) {
                            // Exclude project manager from members display
                            $excludeId = $projectManagerId > 0 ? $projectManagerId : (int) ($creatorId ?? 0);
                            foreach ($assigneeIds as $aid) {
                                $aid = (int) $aid;
                                if ($aid === $excludeId) continue;
                                $acc = collect($accounts ?? [])->first(fn ($a) => (int)($a['id'] ?? $a['Id'] ?? 0) === $aid);
                                if ($acc) $memberNames[] = trim($acc['name'] ?? $acc['Name'] ?? '');
                            }
                        }
                        if (empty($memberNames)) {
                            $raw = $project['memberNames'] ?? $project['members'] ?? $project['Members'] ?? [];
                            $memberNames = is_array($raw) ? $raw : [];
                            // Exclude project manager name from member names
                            if (!empty($memberNames) && $projectManagerName !== '—') {
                                $memberNames = array_values(array_filter(
                                    $memberNames,
                                    static fn($m) => trim((string)$m) !== trim((string)$projectManagerName)
                                ));
                            }
                            $memberNames = array_values(array_unique(array_map('trim', $memberNames)));
                        }
                        $membersDisplay = $memberNames ? implode(', ', $memberNames) : '—';

                        $deletedAt = $project['deletedAt'] ?? $project['DeletedAt'] ?? $project['deletedOn'] ?? $project['DeletedOn'] ?? null;
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td>{{ $name }}</td>
                        <td>{{ $projectManagerName }}</td>
                        <td class="text-sm">{{ $membersDisplay }}</td>
                        <td>{{ $deletedAt ? \Carbon\Carbon::parse($deletedAt)->format('m/d/Y') : '—' }}</td>
                        <td>
                            @if($projectId > 0)
                                <button class="btn clr-bg-primary text-base-100 p-2"
                                        wire:click="restoreProject({{ $projectId }})"
                                        wire:loading.attr="disabled"
                                        wire:target="restoreProject">
                                    Restore
                                </button>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-8 text-gray-500">No archived projects yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
