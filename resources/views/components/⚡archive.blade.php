<?php

use Livewire\Component;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Session;

new class extends Component
{
    public array $projects = [];
    public array $accounts = [];
    public int $creatorId = 0;
    public string $search = '';

    public function mount(array $projects = [], array $accounts = [], int $creatorId = 0): void
    {
        $this->projects = $projects;
        $this->accounts = $accounts;
        $this->creatorId = (int) $creatorId;
    }

    public function restoreProject(int $projectId): void
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return;
        }

        $user = Session::get('user', []);
        $accountId = (int) ($user['id'] ?? $user['Id'] ?? 0);
        if ($accountId <= 0) {
            $this->addError('api_error', 'You must be logged in to restore a project.');
            return;
        }

        $result = app(ProjectController::class)->restoreProjectApi($projectId, $accountId);
        if (!($result['ok'] ?? false)) {
            $this->addError('api_error', 'Failed to restore project. Please try again.');
            return;
        }

        // Remove from local list so UI updates immediately
        $this->projects = array_values(array_filter($this->projects, static function ($p) use ($projectId) {
            $id = (int) ($p['id'] ?? $p['Id'] ?? 0);
            return $id !== $projectId;
        }));

        session()->flash('message', 'Project restored successfully.');
    }

    public function render()
    {
        $query = mb_strtolower(trim((string) $this->search));
        $filtered = $query === ''
            ? $this->projects
            : array_values(array_filter($this->projects, function ($p) use ($query) {
                $name    = mb_strtolower($p['name'] ?? $p['projectName'] ?? $p['title'] ?? '');
                $leader  = mb_strtolower($p['projectManagerName'] ?? $p['ProjectManagerName'] ?? $p['createdByName'] ?? '');
                $status  = mb_strtolower($p['statusName'] ?? $p['status'] ?? '');
                $members = mb_strtolower(implode(' ', (array) ($p['memberNames'] ?? $p['Members'] ?? [])));
                return str_contains($name, $query)
                    || str_contains($leader, $query)
                    || str_contains($status, $query)
                    || str_contains($members, $query);
            }));

        return view('livewire.archive', [
            'filteredProjects' => $filtered,
            'accounts'         => $this->accounts,
            'creatorId'        => $this->creatorId,
        ]);
    }
};

?>

