<?php

namespace App\Livewire;

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;
use Livewire\Component;

class Tasks extends Component
{
    public ?int $projectId = null;
    public array $tasks = [];
    public array $accounts = [];

    public string $search = '';
    public string $viewMode = 'list';
    public ?string $moveError = null;

    /** status name => statusId */
    public array $statusMap = [];
    /** ordered list of status names */
    public array $taskStatuses = [];
    /** priority data: ['map'=>..., 'names'=>..., 'items'=>...] */
    public array $taskPriorities = [];

    public array $expanded = [];

    public bool $showAddTaskModal = false;
    public ?int $taskParentId = null;

    /** Persist overload warnings across Livewire re-renders */
    public array $taskWarnings = [];

    public bool $showTaskDetailModal = false;
    public ?array $detailTask = null;

    public function mount(
        ?int $projectId = null,
        array $tasks = [],
        array $accounts = [],
        bool $showAddTaskModal = false,
        ?int $taskParentId = null,
    ): void {
        $this->projectId = $projectId;
        $this->tasks = $tasks;
        $this->accounts = $accounts;
        $this->showAddTaskModal = $showAddTaskModal;
        $this->taskParentId = $taskParentId;
        $this->taskWarnings = array_values(array_filter((array) Session::get('task_warnings', [])));

        $statusData = app(TaskController::class)->getStatuses();
        $this->statusMap = $statusData['map'] ?? [];
        $this->taskStatuses = $statusData['names'] ?? [];

        if (empty($this->statusMap)) {
            $this->statusMap = ['Not Started' => 1, 'In Progress' => 2, 'For Review' => 3, 'Completed' => 4];
            $this->taskStatuses = array_keys($this->statusMap);
        }

        $this->taskPriorities = app(TaskController::class)->getPriorities();

        // Sync project status to reflect current task statuses on load.
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

        $childrenMap = [];
        $parentMap = [];
        foreach ($this->tasks as $task) {
            $pid = (int) ($task['parentTaskId'] ?? $task['parentId'] ?? $task['parentID'] ?? 0);
            $tid = (int) ($task['id'] ?? $task['Id'] ?? 0);
            if ($pid > 0 && $tid > 0) {
                $childrenMap[$pid][] = $tid;
                $parentMap[$tid] = $pid;
            }
        }

        // Cascade DOWN
        $allIds = [$taskId];
        $queue = [$taskId];
        while (!empty($queue)) {
            $current = array_shift($queue);
            foreach ($childrenMap[$current] ?? [] as $childId) {
                $allIds[] = $childId;
                $queue[] = $childId;
            }
        }
        $allIdsSet = array_flip($allIds);

        $tasks = $this->tasks;
        foreach ($tasks as $i => $task) {
            $tid = (int) ($task['id'] ?? $task['Id'] ?? 0);
            if (isset($allIdsSet[$tid])) {
                $tasks[$i]['status'] = $newStatus;
                $tasks[$i]['statusName'] = $newStatus;
                $tasks[$i]['statusId'] = $statusId;
            }
        }

        // Cascade UP (auto-complete ancestors)
        if (mb_strtolower($newStatus) === 'completed') {
            $currentStatus = [];
            foreach ($tasks as $task) {
                $tid = (int) ($task['id'] ?? $task['Id'] ?? 0);
                $currentStatus[$tid] = mb_strtolower($task['statusName'] ?? $task['status'] ?? '');
            }

            $ancestorId = $parentMap[$taskId] ?? 0;
            while ($ancestorId > 0) {
                $directChildren = $childrenMap[$ancestorId] ?? [];
                if (empty($directChildren)) break;

                $allChildrenDone = true;
                foreach ($directChildren as $childId) {
                    if (($currentStatus[$childId] ?? '') !== 'completed') {
                        $allChildrenDone = false;
                        break;
                    }
                }
                if (!$allChildrenDone) break;

                foreach ($tasks as $i => $task) {
                    $tid = (int) ($task['id'] ?? $task['Id'] ?? 0);
                    if ($tid === $ancestorId) {
                        $tasks[$i]['status'] = $newStatus;
                        $tasks[$i]['statusName'] = $newStatus;
                        $tasks[$i]['statusId'] = $statusId;
                        $currentStatus[$ancestorId] = 'completed';
                        $allIds[] = $ancestorId;
                        break;
                    }
                }

                $ancestorId = $parentMap[$ancestorId] ?? 0;
            }
        }

        $this->tasks = $tasks;

        // Persist updates
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

        $this->syncProjectStatusFromTasks();
    }

    private function computeProjectStatusFromTasks(): string
    {
        if (empty($this->tasks)) return 'Not Started';

        // Leaf-only derivation (matches "progress" intuition).
        $childrenMap = [];
        foreach ($this->tasks as $t) {
            $tid = (int) ($t['id'] ?? $t['Id'] ?? 0);
            if ($tid <= 0) continue;
            $pid = (int) ($t['parentTaskId'] ?? $t['parentId'] ?? $t['parentID'] ?? 0);
            if ($pid > 0) $childrenMap[$pid][] = $tid;
        }

        $leafTasks = [];
        foreach ($this->tasks as $t) {
            $tid = (int) ($t['id'] ?? $t['Id'] ?? 0);
            if ($tid <= 0) continue;
            if (empty($childrenMap[$tid])) $leafTasks[] = $t;
        }
        if (empty($leafTasks)) $leafTasks = $this->tasks;

        $allCompleted = true;
        $allNotStarted = true;
        foreach ($leafTasks as $t) {
            $raw = (string) ($t['statusName'] ?? $t['status'] ?? 'Not Started');
            $s = mb_strtolower(trim($raw));
            if ($s === 'notstarted') $s = 'not started';
            if ($s === '') $s = 'not started';

            if ($s !== 'completed') $allCompleted = false;
            if ($s !== 'not started') $allNotStarted = false;
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

        try {
            $project = app(ProjectController::class)->getProjectData($projectId);
            $current = (string) ($project['statusName'] ?? $project['status'] ?? '');
            if (mb_strtolower(trim($current)) === mb_strtolower($derived)) return;
        } catch (\Throwable) {
            // continue
        }

        $statusData = app(ProjectController::class)->getStatuses();
        $statusId = (int) (($statusData['map'][$derived] ?? 0));

        if ($statusId > 0) {
            app(ProjectController::class)->updateProjectStatusApi($projectId, $statusId, $requesterId);
        } else {
            app(ProjectController::class)->updateProjectApi($projectId, ['status' => $derived], $requesterId);
        }
    }

    public function toggle(int $taskId): void
    {
        $this->expanded[$taskId] = !($this->expanded[$taskId] ?? false);
    }

    public function openAddTaskModal(): void
    {
        $this->taskParentId = null;
        $this->taskPriorities = app(TaskController::class)->getPriorities();
        $this->showAddTaskModal = true;
    }

    public function closeAddTaskModal(): void
    {
        $this->showAddTaskModal = false;
        $this->taskParentId = null;
        $this->taskWarnings = [];
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
            $matchingIds = [];
            foreach ($this->tasks as $task) {
                $id = $task['id'] ?? null;
                if ($id === null) continue;
                $haystack = implode(' ', [
                    mb_strtolower($task['name'] ?? $task['title'] ?? ''),
                    mb_strtolower($task['assigneeName'] ?? $task['assignedToName'] ?? ''),
                    mb_strtolower($task['statusName'] ?? $task['status'] ?? ''),
                    mb_strtolower($task['priority'] ?? ''),
                ]);
                if (str_contains($haystack, $query)) $matchingIds[$id] = true;
            }

            $parentMap = [];
            foreach ($this->tasks as $task) {
                $id = $task['id'] ?? null;
                $pid = $task['parentTaskId'] ?? $task['parentId'] ?? $task['parentID'] ?? null;
                if ($id !== null) $parentMap[$id] = $pid;
            }

            $includedIds = $matchingIds;
            foreach (array_keys($matchingIds) as $id) {
                $cur = $id;
                while (isset($parentMap[$cur]) && $parentMap[$cur] !== null) {
                    $cur = $parentMap[$cur];
                    $includedIds[$cur] = true;
                }
            }

            $filtered = array_values(array_filter($this->tasks, fn ($t) => isset($includedIds[$t['id'] ?? null])));
        }

        $statuses = !empty($this->taskStatuses) ? $this->taskStatuses : ['Not Started', 'In Progress', 'For Review', 'Completed'];

        $grouped = array_fill_keys($statuses, []);
        foreach ($filtered as $task) {
            $pid = $task['parentTaskId'] ?? $task['parentId'] ?? $task['parentID'] ?? null;
            if ($pid !== null) continue;
            $s = $task['statusName'] ?? $task['status'] ?? 'Not Started';
            if (!array_key_exists($s, $grouped)) $grouped[$s] = [];
            $grouped[$s][] = $task;
        }

        $accountMap = [];
        foreach ($this->accounts as $account) {
            $id = $account['id'] ?? $account['Id'] ?? null;
            $name = $account['name']
                ?? $account['fullName']
                ?? $account['username']
                ?? trim(($account['firstName'] ?? '') . ' ' . ($account['lastName'] ?? ''))
                ?: null;
            if ($id !== null && $name !== null) $accountMap[(int) $id] = $name;
        }

        $user = Session::get('user', []);
        $creatorId = (int) ($user['id'] ?? $user['Id'] ?? 0);
        $assignableAccounts = array_values(array_filter(
            $this->accounts,
            fn ($a) => (int) ($a['id'] ?? $a['Id'] ?? 0) !== $creatorId
        ));

        $taskPriorityMap = [];
        foreach ($this->taskPriorities['items'] ?? [] as $pr) {
            $pid = is_array($pr) ? ($pr['id'] ?? $pr['Id'] ?? null) : null;
            $pname = is_array($pr) ? ($pr['name'] ?? $pr['Name'] ?? '') : '';
            if ($pid !== null) $taskPriorityMap[(int) $pid] = $pname;
        }

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
}

