<?php

namespace App\Livewire;

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class DashboardProjectCreate extends Component
{
    public bool $showAddModal = false;

    public int $creatorId = 0;
    public array $accounts = [];

    public array $selectedMemberIds = [];
    public array $memberRoles = [];
    public int $selectedScrumMasterId = 0;

    protected $listeners = [
        'open-dashboard-project-create' => 'open',
    ];

    public function mount(): void
    {
        $user = Session::get('user', []);
        $this->creatorId = (int) ($user['id'] ?? ($user['Id'] ?? 0));

        // Load accounts via controller (no direct API calls in view/components).
        $this->accounts = app(ProjectController::class)->getAccountsData();
    }

    public function open(): void
    {
        $this->resetForm();
        $this->showAddModal = true;
    }

    public function close(): void
    {
        $this->showAddModal = false;
    }

    public function resetForm(): void
    {
        $this->selectedMemberIds = [];
        $this->memberRoles = [];
        $this->selectedScrumMasterId = 0;
    }

    public function toggleMember(int $id): void
    {
        $id = (int) $id;
        if ($id <= 0) return;

        // Don't allow selecting the creator in the members list (matches Projects component behavior).
        if ($this->creatorId > 0 && $id === (int) $this->creatorId) {
            return;
        }

        if (in_array($id, $this->selectedMemberIds, true)) {
            $this->selectedMemberIds = array_values(array_filter(
                $this->selectedMemberIds,
                static fn ($value) => (int) $value !== $id
            ));
            unset($this->memberRoles[$id]);

            if ($this->selectedScrumMasterId === $id) {
                $this->selectedScrumMasterId = 0;
            }
        } else {
            $this->selectedMemberIds[] = $id;
            $this->selectedMemberIds = array_values(array_unique(array_map('intval', $this->selectedMemberIds)));
        }
    }

    public function removeMember(int $id): void
    {
        $this->toggleMember($id);
    }

    public function setMemberRole(int $memberId, string $role): void
    {
        $role = $role === 'Scrum Master' ? 'Scrum Master' : 'Member';
        $this->memberRoles[(int) $memberId] = $role;

        if ($role === 'Scrum Master') {
            $this->selectedScrumMasterId = (int) $memberId;
        } elseif ($this->selectedScrumMasterId === (int) $memberId) {
            $this->selectedScrumMasterId = 0;
        }
    }

    public function render()
    {
        return view('livewire.dashboard-project-create');
    }
}

