<?php

namespace App\Livewire;

use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class DashboardTaskCreate extends Component
{
    /** Projects list coming from dashboard left-side API. */
    public array $projects = [];

    public bool $showSelectProjectModal = false;
    public bool $showAddTaskModal = false;

    public int $currentUserId = 0;
    public ?int $selectedProjectId = null;

    /** PriorityId => PriorityName */
    public array $taskPriorityMap = [];

    /** Accounts assignable to selected project */
    public array $assignableAccounts = [];

    protected $listeners = [
        'open-dashboard-task-create' => 'open',
    ];

    public function mount(array $projects = []): void
    {
        $this->projects = $projects;
        $user = Session::get('user', []);
        $this->currentUserId = (int) ($user['id'] ?? ($user['Id'] ?? 0));

        $this->loadPriorities();
    }

    public function open(): void
    {
        $this->resetWizard();
        $this->showSelectProjectModal = true;
    }

    public function closeAll(): void
    {
        $this->showSelectProjectModal = false;
        $this->showAddTaskModal = false;
    }

    public function resetWizard(): void
    {
        $this->showSelectProjectModal = false;
        $this->showAddTaskModal = false;
        $this->selectedProjectId = null;
        $this->assignableAccounts = [];
    }

    public function loadPriorities(): void
    {
        $priorities = app(TaskController::class)->getPriorities();
        $map = $priorities['map'] ?? [];

        // TaskController returns name=>id; flip to id=>name for selects.
        $out = [];
        foreach ((array) $map as $name => $id) {
            $out[(int) $id] = (string) $name;
        }
        ksort($out);
        $this->taskPriorityMap = $out;
    }

    public function chooseProject(int $projectId): void
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) return;

        $this->selectedProjectId = $projectId;
        $this->assignableAccounts = app(TaskController::class)->getAssignableAccountsForProject(
            $projectId,
            $this->currentUserId
        );

        $this->showSelectProjectModal = false;
        $this->showAddTaskModal = true;
    }

    public function backToProjectSelect(): void
    {
        $this->showAddTaskModal = false;
        $this->showSelectProjectModal = true;
    }

    public function render()
    {
        return view('livewire.dashboard-task-create');
    }
}

