<?php

use Livewire\Component;
use App\Services\CsharpApiService;

new class extends Component
{
    public $search = '';

    /** Show the "Add Project" modal (create only). */
    public bool $showAddModal = false;

    /** Show the "Edit Project" modal (update only). */
    public bool $showEditModal = false;

    public $projects = [];

    public $accounts = [];

    public $creatorId = 0;

    // 0 = creator is scrum master; otherwise member id chosen in table
    public $selectedScrumMasterId = 0;

    // @var int[] IDs of selected members (source of truth for selection)
    public $selectedMemberIds = [];

    // @var array<int, string> memberId => role ('Member' or 'Scrum Master')
    public $memberRoles = [];

    // Currently edited project id (null when creating)
    public $editingProjectId = null;

    // Simple form fields for editing
    public $formName = '';
    public $formDescription = '';
    public $formStartDate = '';
    public $formEndDate = '';

    public function mount(
        $projects = [],
        $accounts = [],
        $creatorId = 0,
        $selectedMemberIds = [],
        $showAddModal = false,
        $showEditModal = false,
        $editingProjectId = null
    ): void {
        $this->projects = $projects;
        $this->accounts = $accounts;
        $this->creatorId = (int) $creatorId;
        $this->selectedMemberIds = is_array($selectedMemberIds) ? array_values(array_map('intval', $selectedMemberIds)) : [];
        $this->showAddModal = (bool) $showAddModal;
        $this->showEditModal = (bool) $showEditModal;
        if ($showEditModal && $editingProjectId) {
            $this->editingProjectId = (int) $editingProjectId;
            $oldIds = old('memberIds');
            if (is_array($oldIds) && !empty($oldIds)) {
                $this->selectedMemberIds = array_values(array_map('intval', $oldIds));
                $this->formName        = (string) old('name', '');
                $this->formDescription = (string) old('description', '');
                $this->formStartDate   = (string) old('startDate', '');
                $this->formEndDate     = (string) old('endDate', '');

                // Restore scrum master and roles from old input
                $oldScrumMasterId = (int) old('scrumMasterId', 0);
                $this->selectedScrumMasterId = $oldScrumMasterId;
                $this->memberRoles = [];
                foreach ($this->selectedMemberIds as $mid) {
                    $mid = (int) $mid;
                    $this->memberRoles[$mid] = ($oldScrumMasterId > 0 && $mid === $oldScrumMasterId)
                        ? 'Scrum Master'
                        : 'Member';
                }
            } else {
                $this->startEdit((int) $editingProjectId);
            }
        }
    }

    /** Open the Add Project modal only; clears form state. */
    public function openModal()
    {
        $this->showAddModal = true;
        $this->showEditModal = false;
        $this->editingProjectId = null;
        $this->formName = '';
        $this->formDescription = '';
        $this->formStartDate = '';
        $this->formEndDate = '';
        $this->selectedMemberIds = [];
        $this->memberRoles = [];
        $this->selectedScrumMasterId = 0;
    }

    /** Close both modals and clear form state. */
    public function closeModal()
    {
        $this->showAddModal = false;
        $this->showEditModal = false;
        $this->editingProjectId = null;
        $this->formName = '';
        $this->formDescription = '';
        $this->formStartDate = '';
        $this->formEndDate = '';
        $this->selectedMemberIds = [];
        $this->memberRoles = [];
        $this->selectedScrumMasterId = 0;
    }

    /** Close only the Add modal. */
    public function closeAddModal()
    {
        $this->showAddModal = false;
        $this->selectedMemberIds = [];
        $this->memberRoles = [];
        $this->selectedScrumMasterId = 0;
    }

    /** Close only the Edit modal. */
    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingProjectId = null;
        $this->formName = '';
        $this->formDescription = '';
        $this->formStartDate = '';
        $this->formEndDate = '';
        $this->selectedMemberIds = [];
        $this->memberRoles = [];
        $this->selectedScrumMasterId = 0;
    }

    /** Open the Edit Project modal only; loads project and pre-fills form. */
    public function startEdit(int $projectId): void
    {
        $this->editingProjectId = $projectId;
        $this->showAddModal = false;
        $this->showEditModal = true;

        // Prefer fresh project data from API (includes memberIds), fall back to cached list.
        $project = null;
        try {
            $api = app(CsharpApiService::class);
            $project = $api->get("/api/Project/GetProjectById/{$projectId}");
        } catch (\Throwable $e) {
            $project = null;
        }

        if (!$project || !is_array($project)) {
            $project = collect($this->projects)->first(function ($p) use ($projectId) {
                $id = $p['id'] ?? $p['Id'] ?? null;
                return (int) $id === (int) $projectId;
            }) ?? [];
        }

        $this->formName = $project['name'] ?? $project['projectName'] ?? $project['title'] ?? '';
        $this->formDescription = $project['description'] ?? '';

        // Dates: store as Y-m-d for HTML date inputs
        $rawStart = $project['startDate'] ?? $project['StartDate'] ?? null;
        $rawEnd   = $project['endDate']   ?? $project['EndDate']   ?? null;
        $this->formStartDate = $rawStart ? \Carbon\Carbon::parse($rawStart)->format('Y-m-d') : '';
        $this->formEndDate   = $rawEnd   ? \Carbon\Carbon::parse($rawEnd)->format('Y-m-d')   : '';

        // Derive member IDs. API currently returns memberNames, not raw IDs.
        $memberIds = $project['memberIds'] ?? $project['MemberIds'] ?? [];
        $this->selectedMemberIds = [];

        if (is_array($memberIds) && !empty($memberIds)) {
            $this->selectedMemberIds = array_values(array_filter(array_map('intval', $memberIds)));
        } else {
            // Fallback: map memberNames -> account IDs by matching names
            $memberNames = $project['memberNames'] ?? $project['Members'] ?? [];
            if (is_array($memberNames) && !empty($memberNames)) {
                foreach ($memberNames as $memberName) {
                    $normalizedName = trim((string) $memberName);
                    if ($normalizedName === '') {
                        continue;
                    }

                    foreach ($this->accounts as $account) {
                        $aid = $account['id'] ?? $account['Id'] ?? null;
                        $aname = $account['name'] ?? $account['Name'] ?? '';
                        if ($aid !== null && trim((string) $aname) === $normalizedName) {
                            $this->selectedMemberIds[] = (int) $aid;
                            break;
                        }
                    }
                }
                // De-duplicate and normalize
                $this->selectedMemberIds = array_values(array_unique(array_map('intval', $this->selectedMemberIds)));
            }
        }

        // Exclude creator from members completely (they are project manager, not a member)
        $creatorIdInt = (int) $this->creatorId;
        $this->selectedMemberIds = array_values(array_filter($this->selectedMemberIds, static fn ($id) => (int) $id !== $creatorIdInt));

        // Resolve Scrum Master from backend response
        $scrumMasterId = $project['scrumMasterId'] ?? $project['ScrumMasterId'] ?? null;

        // If backend returns a name instead of an ID, resolve it to an ID
        if (!$scrumMasterId) {
            $scrumMasterName = $project['scrumMasterName'] ?? $project['ScrumMasterName'] ?? null;
            if ($scrumMasterName) {
                $normalizedSM = trim((string) $scrumMasterName);
                foreach ($this->accounts as $account) {
                    $aid   = $account['id']   ?? $account['Id']   ?? null;
                    $aname = $account['name'] ?? $account['Name'] ?? '';
                    if ($aid !== null && trim((string) $aname) === $normalizedSM) {
                        $scrumMasterId = (int) $aid;
                        break;
                    }
                }
            }
        }

        $scrumMasterIdInt = $scrumMasterId ? (int) $scrumMasterId : 0;
        $this->selectedScrumMasterId = $scrumMasterIdInt;

        // Build memberRoles from backend data
        $this->memberRoles = [];
        foreach ($this->selectedMemberIds as $mid) {
            $mid = (int) $mid;
            $this->memberRoles[$mid] = ($scrumMasterIdInt > 0 && $mid === $scrumMasterIdInt)
                ? 'Scrum Master'
                : 'Member';
        }
    }

    // Set role for a member; when role is Scrum Master, use as project scrum master.
    public function setMemberRole(int $memberId, string $role): void
    {
        $role = $role === 'Scrum Master' ? 'Scrum Master' : 'Member';

        $this->memberRoles[$memberId] = $role;

        if ($role === 'Scrum Master') {
            $this->selectedScrumMasterId = $memberId;
        } else {
            if ($this->selectedScrumMasterId === $memberId) {
                $this->selectedScrumMasterId = 0;
            }
        }
    }

    // Toggle selection from dropdown or table.
    public function toggleMember(int $id): void
    {
        $id = (int) $id;

        if (in_array($id, $this->selectedMemberIds, true)) {
            // Remove
            $this->selectedMemberIds = array_values(
                array_filter(
                    $this->selectedMemberIds,
                    static fn ($value) => (int) $value !== $id
                )
            );
            unset($this->memberRoles[$id]);

            if ($this->selectedScrumMasterId === $id) {
                $this->selectedScrumMasterId = 0;
            }
        } else {
            // Add (never add the creator — they are project manager)
            if ($this->creatorId && (int) $this->creatorId === $id) {
                return;
            }
            $this->selectedMemberIds[] = $id;
            $this->selectedMemberIds = array_values(array_unique(array_map('intval', $this->selectedMemberIds)));
        }
    }

    // Remove via table button simply toggles off the member.
    public function removeMember(int $id): void
    {
        $this->toggleMember($id);
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
