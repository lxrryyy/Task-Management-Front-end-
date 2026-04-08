<?php

namespace App\Livewire;

use Livewire\Component;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Session;

class Dashboard extends Component
{
    public ?array $user = null;
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

    public function mount(): void
    {
        $user = Session::get('user', null);
        $this->user = $user;

        $accountId = (int) ($user['id'] ?? ($user['Id'] ?? 0));
        $this->currentUserId = $accountId;

        $this->loading = true;
        $this->dispatch('load-dashboard');
    }

    #[\Livewire\Attributes\On('load-dashboard')]
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
            if (!is_array($project)) {
                continue;
            }

            $tasks = $project['tasks'] ?? $project['Tasks'] ?? [];
            if (!is_array($tasks)) {
                continue;
            }

            foreach ($tasks as $task) {
                if (!is_array($task)) {
                    continue;
                }

                $tasksCount++;
                $status = mb_strtolower(trim((string) ($task['statusName'] ?? $task['StatusName'] ?? $task['status'] ?? $task['Status'] ?? '')));

                if ($status === 'for review') {
                    $forReviewCount++;
                } elseif ($status === 'completed') {
                    $completedCount++;
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
        ]);
    }
}

