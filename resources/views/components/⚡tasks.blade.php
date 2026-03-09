<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Session;

new class extends Component
{
    public ?int $projectId = null;
    public array $tasks    = [];
    public array $accounts = [];

    public string $search      = '';
    public string $viewMode    = 'list';
    public ?string $moveError  = null;

    // status name => statusId  (fetched from GET /api/Task/GetAllTasksStatuses)
    public array $statusMap     = [];
    // ordered list of status names for board columns
    public array $taskStatuses  = [];
    // priority data: ['map' => [name=>id], 'names' => [...], 'items' => [{id,name}, ...]]
    public array $taskPriorities = [];

    // track which tasks' children are expanded (id => bool)
    public array $expanded = [];

    // Add Task modal state
    public bool $showAddTaskModal = false;
    public ?int $taskParentId     = null;

    // Task detail modal (view task info from list/board)
    public bool $showTaskDetailModal = false;
    public ?array $detailTask = null;

    public function mount(
        ?int $projectId        = null,
        array $tasks           = [],
        array $accounts        = [],
        bool $showAddTaskModal = false,
        ?int $taskParentId     = null,
    ): void {
        $this->projectId        = $projectId;
        $this->tasks            = $tasks;
        $this->accounts         = $accounts;
        $this->showAddTaskModal = $showAddTaskModal;
        $this->taskParentId     = $taskParentId;

        // Fetch all task statuses via TaskController
        $statusData = app(TaskController::class)->getStatuses();
        $this->statusMap    = $statusData['map'];
        $this->taskStatuses = $statusData['names'];

        // Fallback if API returned nothing
        if (empty($this->statusMap)) {
            $this->statusMap    = ['Not Started' => 1, 'In Progress' => 2, 'For Review' => 3, 'Completed' => 4];
            $this->taskStatuses = array_keys($this->statusMap);
        }

        // Fetch all task priorities via TaskController (returns map, names, items).
        // No hardcoded fallback — if API returns nothing, dropdown will be empty so you can see it.
        $this->taskPriorities = app(TaskController::class)->getPriorities();

        // Ensure project status reflects current task statuses on initial load.
        // (Status can be stale if tasks were completed elsewhere or before this UI change.)
        $this->syncProjectStatusFromTasks();
    }

    public function switchView(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function openTaskDetail(int $taskId): void
    {
        $task = collect($this->tasks)->first(fn ($t) => (int) ($t['id'] ?? $t['Id'] ?? 0) === $taskId);
        $this->detailTask = $task ?: null;
        $this->showTaskDetailModal = $this->detailTask !== null;
    }

    public function closeTaskDetail(): void
    {
        $this->showTaskDetailModal = false;
        $this->detailTask = null;
    }

    #[On('task-status-changed')]
    public function moveTask(int $taskId, string $newStatus): void
    {
        $statusId = $this->statusMap[$newStatus] ?? null;

        if ($statusId === null) {
            $this->moveError = "Cannot update: no statusId found for \"{$newStatus}\". Map: " . json_encode($this->statusMap);
            return;
        }

        // Build bidirectional maps:
        //   $childrenMap  — parent  → [childId, ...]  (cascade DOWN)
        //   $parentMap    — childId → parentId         (walk UP)
        $childrenMap = [];
        $parentMap   = [];
        foreach ($this->tasks as $task) {
            $pid = (int) ($task['parentTaskId'] ?? $task['parentId'] ?? $task['parentID'] ?? 0);
            $tid = (int) ($task['id'] ?? $task['Id'] ?? 0);
            if ($pid > 0 && $tid > 0) {
                $childrenMap[$pid][] = $tid;
                $parentMap[$tid]     = $pid;
            }
        }

        // ── Cascade DOWN: moved task + all its descendants ──────────────────
        $allIds = [$taskId];
        $queue  = [$taskId];
        while (!empty($queue)) {
            $current = array_shift($queue);
            foreach ($childrenMap[$current] ?? [] as $childId) {
                $allIds[] = $childId;
                $queue[]  = $childId;
            }
        }
        $allIdsSet = array_flip($allIds);

        // Optimistic update for the moved task and all its descendants
        $tasks = $this->tasks;
        foreach ($tasks as $i => $task) {
            $tid = (int) ($task['id'] ?? $task['Id'] ?? 0);
            if (isset($allIdsSet[$tid])) {
                $tasks[$i]['status']     = $newStatus;
                $tasks[$i]['statusName'] = $newStatus;
                $tasks[$i]['statusId']   = $statusId;
            }
        }

        // ── Cascade UP: auto-complete ancestors when every child is done ─────
        if (mb_strtolower($newStatus) === 'completed') {
            // Build a current-status snapshot from the already-updated $tasks
            $currentStatus = [];
            foreach ($tasks as $task) {
                $tid = (int) ($task['id'] ?? $task['Id'] ?? 0);
                $currentStatus[$tid] = mb_strtolower($task['statusName'] ?? $task['status'] ?? '');
            }

            $ancestorId = $parentMap[$taskId] ?? 0;

            while ($ancestorId > 0) {
                $directChildren = $childrenMap[$ancestorId] ?? [];

                // Stop climbing if this ancestor has no tracked children (edge case)
                if (empty($directChildren)) break;

                // Check every direct child of this ancestor
                $allChildrenDone = true;
                foreach ($directChildren as $childId) {
                    if (($currentStatus[$childId] ?? '') !== 'completed') {
                        $allChildrenDone = false;
                        break;
                    }
                }

                // If any child is not yet complete, the ancestor cannot be auto-completed
                // (and neither can any further ancestor)
                if (!$allChildrenDone) break;

                // Mark the ancestor as Completed in the local snapshot
                foreach ($tasks as $i => $task) {
                    $tid = (int) ($task['id'] ?? $task['Id'] ?? 0);
                    if ($tid === $ancestorId) {
                        $tasks[$i]['status']     = $newStatus;
                        $tasks[$i]['statusName'] = $newStatus;
                        $tasks[$i]['statusId']   = $statusId;
                        $currentStatus[$ancestorId] = 'completed';
                        $allIds[] = $ancestorId;
                        break;
                    }
                }

                // Continue up the tree
                $ancestorId = $parentMap[$ancestorId] ?? 0;
            }
        }

        $this->tasks = $tasks;

        // Persist every affected task (descendants + auto-completed ancestors)
        $this->moveError = null;
        $errors = [];
        foreach (array_unique($allIds) as $id) {
            try {
                app(TaskController::class)->updateStatus($this->projectId, $id, $statusId);
            } catch (\Throwable $e) {
                $errors[] = "Task #{$id}: " . $e->getMessage();
            }
        }
        if (!empty($errors)) {
            $this->moveError = 'Some updates failed: ' . implode(' | ', $errors);
            return;
        }

        // Project status depends on tasks:
        // - Completed if 100% tasks are Completed
        // - Not Started if all tasks are Not Started
        // - Active otherwise (any task has started)
        $this->syncProjectStatusFromTasks();
    }

    private function computeProjectStatusFromTasks(): string
    {
        if (empty($this->tasks)) {
            return 'Not Started';
        }

        // Derive project status from LEAF tasks (tasks with no children).
        // This avoids cases where parent tasks are still "Not Started" even though
        // every real work item (leaf) is completed (progress shows 100%).
        $childrenMap = [];
        $allIds = [];
        foreach ($this->tasks as $t) {
            $tid = (int) ($t['id'] ?? $t['Id'] ?? 0);
            if ($tid <= 0) continue;
            $allIds[$tid] = true;
            $pid = (int) ($t['parentTaskId'] ?? $t['parentId'] ?? $t['parentID'] ?? 0);
            if ($pid > 0) {
                $childrenMap[$pid][] = $tid;
            }
        }

        $leafTasks = [];
        foreach ($this->tasks as $t) {
            $tid = (int) ($t['id'] ?? $t['Id'] ?? 0);
            if ($tid <= 0) continue;
            if (empty($childrenMap[$tid])) {
                $leafTasks[] = $t;
            }
        }
        if (empty($leafTasks)) {
            // If everything is a parent (edge case), fall back to using all tasks.
            $leafTasks = $this->tasks;
        }

        $allCompleted  = true;
        $allNotStarted = true;

        foreach ($leafTasks as $t) {
            $raw = (string) ($t['statusName'] ?? $t['status'] ?? 'Not Started');
            $s = mb_strtolower(trim($raw));
            if ($s === 'notstarted') $s = 'not started';
            if ($s === '') $s = 'not started';

            if ($s !== 'completed') {
                $allCompleted = false;
            }
            if ($s !== 'not started') {
                $allNotStarted = false;
            }
        }

        if ($allCompleted) return 'Completed';
        if ($allNotStarted) return 'Not Started';
        return 'Active';
    }

    private function syncProjectStatusFromTasks(): void
    {
        $projectId = (int) ($this->projectId ?? 0);
        if ($projectId <= 0) return;

        $user = Session::get('user', []);
        $requesterId = (int) ($user['id'] ?? $user['Id'] ?? 0);
        if ($requesterId <= 0) return;

        $derived = $this->computeProjectStatusFromTasks();

        // Avoid extra PATCH if backend already matches
        try {
            $project = app(ProjectController::class)->getProjectData($projectId);
            $current = (string) ($project['statusName'] ?? $project['status'] ?? '');
            if (mb_strtolower(trim($current)) === mb_strtolower($derived)) {
                return;
            }
        } catch (\Throwable) {
            // If read fails, still attempt to patch.
        }

        $statusData = app(ProjectController::class)->getStatuses();
        $statusId = (int) (($statusData['map'][$derived] ?? 0));

        if ($statusId > 0) {
            app(ProjectController::class)->updateProjectStatusApi($projectId, $statusId, $requesterId);
            return;
        }

        // Fallback if map is missing: patch by name
        app(ProjectController::class)->updateProjectApi($projectId, ['status' => $derived], $requesterId);
    }

    public function toggle(int $taskId): void
    {
        $this->expanded[$taskId] = !($this->expanded[$taskId] ?? false);
    }

    public function openAddTaskModal(): void
    {
        $this->taskParentId = null;
        // Refresh priorities when opening the modal so the dropdown always has options
        // (avoids empty dropdown when initial page load had no session/API data)
        $this->taskPriorities = app(TaskController::class)->getPriorities();
        $this->showAddTaskModal = true;
    }

    public function closeAddTaskModal(): void
    {
        $this->showAddTaskModal = false;
        $this->taskParentId     = null;
    }

    public function addSubtask(int $parentTaskId): void
    {
        $this->taskParentId = $parentTaskId;
        $this->taskPriorities = app(TaskController::class)->getPriorities();
        $this->showAddTaskModal = true;
    }

    public function render()
    {
        $query = mb_strtolower(trim($this->search));

        if ($query === '') {
            $filtered = $this->tasks;
        } else {
            // Step 1: find tasks whose own fields match the query
            $matchingIds = [];
            foreach ($this->tasks as $task) {
                $id = $task['id'] ?? null;
                if ($id === null) continue;
                $haystack = implode(' ', [
                    mb_strtolower($task['name']             ?? $task['title']          ?? ''),
                    mb_strtolower($task['assigneeName']     ?? $task['assignedToName'] ?? ''),
                    mb_strtolower($task['statusName'] ?? $task['status'] ?? ''),
                    mb_strtolower($task['priority']         ?? ''),
                ]);
                if (str_contains($haystack, $query)) {
                    $matchingIds[$id] = true;
                }
            }

            // Step 2: build id → parentId map so we can walk up the tree
            $parentMap = [];
            foreach ($this->tasks as $task) {
                $id  = $task['id'] ?? null;
                $pid = $task['parentTaskId'] ?? $task['parentId'] ?? $task['parentID'] ?? null;
                if ($id !== null) {
                    $parentMap[$id] = $pid;
                }
            }

            // Step 3: include ancestors of every match so hierarchy stays intact
            $includedIds = $matchingIds;
            foreach (array_keys($matchingIds) as $id) {
                $cur = $id;
                while (isset($parentMap[$cur]) && $parentMap[$cur] !== null) {
                    $cur = $parentMap[$cur];
                    $includedIds[$cur] = true;
                }
            }

            $filtered = array_values(
                array_filter($this->tasks, fn ($t) => isset($includedIds[$t['id'] ?? null]))
            );
        }

        // Use statuses fetched from the API; fall back to known defaults if fetch failed
        $statuses = !empty($this->taskStatuses)
            ? $this->taskStatuses
            : ['Not Started', 'In Progress', 'For Review', 'Completed'];

        $grouped = array_fill_keys($statuses, []);
        foreach ($filtered as $task) {
            $pid = $task['parentTaskId'] ?? $task['parentId'] ?? $task['parentID'] ?? null;
            if ($pid !== null) continue;
            // Use statusName first (matches what list view displays), fall back to status
            $s = $task['statusName'] ?? $task['status'] ?? 'Not Started';
            if (!array_key_exists($s, $grouped)) {
                $grouped[$s] = [];
            }
            $grouped[$s][] = $task;
        }

        // Build id → display name map from the loaded accounts
        $accountMap = [];
        foreach ($this->accounts as $account) {
            $id   = $account['id'] ?? $account['Id'] ?? null;
            $name = $account['name']
                ?? $account['fullName']
                ?? $account['username']
                ?? trim(($account['firstName'] ?? '') . ' ' . ($account['lastName'] ?? ''))
                ?: null;
            if ($id !== null && $name !== null) {
                $accountMap[(int) $id] = $name;
            }
        }

        // Exclude the currently logged-in user (creator) from the assignee dropdown
        $user          = Session::get('user', []);
        $creatorId     = (int) ($user['id'] ?? $user['Id'] ?? 0);
        $assignableAccounts = array_values(array_filter(
            $this->accounts,
            fn($a) => (int) ($a['id'] ?? $a['Id'] ?? 0) !== $creatorId
        ));

        // Build priorityId => name map for resolving task priority display
        $taskPriorityMap = [];
        foreach ($this->taskPriorities['items'] ?? [] as $pr) {
            $pid = is_array($pr) ? ($pr['id'] ?? $pr['Id'] ?? null) : null;
            $pname = is_array($pr) ? ($pr['name'] ?? $pr['Name'] ?? '') : '';
            if ($pid !== null) {
                $taskPriorityMap[(int) $pid] = $pname;
            }
        }

        $user = Session::get('user', []);
        $currentUserName = $user['name'] ?? $user['Name'] ?? $user['fullName'] ?? trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? '')) ?: null;
        $currentUserId = (int) ($user['id'] ?? $user['Id'] ?? 0);

        return view('livewire.tasks', [
            'filteredTasks'      => $filtered,
            'boardStatuses'      => $statuses,
            'boardGrouped'       => $grouped,
            'accountMap'         => $accountMap,
            'assignableAccounts' => $assignableAccounts,
            'taskPriorities'     => $this->taskPriorities['items'] ?? [],
            'taskPriorityNames'  => $this->taskPriorities['names'] ?? [],
            'taskPriorityMap'    => $taskPriorityMap,
            'currentUserName'    => $currentUserName,
            'currentUserId'      => $currentUserId,
        ]);
    }
};
?>
