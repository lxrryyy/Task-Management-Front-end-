<?php

use Livewire\Component;

new class extends Component
{
    public $search = '';

    public bool $showModal = false;

    public array $projects = [];

    public function openModal()
    {
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
    }

    public function render()
    {
        return view('livewire.projects');
    }

    public function navigateToTasks()
    {
        return redirect()->route('Tasks');
    }
};
?>

<div>
    {{-- Nothing in life is to be feared, it is only to be understood. Now is the time to understand more, so that we may fear less. - Maria Skłodowska-Curie --}}
</div>
