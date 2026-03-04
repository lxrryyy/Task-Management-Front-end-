<?php

use Livewire\Component;

new class extends Component
{
    public ?int $projectId = null;
    public array $tasks = [];

    // track which tasks' children are expanded (id => bool)
    public array $expanded = [];

    public function mount(?int $projectId = null, array $tasks = [])
    {
        $this->projectId = $projectId;
        $this->tasks = $tasks;
    }

    public function toggle(int $taskId): void
    {
        $this->expanded[$taskId] = !($this->expanded[$taskId] ?? false);
    }

    public function render()
    {
        return view('livewire.tasks');
    }
};
?>
