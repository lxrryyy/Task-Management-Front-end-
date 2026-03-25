<?php

namespace App\Livewire;

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Services\CsharpApiService;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;
use Livewire\Component;

class Tasks extends Component
{
    public ?int $projectId = null;
    /** Project payload from GetProjectById (used for delete permissions: PM / Scrum Master). */
    public ?array $project = null;
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
    public array $taskComments = [];
    public string $newComment = '';
    public ?int $editingCommentId = null;
    public string $editingCommentContent = '';
    public ?string $commentError = null;

    // Delete confirmation modal state
    public bool $showDeleteConfirmModal = false;
    public ?int $pendingDeleteTaskId = null;
    public ?string $pendingDeleteTaskName = null;

    public function mount(
        ?int $projectId = null,
        array $tasks = [],
        array $accounts = [],
        bool $showAddTaskModal = false,
        ?int $taskParentId = null,
        ?int $openTaskId = null,
        ?int $openCommentId = null,
        ?array $project = null,
    ): void {
        $this->projectId = $projectId;
        $this->project = $project;
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

        if ($openTaskId !== null && $openTaskId > 0) {
            $commentId = ($openCommentId !== null && $openCommentId > 0) ? $openCommentId : null;
            $this->openTaskDetail($openTaskId, $commentId);
        }
    }

    public function switchView(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function openTaskDetail(int $taskId, ?int $scrollToCommentId = null): void
    {
        $task = collect($this->tasks)->first(fn ($t) => (int) ($t['id'] ?? $t['Id'] ?? 0) === $taskId);
        $this->detailTask = $task ?: null;
        $this->showTaskDetailModal = $this->detailTask !== null;
        $this->newComment = '';
        $this->editingCommentId = null;
        $this->editingCommentContent = '';
        $this->commentError = null;
        $this->taskComments = [];

        if ($this->showTaskDetailModal) {
            $this->loadTaskComments($taskId);
        }
    }

    public function closeTaskDetail(): void
    {
        $this->showTaskDetailModal = false;
        $this->detailTask = null;
        $this->taskComments = [];
        $this->newComment = '';
        $this->editingCommentId = null;
        $this->editingCommentContent = '';
        $this->commentError = null;
    }

    private function currentAccountId(): int
    {
        $user = Session::get('user', []);
        return (int) ($user['id'] ?? $user['Id'] ?? 0);
    }

    private function loadTaskComments(int $taskId): void
    {
        $this->commentError = null;
        try {
            $raw = app(CsharpApiService::class)->get("/api/TaskComment/GetCommentsByTask/{$taskId}");
            $list = is_array($raw)
                ? ($raw['data'] ?? $raw['comments'] ?? $raw['items'] ?? (isset($raw[0]) ? $raw : []))
                : [];

            $comments = [];
            foreach ((array) $list as $c) {
                if (!is_array($c)) continue;
                $comments[] = [
                    'id' => (int) ($c['id'] ?? $c['Id'] ?? 0),
                    'taskId' => (int) ($c['taskId'] ?? $c['TaskId'] ?? 0),
                    'accountId' => (int) ($c['accountId'] ?? $c['AccountId'] ?? 0),
                    'accountName' => (string) ($c['accountName'] ?? $c['AccountName'] ?? 'User'),
                    'content' => (string) ($c['content'] ?? $c['Content'] ?? ''),
                    'createdAt' => (string) ($c['createdAt'] ?? $c['CreatedAt'] ?? ''),
                    'updatedAt' => (string) ($c['updatedAt'] ?? $c['UpdatedAt'] ?? ''),
                ];
            }
            $this->taskComments = $comments;
        } catch (\Throwable $e) {
            $this->taskComments = [];
            $this->commentError = 'Failed to load comments.';
        }
    }

    public function addComment(): void
    {
        $taskId = (int) ($this->detailTask['id'] ?? $this->detailTask['Id'] ?? 0);
        $accountId = $this->currentAccountId();
        $content = trim($this->newComment);

        if ($taskId <= 0 || $accountId <= 0) return;
        if ($content === '') return;

        $this->commentError = null;
        try {
            app(CsharpApiService::class)->post(
                "/api/TaskComment/CreateComment?accountId={$accountId}",
                [
                    'taskId' => $taskId,
                    'content' => $content,
                ]
            );
            $this->newComment = '';
            $this->loadTaskComments($taskId);
        } catch (\Throwable $e) {
            $this->commentError = 'Failed to add comment.';
        }
    }

    public function startEditComment(int $commentId): void
    {
        $comment = collect($this->taskComments)->first(fn ($c) => (int)($c['id'] ?? 0) === $commentId);
        if (!$comment) return;
        $this->editingCommentId = $commentId;
        $this->editingCommentContent = (string) ($comment['content'] ?? '');
    }

    public function cancelEditComment(): void
    {
        $this->editingCommentId = null;
        $this->editingCommentContent = '';
    }

    public function updateComment(int $commentId): void
    {
        $accountId = $this->currentAccountId();
        $taskId = (int) ($this->detailTask['id'] ?? $this->detailTask['Id'] ?? 0);
        $content = trim($this->editingCommentContent);
        if ($accountId <= 0 || $commentId <= 0 || $taskId <= 0) return;
        if ($content === '') return;

        $this->commentError = null;
        try {
            app(CsharpApiService::class)->patch(
                "/api/TaskComment/UpdateComment/{$commentId}?accountId={$accountId}",
                ['content' => $content]
            );
            $this->editingCommentId = null;
            $this->editingCommentContent = '';
            $this->loadTaskComments($taskId);
        } catch (\Throwable $e) {
            $this->commentError = 'Failed to update comment.';
        }
    }

    public function deleteComment(int $commentId): void
    {
        $accountId = $this->currentAccountId();
        $taskId = (int) ($this->detailTask['id'] ?? $this->detailTask['Id'] ?? 0);
        if ($accountId <= 0 || $commentId <= 0 || $taskId <= 0) return;

        $this->commentError = null;
        try {
            app(CsharpApiService::class)->delete("/api/TaskComment/DeleteComment/{$commentId}?accountId={$accountId}");
            if ($this->editingCommentId === $commentId) {
                $this->editingCommentId = null;
                $this->editingCommentContent = '';
            }
            $this->loadTaskComments($taskId);
        } catch (\Throwable $e) {
            $this->commentError = 'Failed to delete comment.';
        }
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

    private function canDeleteTasks(): bool
    {
        $user = Session::get('user', []);
        $uid = (int) ($user['id'] ?? $user['Id'] ?? 0);
        if ($uid <= 0) {
            return false;
        }

        $role = mb_strtolower(trim((string) ($user['role'] ?? $user['Role'] ?? $user['roleName'] ?? $user['RoleName'] ?? '')));
        if ($role === 'admin' || $role === 'superadmin') {
            return true;
        }

        $proj = $this->project;
        if (! is_array($proj) || $proj === []) {
            return false;
        }

        $pmId = (int) (
            $proj['projectManagerId'] ?? $proj['ProjectManagerId']
            ?? $proj['createdById'] ?? $proj['CreatedById']
            ?? $proj['createdBy'] ?? $proj['CreatedBy'] ?? 0
        );

        if ($pmId > 0 && $pmId === $uid) {
            return true;
        }

        $smId = (int) ($proj['scrumMasterId'] ?? $proj['ScrumMasterId'] ?? 0);
        if ($smId > 0 && $smId === $uid) {
            return true;
        }

        return false;
    }

    private function reloadTasksFromApi(): void
    {
        $pid = (int) ($this->projectId ?? 0);
        $requesterId = $this->currentAccountId();
        if ($pid <= 0 || $requesterId <= 0) {
            return;
        }

        try {
            $api = app(CsharpApiService::class);
            $projectResponse = $api->get(
                "/api/Task/GetTasksByProject/{$pid}",
                ['requesterId' => $requesterId]
            );
            $allTasks = is_array($projectResponse)
                ? $projectResponse
                : ($projectResponse['data'] ?? $projectResponse['tasks'] ?? []);

            $assignedIds = [];
            try {
                $assignedResponse = $api->get("/api/Task/GetTasksByAssignee/{$requesterId}");
                $assignedTasks = is_array($assignedResponse)
                    ? $assignedResponse
                    : ($assignedResponse['data'] ?? $assignedResponse['tasks'] ?? []);
                foreach ($assignedTasks as $t) {
                    if (isset($t['id'])) {
                        $assignedIds[(int) $t['id']] = true;
                    }
                }
            } catch (\Throwable) {
                // ignore
            }

            $tasks = [];
            foreach ((array) $allTasks as $t) {
                if (! is_array($t)) {
                    continue;
                }
                if (isset($t['id']) && isset($assignedIds[(int) $t['id']])) {
                    $t['isMine'] = true;
                } else {
                    $t['isMine'] = false;
                }
                $tasks[] = $t;
            }
            $this->tasks = $tasks;
        } catch (\Throwable) {
            // keep existing $this->tasks
        }
    }

    public function confirmDeleteTask(int $taskId): void
    {
        if (! $this->canDeleteTasks()) {
            $this->moveError = 'You do not have permission to delete tasks.';
            return;
        }

        $this->pendingDeleteTaskId = $taskId;
        $task = collect($this->tasks)->first(fn ($t) => (int) ($t['id'] ?? $t['Id'] ?? 0) === $taskId);
        $this->pendingDeleteTaskName = trim((string) ($task['name'] ?? $task['title'] ?? 'this task')) ?: 'this task';
        $this->showDeleteConfirmModal = true;
    }

    public function cancelDeleteTask(): void
    {
        $this->showDeleteConfirmModal = false;
        $this->pendingDeleteTaskId = null;
        $this->pendingDeleteTaskName = null;
    }

    public function deleteTask(int $taskId): void
    {
        if (! $this->canDeleteTasks()) {
            $this->moveError = 'You do not have permission to delete tasks.';

            return;
        }

        $pid = (int) ($this->projectId ?? 0);
        $requesterId = $this->currentAccountId();
        if ($pid <= 0 || $taskId <= 0 || $requesterId <= 0) {
            return;
        }

        $detailId = (int) ($this->detailTask['id'] ?? $this->detailTask['Id'] ?? 0);

        try {
            app(CsharpApiService::class)->delete("/api/Task/DeleteTask/{$taskId}?requesterId={$requesterId}");
        } catch (\Throwable) {
            $this->moveError = 'Failed to delete task. Please try again.';

            return;
        }

        $this->showDeleteConfirmModal = false;
        $this->pendingDeleteTaskId = null;
        $this->pendingDeleteTaskName = null;

        if ($detailId === $taskId) {
            $this->closeTaskDetail();
        }

        $this->reloadTasksFromApi();
        $this->moveError = null;
        $this->syncProjectStatusFromTasks();
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
        $accountProfiles = [];
        foreach ($this->accounts as $account) {
            $id = $account['id'] ?? $account['Id'] ?? null;
            $name = $account['name']
                ?? $account['fullName']
                ?? $account['username']
                ?? trim(($account['firstName'] ?? '') . ' ' . ($account['lastName'] ?? ''))
                ?: null;
            if ($id === null) {
                continue;
            }

            $accountId = (int) $id;
            if ($name !== null) {
                $accountMap[$accountId] = $name;
            }

            $profilePicture = $account['profilePicture'] ?? $account['ProfilePicture'] ?? null;

            $parts = preg_split('/\s+/', trim((string) ($name ?? '')));
            $parts = array_values(array_filter($parts, fn ($p) => is_string($p) && trim($p) !== ''));
            $first = (string) ($parts[0] ?? '');
            $last = (string) (!empty($parts) ? implode(' ', array_slice($parts, 1)) : '');
            $a0 = mb_substr(trim($first), 0, 1);
            $b0 = mb_substr(trim($last), 0, 1);
            if ($a0 !== '' && $b0 !== '') {
                $initials = mb_strtoupper($a0 . $b0);
            } elseif ($a0 !== '') {
                $initials = mb_strtoupper($a0);
            } else {
                $initials = '?';
            }

            $accountProfiles[$accountId] = [
                'profilePicture' => $profilePicture,
                'initials' => $initials,
                'name' => $name,
            ];
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
            'accountProfiles'   => $accountProfiles,
            'assignableAccounts' => $assignableAccounts,
            'taskPriorities'     => $this->taskPriorities['items'] ?? [],
            'taskPriorityNames'  => $this->taskPriorities['names'] ?? [],
            'taskPriorityMap'    => $taskPriorityMap,
            'currentUserName'    => $currentUserName,
            'currentUserId'      => $currentUserId,
            'canDeleteTasks'     => $this->canDeleteTasks(),
        ]);
    }
}

