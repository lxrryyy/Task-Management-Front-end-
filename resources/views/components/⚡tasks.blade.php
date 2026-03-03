<?php

use Livewire\Component;

new class extends Component
{
    public bool $showSubtasks = false;
    public bool $showGrandchildren = false;

    public function toggleSubtasks()
    {
        $this->showSubtasks = ! $this->showSubtasks;

        // If we hide subtasks, also hide grandchildren
        if (! $this->showSubtasks) {
            $this->showGrandchildren = false;
        }
    }

    public function toggleGrandchildren()
    {
        // Only allow toggling grandchildren when subtasks are visible
        if (! $this->showSubtasks) {
            return;
        }

        $this->showGrandchildren = ! $this->showGrandchildren;
    }

    public function render()
    {
        return view('livewire.tasks');
    }
};
?>
