<?php

namespace App\Livewire;

use Livewire\Component;

class UserManagement extends Component
{
    public string $search = '';

    public function render()
    {
        return view('livewire.user-management');
    }
}
