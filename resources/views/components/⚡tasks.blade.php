<?php

use Livewire\Component;

new class extends Component
{
    public ?int $projectId = null;
    public array $tasks    = [];
    public array $accounts = [];

    public string $search = '';

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
                    mb_strtolower($task['status']           ?? ''),
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

        return view('livewire.tasks', ['filteredTasks' => $filtered]);
    }
};
?>
