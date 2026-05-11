<?php

namespace App\Livewire;

use App\Http\Controllers\DashboardController;
use App\Services\CsharpApiService;
use App\Services\StickyNoteApiService;
use App\Support\AccountPresentation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

class Dashboard extends Component
{
    private const NOTEPAD_MARKER = '__PRIVATE_NOTEPAD__::';
    public ?array $user = null;

    public bool $isAdmin = false;

    public array $projects = [];

    public int $currentUserId = 0;

    /** Selected project id for the "Task by Status" chart; default is first project. */
    public ?int $selectedProjectId = null;

    /** Task status summary from API: totalTasks, breakdown (statusName, count, percentage). */
    public array $taskStatusSummary = ['totalTasks' => 0, 'breakdown' => []];

    public bool $loading = true;
    public bool $secondaryReady = false;

    public array $summaryCards = [
        'projects' => 0,
        'tasks' => 0,
        'forReview' => 0,
        'completed' => 0,
    ];

    public array $kpiCards = [
        'totalProjects' => 0,
        'totalTasks' => 0,
        'assignedTasks' => 0,
        'completedTasks' => 0,
        'overdueTasks' => 0,
    ];

    public array $assignedTaskList = [];
    public array $projectOverviewList = [];
    public array $peopleList = [];
    public string $notepadContent = '';
    public ?int $notepadNoteId = null;

    public array $adminSummaryCards = [];

    public ?string $flashSuccess = null;

    public function mount(): void
    {
        $user = Session::get('user', null);
        $this->user = $user;
        $role = mb_strtolower(trim((string) ($user['role'] ?? $user['Role'] ?? $user['roleName'] ?? $user['RoleName'] ?? '')));
        $this->isAdmin = $role === 'admin';

        $accountId = (int) ($user['id'] ?? ($user['Id'] ?? 0));
        $this->currentUserId = $accountId;

        $this->loading = true;
        $this->secondaryReady = false;
        $this->flashSuccess = session()->get('success') ?: null;
        $this->loadPrivateNotepad();
        $this->dispatch('load-dashboard');
    }

    public function dismissFlashSuccess(): void
    {
        $this->flashSuccess = null;
    }

    public function savePrivateNotepad(string $content): void
    {
        if ($this->currentUserId <= 0) {
            return;
        }

        $payloadContent = self::NOTEPAD_MARKER . $content;

        try {
            $notesApi = app(StickyNoteApiService::class);

            if ($this->notepadNoteId) {
                $notesApi->update($this->notepadNoteId, [
                    'content' => $payloadContent,
                    'isPinned' => true,
                ]);
            } else {
                $created = $notesApi->create($payloadContent, true);
                $newId = (int) ($created['id'] ?? $created['Id'] ?? 0);
                if ($newId > 0) {
                    $this->notepadNoteId = $newId;
                } else {
                    $this->loadPrivateNotepad();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Dashboard private notepad save failed', [
                'accountId' => $this->currentUserId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    #[On('load-dashboard')]
    public function loadDashboard(): void
    {
        if ($this->currentUserId <= 0) {
            $this->projects = [];
            $this->summaryCards = [
                'projects' => 0,
                'tasks' => 0,
                'forReview' => 0,
                'completed' => 0,
            ];
            $this->loading = false;

            return;
        }

        /** @var DashboardController $controller */
        $controller = app(DashboardController::class);
        $data = $controller->getMyProjectsAndTasks($this->currentUserId);
        $this->projects = is_array($data) ? $data : [];
        $this->summaryCards = $this->buildSummaryCards($this->projects);
        $this->buildDashboardCollections();

        $this->setDefaultSelectedProject();

        // End the primary loading state as soon as the main projects list is ready.
        // Secondary widgets (admin cards + charts) can be fetched in a follow-up request.
        $this->loading = false;

        $this->dispatch('load-dashboard-secondary');
    }

    #[On('load-dashboard-secondary')]
    public function loadDashboardSecondary(): void
    {
        if ($this->currentUserId <= 0) {
            $this->adminSummaryCards = [];
            $this->taskStatusSummary = ['totalTasks' => 0, 'breakdown' => []];
            $this->secondaryReady = true;
            return;
        }

        /** @var DashboardController $controller */
        $controller = app(DashboardController::class);

        if ($this->isAdmin) {
            $stats = $controller->getDashboardAdminStats($this->currentUserId);
            $this->adminSummaryCards = [
                [
                    'label' => 'Total Users',
                    'value' => (int) ($stats['totalUsers'] ?? $stats['TotalUsers'] ?? 0),
                    'sub' => 'Active',
                    'icon' => 'icons.users',
                    'iconWrap' => 'bg-blue-50 text-blue-700',
                ],
                [
                    'label' => 'Total Projects',
                    'value' => (int) ($stats['totalProjects'] ?? $stats['TotalProjects'] ?? 0),
                    'sub' => 'Active',
                    'icon' => 'icons.total-projects',
                ],
                [
                    'label' => 'Overdue Tasks',
                    'value' => (int) ($stats['overdueTasks'] ?? $stats['OverdueTasks'] ?? 0),
                    'sub' => 'Past due date',
                    'icon' => 'icons.overdue',
                ],
                [
                    'label' => 'Deactivated Users',
                    'value' => (int) ($stats['deactivatedUsers'] ?? $stats['DeactivatedUsers'] ?? $stats['completedProjects'] ?? $stats['CompletedProjects'] ?? 0),
                    'sub' => 'Inactive',
                    'icon' => 'icons.deactivated',
                ],
            ];
        }

        // Keep selected project stable; only set a default if not already chosen.
        if ($this->selectedProjectId === null) {
            $this->setDefaultSelectedProject();
        }
        $this->loadTaskStatusSummary();
        $this->secondaryReady = true;
    }

    private function buildSummaryCards(array $projects): array
    {
        $projectsCount = count($projects);
        $tasksCount = 0;
        $forReviewCount = 0;
        $completedCount = 0;

        foreach ($projects as $project) {
            $tasks = $this->readField($project, ['tasks', 'Tasks']);

            if (is_iterable($tasks)) {
                foreach ($tasks as $task) {
                    $tasksCount++;
                    $status = mb_strtolower(trim((string) $this->readField($task, ['statusName', 'StatusName', 'status', 'Status'], '')));
                    if ($status === 'for review') {
                        $forReviewCount++;
                    } elseif ($status === 'completed') {
                        $completedCount++;
                    }
                }

                continue;
            }

            $tasksCount += (int) $this->readField($project, ['taskCount', 'TaskCount', 'tasksCount', 'TasksCount', 'totalTasks', 'TotalTasks'], 0);
            $forReviewCount += (int) $this->readField($project, ['forReviewCount', 'ForReviewCount'], 0);
            $completedCount += (int) $this->readField($project, ['completedCount', 'CompletedCount'], 0);
        }

        if ($tasksCount === 0) {
            $breakdown = (array) ($this->taskStatusSummary['breakdown'] ?? []);
            foreach ($breakdown as $row) {
                $count = (int) $this->readField($row, ['count', 'Count'], 0);
                $tasksCount += $count;
                $status = mb_strtolower(trim((string) $this->readField($row, ['statusName', 'StatusName', 'status', 'Status'], '')));
                if ($status === 'for review') {
                    $forReviewCount += $count;
                } elseif ($status === 'completed') {
                    $completedCount += $count;
                }
            }
        }

        return [
            'projects' => $projectsCount,
            'tasks' => $tasksCount,
            'forReview' => $forReviewCount,
            'completed' => $completedCount,
        ];
    }

    private function readField(mixed $source, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (is_array($source) && array_key_exists($key, $source)) {
                return $source[$key];
            }
            if (is_object($source) && isset($source->{$key})) {
                return $source->{$key};
            }
        }

        return $default;
    }

    private function buildDashboardCollections(): void
    {
        $allTasks = [];
        foreach ($this->projects as $project) {
            $projectId = (int) ($this->readField($project, ['id', 'Id'], 0));
            $projectName = (string) $this->readField($project, ['name', 'Name', 'title'], 'Project');
            $tasks = $this->readField($project, ['tasks', 'Tasks'], []);
            if (! is_iterable($tasks)) {
                continue;
            }
            foreach ($tasks as $task) {
                $allTasks[] = [
                    'projectId' => $projectId,
                    'projectName' => $projectName,
                    'taskId' => (int) $this->readField($task, ['id', 'Id'], 0),
                    'name' => (string) $this->readField($task, ['title', 'name', 'Name'], 'Task'),
                    'status' => (string) $this->readField($task, ['statusName', 'StatusName', 'status', 'Status'], ''),
                    'dueRaw' => $this->readField($task, ['dueDate', 'DueDate', 'dueAt', 'DueAt']),
                    'assigneeIds' => $this->normalizeAssigneeIds($this->readField($task, ['assigneeIds', 'AssigneeIds', 'assigneeId', 'AssigneeId'], [])),
                ];
            }
        }

        $now = now();
        $isAssignedToCurrent = function (array $task): bool {
            if ($this->isAdmin) {
                return true;
            }
            return in_array($this->currentUserId, $task['assigneeIds'], true);
        };

        $assignedTasks = array_values(array_filter($allTasks, $isAssignedToCurrent));
        $completedTasks = array_values(array_filter($allTasks, function (array $task): bool {
            return mb_strtolower(trim($task['status'])) === 'completed';
        }));
        $overdueTasks = array_values(array_filter($allTasks, function (array $task) use ($now): bool {
            $status = mb_strtolower(trim($task['status']));
            if ($status === 'completed') return false;
            if (empty($task['dueRaw'])) return false;
            try {
                return Carbon::parse((string) $task['dueRaw'])->lt($now);
            } catch (\Throwable) {
                return false;
            }
        }));

        $this->kpiCards = [
            'totalProjects' => count($this->projects),
            'totalTasks' => count($allTasks),
            'assignedTasks' => count($assignedTasks),
            'completedTasks' => count($completedTasks),
            'overdueTasks' => count($overdueTasks),
        ];

        usort($assignedTasks, function (array $a, array $b): int {
            $ad = $this->safeTimestamp($a['dueRaw']);
            $bd = $this->safeTimestamp($b['dueRaw']);
            return $ad <=> $bd;
        });
        $this->assignedTaskList = array_slice(array_map(function (array $task) use ($now): array {
            $dueLabel = 'No due date';
            if (! empty($task['dueRaw'])) {
                try {
                    $due = Carbon::parse((string) $task['dueRaw']);
                    $dueLabel = $due->isPast() ? 'Overdue' : 'Due in ' . $now->diffForHumans($due, ['parts' => 2, 'short' => true, 'syntax' => Carbon::DIFF_RELATIVE_TO_NOW]);
                } catch (\Throwable) {
                    $dueLabel = 'No due date';
                }
            }
            return [
                'name' => $task['name'],
                'projectName' => $task['projectName'],
                'projectId' => $task['projectId'],
                'dueLabel' => $dueLabel,
            ];
        }, $assignedTasks), 0, 5);

        $this->projectOverviewList = array_slice(array_map(function (array $project): array {
            $name = (string) $this->readField($project, ['name', 'Name', 'title'], 'Project');
            $pid = (int) $this->readField($project, ['id', 'Id'], 0);
            $tasks = $this->readField($project, ['tasks', 'Tasks'], []);
            $dueSoon = 0;
            if (is_iterable($tasks)) {
                foreach ($tasks as $task) {
                    $status = mb_strtolower(trim((string) $this->readField($task, ['statusName', 'StatusName', 'status', 'Status'], '')));
                    if ($status === 'completed') continue;
                    $dueRaw = $this->readField($task, ['dueDate', 'DueDate', 'dueAt', 'DueAt']);
                    if (! $dueRaw) continue;
                    try {
                        $due = Carbon::parse((string) $dueRaw);
                        if ($due->lte(now()->copy()->addDays(7))) {
                            $dueSoon++;
                        }
                    } catch (\Throwable) {
                    }
                }
            }
            return [
                'id' => $pid,
                'name' => $name,
                'dueSoon' => $dueSoon,
            ];
        }, $this->projects), 0, 6);

        $people = $this->loadPeopleFromAccountsApi();

        if (empty($people) && ! empty($this->user)) {
            $uid = (int) ($this->user['id'] ?? $this->user['Id'] ?? 0);
            if ($uid > 0) {
                $people[$uid] = [
                    'id' => $uid,
                    'name' => (string) ($this->user['name'] ?? $this->user['Name'] ?? 'You'),
                    'email' => (string) ($this->user['email'] ?? $this->user['Email'] ?? ''),
                ];
            }
        }
        $this->peopleList = array_slice(array_values($people), 0, 8);
    }

    private function loadPeopleFromAccountsApi(): array
    {
        $out = [];
        try {
            $list = app(\App\Services\AccountApiService::class)->listAssignableUsers();

            foreach ($list as $acc) {
                if (! is_array($acc)) {
                    continue;
                }
                $id = (int) ($acc['id'] ?? $acc['Id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $name = (string) ($acc['name'] ?? $acc['Name'] ?? 'User');
                $email = (string) ($acc['email'] ?? $acc['Email'] ?? '');
                $profilePicture = AccountPresentation::profilePictureDisplayUrl(
                    $acc['profilePicture'] ?? $acc['ProfilePicture'] ?? null
                ) ?? '';
                $specialization = (string) ($acc['specialization'] ?? $acc['Specialization'] ?? '');
                $role = (string) ($acc['role'] ?? $acc['Role'] ?? $acc['roleName'] ?? $acc['RoleName'] ?? '');

                $out[$id] = [
                    'id' => $id,
                    'name' => $name,
                    'email' => $email,
                    'profilePicture' => $profilePicture ?: null,
                    'specialization' => $specialization,
                    'role' => $role,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Dashboard GetAllUserRoleAccount failed', ['message' => $e->getMessage()]);
        }

        return $out;
    }

    private function loadPrivateNotepad(): void
    {
        if ($this->currentUserId <= 0) {
            $this->notepadContent = '';
            $this->notepadNoteId = null;
            return;
        }

        try {
            $list = app(StickyNoteApiService::class)->list();

            foreach ($list as $note) {
                $content = (string) ($note['content'] ?? $note['Content'] ?? '');
                if (! str_starts_with($content, self::NOTEPAD_MARKER)) {
                    continue;
                }
                $this->notepadNoteId = (int) ($note['id'] ?? $note['Id'] ?? 0);
                $this->notepadContent = mb_substr($content, mb_strlen(self::NOTEPAD_MARKER));
                return;
            }

            $this->notepadNoteId = null;
            $this->notepadContent = '';
        } catch (\Throwable $e) {
            Log::warning('Dashboard private notepad load failed', [
                'accountId' => $this->currentUserId,
                'message' => $e->getMessage(),
            ]);
            $this->notepadNoteId = null;
            $this->notepadContent = '';
        }
    }

    private function normalizeAssigneeIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw), static fn (int $id) => $id > 0));
        }
        if (is_numeric($raw)) {
            $id = (int) $raw;
            return $id > 0 ? [$id] : [];
        }
        if (is_string($raw) && trim($raw) !== '') {
            return array_values(array_filter(array_map('intval', explode(',', $raw)), static fn (int $id) => $id > 0));
        }
        return [];
    }

    private function safeTimestamp(mixed $v): int
    {
        if (! $v) return PHP_INT_MAX;
        try {
            return Carbon::parse((string) $v)->getTimestamp();
        } catch (\Throwable) {
            return PHP_INT_MAX;
        }
    }

    /** Set selected project to the first project from the list. */
    protected function setDefaultSelectedProject(): void
    {
        if (empty($this->projects)) {
            $this->selectedProjectId = null;

            return;
        }
        $first = $this->projects[0];
        $id = $first['id'] ?? $first['Id'] ?? null;
        $this->selectedProjectId = $id !== null ? (int) $id : null;
    }

    /** Load pie chart data from ProjectTaskSummary API for the selected project. */
    public function loadTaskStatusSummary(): void
    {
        if ($this->currentUserId <= 0 || $this->selectedProjectId === null || $this->selectedProjectId <= 0) {
            $this->taskStatusSummary = ['totalTasks' => 0, 'breakdown' => []];

            return;
        }
        $controller = app(DashboardController::class);
        $this->taskStatusSummary = $controller->getProjectTaskSummary(
            $this->selectedProjectId,
            $this->currentUserId
        );
    }

    /** Called when user changes the project dropdown. */
    public function updatedSelectedProjectId(): void
    {
        $this->loadTaskStatusSummary();
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'projects' => $this->projects,
            'taskStatusSummary' => $this->taskStatusSummary,
            'summaryCards' => $this->summaryCards,
            'kpiCards' => $this->kpiCards,
            'assignedTaskList' => $this->assignedTaskList,
            'projectOverviewList' => $this->projectOverviewList,
            'peopleList' => $this->peopleList,
            'isAdmin' => $this->isAdmin,
            'adminSummaryCards' => $this->adminSummaryCards,
            'secondaryReady' => $this->secondaryReady,
        ]);
    }
}
