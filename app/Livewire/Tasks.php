<?php

namespace App\Livewire;

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Services\CsharpApiService;
use App\Support\AccountPresentation;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ViewErrorBag;
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

    public string $filterStatus = '';

    public string $filterPriority = '';

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    /** status name => statusId */
    public array $statusMap = [];

    /** ordered list of status names */
    public array $taskStatuses = [];

    /** priority data: ['map'=>..., 'names'=>..., 'items'=>...] */
    public array $taskPriorities = [];

    public array $expanded = [];

    public bool $showAddTaskModal = false;

    public ?int $taskParentId = null;

    /** Synced from rich-text editor (Alpine) via Livewire.set; required for name="description". */
    public string $description = '';

    /** Persist overload warnings across Livewire re-renders */
    public array $taskWarnings = [];

    public bool $showTaskDetailModal = false;

    public ?array $detailTask = null;

    /** ordered ancestor chain from root -> current (for detail modal) */
    public array $detailBreadcrumb = [];

    public array $taskComments = [];

    public string $newComment = '';

    public ?int $editingCommentId = null;

    public string $editingCommentContent = '';

    public ?string $commentError = null;

    public ?string $commentToastMessage = null;

    public string $commentToastType = 'success';

    // Delete confirmation modal state
    public bool $showDeleteConfirmModal = false;

    public ?int $pendingDeleteTaskId = null;

    public ?string $pendingDeleteTaskName = null;

    public bool $loading = true;
    public int $listVisibleLimit = 30;
    public int $listVisibleStep = 30;
    public int $boardVisibleStep = 25;
    public array $boardVisibleByStatus = [];

    /** Current task-level context; null means root (parent tasks). */
    public ?int $currentParentTaskId = null;

    /** Session flash copied on first load so alerts survive Livewire re-renders (flash is one-request only). */
    public ?string $flashSuccess = null;

    /** @var list<string> */
    public array $flashErrorMessages = [];

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

        $requestedView = (string) request()->query('view', '');
        if ($requestedView === 'board' || $requestedView === 'list') {
            $this->viewMode = $requestedView;
        }

        $this->taskWarnings = array_values(array_filter((array) Session::get('task_warnings', [])));

        $this->flashSuccess = session()->get('success') ?: null;
        $this->flashErrorMessages = $this->captureFlashedErrors();

        $statusData = app(TaskController::class)->getStatuses();
        $this->statusMap = $statusData['map'] ?? [];
        $this->taskStatuses = $statusData['names'] ?? [];

        if (empty($this->statusMap)) {
            $this->statusMap = ['Not Started' => 1, 'In Progress' => 2, 'For Review' => 3, 'Completed' => 4];
            $this->taskStatuses = array_keys($this->statusMap);
        }
        $this->resetVisibleWindows();

        $this->taskPriorities = app(TaskController::class)->getPriorities();
        $this->syncProjectStatusFromTasks();

        $requestedParent = (int) request()->query('parentTaskId', 0);
        if ($requestedParent > 0) {
            $this->currentParentTaskId = $requestedParent;
        }

        if ($openTaskId !== null && $openTaskId > 0) {
            $commentId = ($openCommentId !== null && $openCommentId > 0) ? $openCommentId : null;
            $this->openTaskDetail($openTaskId, $commentId);
        }

        $this->dispatch('tasks-loaded');
    }

    public function dismissFlashSuccess(): void
    {
        $this->flashSuccess = null;
    }

    public function dismissFlashErrors(): void
    {
        $this->flashErrorMessages = [];
    }

    /**
     * @return list<string>
     */
    private function captureFlashedErrors(): array
    {
        $bag = session()->get('errors');
        if (! $bag instanceof ViewErrorBag) {
            return [];
        }
        $out = [];
        foreach ($bag->getBags() as $namedBag) {
            foreach ($namedBag->getMessages() as $msgs) {
                foreach ((array) $msgs as $m) {
                    $t = trim((string) $m);
                    if ($t !== '') {
                        $out[] = $t;
                    }
                }
            }
        }

        return array_values(array_unique($out));
    }

    #[On('tasks-loaded')]
    public function onTasksLoaded(): void
    {
        $this->loading = false;
    }

    public function switchView(string $mode): void
    {
        $this->viewMode = $mode;
        $this->resetVisibleWindows();
    }

    public function enterTaskLevel(int $taskId): void
    {
        if ($taskId <= 0) {
            return;
        }

        $exists = collect($this->tasks)->first(fn ($t) => (int) ($t['id'] ?? $t['Id'] ?? 0) === $taskId);
        if (! $exists) {
            return;
        }

        $this->currentParentTaskId = $taskId;
        $this->resetVisibleWindows();
    }

    public function goToTaskLevel(?int $taskId = null): void
    {
        $this->currentParentTaskId = $taskId && $taskId > 0 ? (int) $taskId : null;
        $this->resetVisibleWindows();
    }

    public function clearTaskFilters(): void
    {
        $this->filterStatus = '';
        $this->filterPriority = '';
        $this->filterDateFrom = '';
        $this->filterDateTo = '';
        $this->resetVisibleWindows();
    }

    public function loadMoreList(): void
    {
        $this->listVisibleLimit += $this->listVisibleStep;
    }

    public function loadMoreBoard(string $status): void
    {
        $current = (int) ($this->boardVisibleByStatus[$status] ?? $this->boardVisibleStep);
        $this->boardVisibleByStatus[$status] = $current + $this->boardVisibleStep;
    }

    public function updatedSearch(): void
    {
        $this->resetVisibleWindows();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetVisibleWindows();
    }

    public function updatedFilterPriority(): void
    {
        $this->resetVisibleWindows();
    }

    public function updatedFilterDateFrom(): void
    {
        $this->resetVisibleWindows();
    }

    public function updatedFilterDateTo(): void
    {
        $this->resetVisibleWindows();
    }

    private function resetVisibleWindows(): void
    {
        $this->listVisibleLimit = max(1, (int) $this->listVisibleStep);
        $this->boardVisibleByStatus = [];
        foreach ((array) $this->taskStatuses as $status) {
            $name = trim((string) $status);
            if ($name === '') {
                continue;
            }
            $this->boardVisibleByStatus[$name] = max(1, (int) $this->boardVisibleStep);
        }
    }

    private function ensureBoardVisibleWindows(array $statuses): void
    {
        $step = max(1, (int) $this->boardVisibleStep);
        foreach ($statuses as $status) {
            $name = trim((string) $status);
            if ($name === '') {
                continue;
            }
            if (! isset($this->boardVisibleByStatus[$name]) || (int) $this->boardVisibleByStatus[$name] <= 0) {
                $this->boardVisibleByStatus[$name] = $step;
            }
        }
    }

    public function openTaskDetail(int $taskId, ?int $scrollToCommentId = null): void
    {
        $task = collect($this->tasks)->first(fn ($t) => (int) ($t['id'] ?? $t['Id'] ?? 0) === $taskId);
        $this->detailTask = $task ?: null;
        $this->showTaskDetailModal = $this->detailTask !== null;
        $this->detailBreadcrumb = $this->detailTask ? $this->buildTaskBreadcrumb($taskId) : [];
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
        $this->detailBreadcrumb = [];
        $this->taskComments = [];
        $this->newComment = '';
        $this->editingCommentId = null;
        $this->editingCommentContent = '';
        $this->commentError = null;
        $this->commentToastMessage = null;
        $this->commentToastType = 'success';
    }

    public function dismissCommentToast(): void
    {
        $this->commentToastMessage = null;
    }

    private function buildTaskBreadcrumb(int $taskId): array
    {
        // Build lookups once from current in-memory tasks.
        $byId = [];
        $parentById = [];
        foreach ($this->tasks as $t) {
            if (! is_array($t)) {
                continue;
            }
            $id = (int) ($t['id'] ?? $t['Id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $byId[$id] = $t;
            $parentById[$id] = $t['parentTaskId'] ?? $t['parentId'] ?? $t['parentID'] ?? null;
        }

        $chain = [];
        $seen = [];
        $cur = $taskId;
        $guard = 0;
        while ($cur > 0 && $guard < 25 && isset($byId[$cur]) && ! isset($seen[$cur])) {
            $seen[$cur] = true;
            $chain[] = $byId[$cur];
            $pidRaw = $parentById[$cur] ?? null;
            $cur = $pidRaw !== null ? (int) $pidRaw : 0;
            $guard++;
        }

        return array_reverse($chain);
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
            $raw = app(CsharpApiService::class)->get(
                "/api/TaskComment/GetCommentsByTask/{$taskId}",
                ['_no_cache' => 1]
            );
            $list = is_array($raw)
                ? ($raw['data'] ?? $raw['comments'] ?? $raw['items'] ?? (isset($raw[0]) ? $raw : []))
                : [];

            $comments = [];
            foreach ((array) $list as $c) {
                if (! is_array($c)) {
                    continue;
                }
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
        $plain = trim((string) preg_replace('/\x{00A0}|&nbsp;/u', ' ', strip_tags($content)));

        if ($taskId <= 0 || $accountId <= 0) {
            $this->commentError = 'Unable to add comment right now.';
            $this->commentToastType = 'error';
            $this->commentToastMessage = $this->commentError;
            $this->dispatch('app-toast', type: 'error', message: $this->commentError, timeout: 2000);
            return;
        }
        if ($content === '' || $plain === '') {
            $this->commentError = 'Comment cannot be empty.';
            $this->commentToastType = 'error';
            $this->commentToastMessage = $this->commentError;
            $this->dispatch('app-toast', type: 'error', message: $this->commentError, timeout: 2000);
            return;
        }

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
            $this->commentToastType = 'success';
            $this->commentToastMessage = 'Comment added successfully.';
            $this->dispatch('clear-rich-editor', field: 'newComment');
            $this->dispatch('app-toast', type: 'success', message: 'Comment added successfully.', timeout: 2000);
        } catch (\Throwable $e) {
            $this->commentError = 'Failed to add comment.';
            $this->commentToastType = 'error';
            $this->commentToastMessage = $this->commentError;
            $this->dispatch('app-toast', type: 'error', message: $this->commentError, timeout: 2000);
        }
    }

    public function startEditComment(int $commentId): void
    {
        $comment = collect($this->taskComments)->first(fn ($c) => (int) ($c['id'] ?? 0) === $commentId);
        if (! $comment) {
            return;
        }
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
        if ($accountId <= 0 || $commentId <= 0 || $taskId <= 0) {
            return;
        }
        if ($content === '') {
            return;
        }

        $this->commentError = null;
        try {
            app(CsharpApiService::class)->patch(
                "/api/TaskComment/UpdateComment/{$commentId}?accountId={$accountId}",
                ['content' => $content]
            );
            $this->editingCommentId = null;
            $this->editingCommentContent = '';
            $this->loadTaskComments($taskId);
            $this->dispatch('app-toast', type: 'success', message: 'Comment updated successfully.', timeout: 2000);
        } catch (\Throwable $e) {
            $this->commentError = 'Failed to update comment.';
            $this->dispatch('app-toast', type: 'error', message: $this->commentError, timeout: 2000);
        }
    }

    public function deleteComment(int $commentId): void
    {
        $accountId = $this->currentAccountId();
        $taskId = (int) ($this->detailTask['id'] ?? $this->detailTask['Id'] ?? 0);
        if ($accountId <= 0 || $commentId <= 0 || $taskId <= 0) {
            return;
        }

        $this->commentError = null;
        try {
            app(CsharpApiService::class)->delete("/api/TaskComment/DeleteComment/{$commentId}?accountId={$accountId}");
            if ($this->editingCommentId === $commentId) {
                $this->editingCommentId = null;
                $this->editingCommentContent = '';
            }
            $this->loadTaskComments($taskId);
            $this->dispatch('app-toast', type: 'success', message: 'Comment deleted successfully.', timeout: 2000);
        } catch (\Throwable $e) {
            $this->commentError = 'Failed to delete comment.';
            $this->dispatch('app-toast', type: 'error', message: $this->commentError, timeout: 2000);
        }
    }

    #[On('task-status-changed')]
    public function moveTask(int $taskId, string $newStatus): void
    {
        $statusId = $this->statusMap[$newStatus] ?? null;
        if ($statusId === null) {
            $this->moveError = "Cannot update: no statusId found for \"{$newStatus}\". Map: ".json_encode($this->statusMap);

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
        while (! empty($queue)) {
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
                if (empty($directChildren)) {
                    break;
                }

                $allChildrenDone = true;
                foreach ($directChildren as $childId) {
                    if (($currentStatus[$childId] ?? '') !== 'completed') {
                        $allChildrenDone = false;
                        break;
                    }
                }
                if (! $allChildrenDone) {
                    break;
                }

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
                $errors[] = "Task #{$id}: ".$e->getMessage();
            }
        }
        if (! empty($errors)) {
            $this->moveError = 'Some updates failed: '.implode(' | ', $errors);

            return;
        }

        $this->syncProjectStatusFromTasks();
    }

    private function computeProjectStatusFromTasks(): string
    {
        if (empty($this->tasks)) {
            return 'Not Started';
        }

        // Leaf-only derivation (matches "progress" intuition).
        $childrenMap = [];
        foreach ($this->tasks as $t) {
            $tid = (int) ($t['id'] ?? $t['Id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $pid = (int) ($t['parentTaskId'] ?? $t['parentId'] ?? $t['parentID'] ?? 0);
            if ($pid > 0) {
                $childrenMap[$pid][] = $tid;
            }
        }

        $leafTasks = [];
        foreach ($this->tasks as $t) {
            $tid = (int) ($t['id'] ?? $t['Id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            if (empty($childrenMap[$tid])) {
                $leafTasks[] = $t;
            }
        }
        if (empty($leafTasks)) {
            $leafTasks = $this->tasks;
        }

        $allCompleted = true;
        $allNotStarted = true;
        foreach ($leafTasks as $t) {
            $raw = (string) ($t['statusName'] ?? $t['status'] ?? 'Not Started');
            $s = mb_strtolower(trim($raw));
            if ($s === 'notstarted') {
                $s = 'not started';
            }
            if ($s === '') {
                $s = 'not started';
            }

            if ($s !== 'completed') {
                $allCompleted = false;
            }
            if ($s !== 'not started') {
                $allNotStarted = false;
            }
        }

        if ($allCompleted) {
            return 'Completed';
        }
        if ($allNotStarted) {
            return 'Not Started';
        }

        return 'Active';
    }

    private function syncProjectStatusFromTasks(): void
    {
        $projectId = (int) ($this->projectId ?? 0);
        if ($projectId <= 0) {
            return;
        }

        $user = Session::get('user', []);
        $requesterId = (int) ($user['id'] ?? $user['Id'] ?? 0);
        if ($requesterId <= 0) {
            return;
        }

        $derived = $this->computeProjectStatusFromTasks();

        try {
            $project = app(ProjectController::class)->getProjectData($projectId);
            $current = (string) ($project['statusName'] ?? $project['status'] ?? '');
            if (mb_strtolower(trim($current)) === mb_strtolower($derived)) {
                return;
            }
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
        $this->expanded[$taskId] = ! ($this->expanded[$taskId] ?? false);
    }

    public function openAddTaskModal(): void
    {
        // At root, add a parent task. Inside a parent, add a child in current context.
        $this->taskParentId = $this->currentParentTaskId;
        $this->description = '';
        $this->taskPriorities = app(TaskController::class)->getPriorities();
        $this->showAddTaskModal = true;
    }

    public function closeAddTaskModal(): void
    {
        $this->showAddTaskModal = false;
        $this->taskParentId = null;
        $this->description = '';
        $this->taskWarnings = [];
        $this->dismissFlashErrors();
    }

    public function addSubtask(int $parentTaskId): void
    {
        $this->taskParentId = $parentTaskId;
        $this->description = '';
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
        $statusNeedle = mb_strtolower(trim($this->filterStatus));
        $priorityNeedle = mb_strtolower(trim($this->filterPriority));
        $fromDate = trim($this->filterDateFrom);
        $toDate = trim($this->filterDateTo);

        $matchingIds = [];
        foreach ($this->tasks as $task) {
            $id = $task['id'] ?? null;
            if ($id === null) {
                continue;
            }

            $statusRaw = (string) ($task['statusName'] ?? $task['status'] ?? '');
            $status = mb_strtolower(trim($statusRaw));
            if ($statusNeedle !== '' && $status !== $statusNeedle) {
                continue;
            }

            $priorityRaw = (string) ($task['priorityName'] ?? $task['priority'] ?? '');
            if ($priorityRaw === '' && isset($task['priorityId'])) {
                $priorityRaw = (string) (($this->taskPriorities['map'] ?? [])[(int) ($task['priorityId'] ?? 0)] ?? '');
            }
            $priority = mb_strtolower(trim($priorityRaw));
            if ($priorityNeedle !== '' && $priority !== $priorityNeedle) {
                continue;
            }

            $dateRaw = (string) ($task['dueDate'] ?? $task['dueAt'] ?? '');
            if (($fromDate !== '' || $toDate !== '')) {
                if ($dateRaw === '') {
                    continue;
                }
                $dateTs = strtotime($dateRaw);
                if ($dateTs === false) {
                    continue;
                }
                if ($fromDate !== '') {
                    $fromTs = strtotime($fromDate.' 00:00:00');
                    if ($fromTs !== false && $dateTs < $fromTs) {
                        continue;
                    }
                }
                if ($toDate !== '') {
                    $toTs = strtotime($toDate.' 23:59:59');
                    if ($toTs !== false && $dateTs > $toTs) {
                        continue;
                    }
                }
            }

            if ($query !== '') {
                $haystack = implode(' ', [
                    mb_strtolower((string) ($task['name'] ?? $task['title'] ?? '')),
                    mb_strtolower((string) ($task['assigneeName'] ?? $task['assignedToName'] ?? '')),
                    mb_strtolower((string) ($task['statusName'] ?? $task['status'] ?? '')),
                    mb_strtolower((string) ($task['priorityName'] ?? $task['priority'] ?? '')),
                ]);
                if (! str_contains($haystack, $query)) {
                    continue;
                }
            }

            $matchingIds[$id] = true;
        }

        $parentMap = [];
        foreach ($this->tasks as $task) {
            $id = $task['id'] ?? null;
            $pid = $task['parentTaskId'] ?? $task['parentId'] ?? $task['parentID'] ?? null;
            if ($id !== null) {
                $parentMap[$id] = $pid;
            }
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

        $parentOf = [];
        $childrenMap = [];
        $tasksById = [];
        foreach ($this->tasks as $task) {
            if (! is_array($task)) {
                continue;
            }
            $id = (int) ($task['id'] ?? $task['Id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $tasksById[$id] = $task;
            $pidRaw = $task['parentTaskId'] ?? $task['parentId'] ?? $task['parentID'] ?? null;
            $pid = $pidRaw !== null && $pidRaw !== '' ? (int) $pidRaw : null;
            $parentOf[$id] = $pid;
            if ($pid !== null && $pid > 0) {
                $childrenMap[$pid][] = $id;
            }
        }

        $rootOnly = [];
        $currentOnly = [];
        foreach ($filtered as $task) {
            $id = (int) ($task['id'] ?? $task['Id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $pid = $parentOf[$id] ?? null;
            if ($pid === null || $pid <= 0) {
                $rootOnly[] = $task;
            }
            if ($this->currentParentTaskId === null) {
                if ($pid === null || $pid <= 0) {
                    $currentOnly[] = $task;
                }
            } elseif ((int) ($pid ?? 0) === (int) $this->currentParentTaskId) {
                $currentOnly[] = $task;
            }
        }

        $listRows = [];
        $appendRow = function (array $task, int $depth) use (&$appendRow, &$listRows, $tasksById, $childrenMap) {
            $id = (int) ($task['id'] ?? $task['Id'] ?? 0);
            if ($id <= 0) {
                return;
            }
            $childIds = array_values(array_filter(array_map('intval', (array) ($childrenMap[$id] ?? [])), static fn ($v) => $v > 0));
            // Inside-parent tree: child and grandchild rows are toggleable.
            // Great-grandchild rows are terminal.
            $canExpandInLevel = $depth < 2;
            $listRows[] = [
                'type' => 'task',
                'task' => $task,
                'id' => $id,
                'depth' => max(0, $depth),
                'canToggle' => $canExpandInLevel,
                'hasChildren' => ! empty($childIds),
            ];

            if (! $canExpandInLevel || ! ($this->expanded[$id] ?? false)) {
                return;
            }

            foreach ($childIds as $childId) {
                if (! isset($tasksById[$childId]) || ! is_array($tasksById[$childId])) {
                    continue;
                }
                $appendRow($tasksById[$childId], $depth + 1);
            }

            $addLabel = $depth === 0 ? 'Add grandchild task' : 'Add great grandchild task';
            $listRows[] = [
                'type' => 'add',
                'parentId' => $id,
                'depth' => max(0, $depth + 1),
                'label' => $addLabel,
            ];
        };

        foreach ($currentOnly as $task) {
            if (! is_array($task)) {
                continue;
            }
            $appendRow($task, 0);
        }

        $currentBreadcrumb = [];
        if ($this->currentParentTaskId !== null && $this->currentParentTaskId > 0) {
            $chain = $this->buildTaskBreadcrumb((int) $this->currentParentTaskId);
            foreach ($chain as $item) {
                $currentBreadcrumb[] = [
                    'id' => (int) ($item['id'] ?? $item['Id'] ?? 0),
                    'name' => (string) ($item['name'] ?? $item['title'] ?? 'Task'),
                ];
            }
        }

        $statuses = ! empty($this->taskStatuses) ? $this->taskStatuses : ['Not Started', 'In Progress', 'For Review', 'Completed'];
        $this->ensureBoardVisibleWindows($statuses);

        $grouped = array_fill_keys($statuses, []);
        foreach ($currentOnly as $task) {
            $s = $task['statusName'] ?? $task['status'] ?? 'Not Started';
            if (! array_key_exists($s, $grouped)) {
                $grouped[$s] = [];
            }
            $grouped[$s][] = $task;
        }
        $visibleListRows = array_slice($listRows, 0, max(1, (int) $this->listVisibleLimit));
        $visibleFilteredTasks = array_slice($currentOnly, 0, max(1, (int) $this->listVisibleLimit));
        $boardGroupedVisible = [];
        $boardHasMoreByStatus = [];
        foreach ($statuses as $status) {
            $all = (array) ($grouped[$status] ?? []);
            $limit = max(1, (int) ($this->boardVisibleByStatus[$status] ?? $this->boardVisibleStep));
            $boardGroupedVisible[$status] = array_slice($all, 0, $limit);
            $boardHasMoreByStatus[$status] = count($all) > count($boardGroupedVisible[$status]);
        }

        $accountMap = [];
        $accountProfiles = [];
        foreach ($this->accounts as $account) {
            $id = $account['id'] ?? $account['Id'] ?? null;
            $name = $account['name']
                ?? $account['fullName']
                ?? $account['username']
                ?? trim(($account['firstName'] ?? '').' '.($account['lastName'] ?? ''))
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
            $last = (string) (! empty($parts) ? implode(' ', array_slice($parts, 1)) : '');
            $a0 = mb_substr(trim($first), 0, 1);
            $b0 = mb_substr(trim($last), 0, 1);
            if ($a0 !== '' && $b0 !== '') {
                $initials = mb_strtoupper($a0.$b0);
            } elseif ($a0 !== '') {
                $initials = mb_strtoupper($a0);
            } else {
                $initials = '?';
            }

            $accountProfiles[$accountId] = [
                'profilePicture' => $profilePicture,
                'initials' => $initials,
                'name' => $name,
                'email' => (string) ($account['email'] ?? $account['Email'] ?? ''),
                'specialization' => AccountPresentation::displaySpecialization($account),
                'role' => (string) ($account['role'] ?? $account['Role'] ?? ''),
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
            if ($pid !== null) {
                $taskPriorityMap[(int) $pid] = $pname;
            }
        }

        $currentUserName = $user['name'] ?? $user['Name'] ?? $user['fullName'] ?? trim(($user['firstName'] ?? '').' '.($user['lastName'] ?? '')) ?: null;
        $currentUserId = (int) ($user['id'] ?? $user['Id'] ?? 0);

        return view('livewire.tasks', [
            'filteredTasks' => $currentOnly,
            'listRows' => $listRows,
            'visibleListRows' => $visibleListRows,
            'rootTasks' => $rootOnly,
            'visibleFilteredTasks' => $visibleFilteredTasks,
            'boardStatuses' => $statuses,
            'boardGrouped' => $grouped,
            'boardGroupedVisible' => $boardGroupedVisible,
            'boardHasMoreByStatus' => $boardHasMoreByStatus,
            'childrenMap' => $childrenMap,
            'taskParentMap' => $parentOf,
            'tasksById' => $tasksById,
            'currentBreadcrumb' => $currentBreadcrumb,
            'currentParentTaskId' => $this->currentParentTaskId,
            'accountMap' => $accountMap,
            'accountProfiles' => $accountProfiles,
            'assignableAccounts' => $assignableAccounts,
            'taskPriorities' => $this->taskPriorities['items'] ?? [],
            'taskPriorityNames' => $this->taskPriorities['names'] ?? [],
            'taskPriorityMap' => $taskPriorityMap,
            'currentUserName' => $currentUserName,
            'currentUserId' => $currentUserId,
            'canDeleteTasks' => $this->canDeleteTasks(),
        ]);
    }
}
