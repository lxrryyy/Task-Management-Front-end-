<?php

use Livewire\Component;

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

