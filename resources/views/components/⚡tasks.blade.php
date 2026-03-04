<?php

use Livewire\Component;

new class extends Component
{
    public ?int $projectId = null;
    public array $tasks    = [];
    public array $accounts = [];

    // track which tasks' children are expanded (id => bool)
    public array $expanded = [];

    // Add Task modal state
    public bool $showAddTaskModal = false;
    public ?int $taskParentId     = null;

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
    }

    public function toggle(int $taskId): void
    {
        $this->expanded[$taskId] = !($this->expanded[$taskId] ?? false);
    }

    public function openAddTaskModal(): void
    {
        $this->taskParentId     = null;
        $this->showAddTaskModal = true;
    }

    public function closeAddTaskModal(): void
    {
        $this->showAddTaskModal = false;
        $this->taskParentId     = null;
    }

    public function addSubtask(int $parentTaskId): void
    {
        $this->taskParentId     = $parentTaskId;
        $this->showAddTaskModal = true;
    }

    public function render()
    {
        return view('livewire.tasks');
    }
};
?>
