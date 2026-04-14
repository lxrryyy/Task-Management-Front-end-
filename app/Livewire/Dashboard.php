<?php

namespace App\Livewire;

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;
use Livewire\Component;

class Dashboard extends Component
{
    public ?array $user = null;

    public bool $isAdmin = false;

    public array $projects = [];

    public int $currentUserId = 0;

    /** Selected project id for the "Task by Status" chart; default is first project. */
    public ?int $selectedProjectId = null;

    /** Task status summary from API: totalTasks, breakdown (statusName, count, percentage). */
    public array $taskStatusSummary = ['totalTasks' => 0, 'breakdown' => []];

    public bool $loading = true;

    public array $summaryCards = [
        'projects' => 0,
        'tasks' => 0,
        'forReview' => 0,
        'completed' => 0,
    ];

    public array $adminSummaryCards = [];

    public function mount(): void
    {
        $user = Session::get('user', null);
        $this->user = $user;
        $role = mb_strtolower(trim((string) ($user['role'] ?? $user['Role'] ?? $user['roleName'] ?? $user['RoleName'] ?? '')));
        $this->isAdmin = $role === 'admin';

        $accountId = (int) ($user['id'] ?? ($user['Id'] ?? 0));
        $this->currentUserId = $accountId;

        $this->loading = true;
        $this->dispatch('load-dashboard');
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

        $this->setDefaultSelectedProject();
        $this->loadTaskStatusSummary();
        $this->loading = false;
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
        $this->summaryCards = $this->buildSummaryCards($this->projects);

        return view('livewire.dashboard', [
            'projects' => $this->projects,
            'taskStatusSummary' => $this->taskStatusSummary,
            'summaryCards' => $this->summaryCards,
            'isAdmin' => $this->isAdmin,
            'adminSummaryCards' => $this->adminSummaryCards,
        ]);
    }
}
