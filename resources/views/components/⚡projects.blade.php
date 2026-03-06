<?php

use Livewire\Component;
use App\Http\Controllers\ProjectController;
use App\Services\CsharpApiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

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

    /** Locked member IDs when user clicks "Update Project" (used for PATCH so removal is not lost). */
    public array $confirmedMemberIds = [];

    /** Show the "Confirm Update" overlay (set after prepareEditSubmit so list is locked). */
    public bool $showConfirmDialog = false;

    // @var array<int, string> memberId => role ('Member' or 'Scrum Master')
    public $memberRoles = [];

    // Currently edited project id (null when creating)
    public $editingProjectId = null;

    // Simple form fields for editing
    public $formName = '';
    public $formDescription = '';
    public $formStartDate = '';
    public $formEndDate = '';
    public $formStatus = '';

    // Ordered list of project status names from GET /api/Project/GetAllProjectsStatus
    public array $projectStatuses = [];

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
                $this->formStatus      = (string) old('status', '');

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

        // Fetch project statuses via ProjectController
        $this->projectStatuses = app(ProjectController::class)->getStatuses();

        if (empty($this->projectStatuses)) {
            $this->projectStatuses = ['Not Started', 'Active', 'Completed'];
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
        $this->formStatus = '';
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
        $this->formStatus = '';
        $this->selectedMemberIds = [];
        $this->memberRoles = [];
        $this->selectedScrumMasterId = 0;
    }

    /** Close only the Add modal. */
    public function closeAddModal()
    {
        $this->showAddModal = false;
        $this->formStatus = '';
        $this->selectedMemberIds = [];
        $this->memberRoles = [];
        $this->selectedScrumMasterId = 0;
    }

    /** Close only the Edit modal. */
    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->showConfirmDialog = false;
        $this->confirmedMemberIds = [];
        $this->editingProjectId = null;
        $this->formName = '';
        $this->formDescription = '';
        $this->formStartDate = '';
        $this->formEndDate = '';
        $this->formStatus = '';
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
        $this->showConfirmDialog = false;
        $this->confirmedMemberIds = [];

        // Prefer fresh project data from API via ProjectController, fall back to cached list.
        $project = app(ProjectController::class)->getProjectData($projectId);

        if (empty($project)) {
            $project = collect($this->projects)->first(function ($p) use ($projectId) {
                $id = $p['id'] ?? $p['Id'] ?? null;
                return (int) $id === (int) $projectId;
            }) ?? [];
        }

        $this->formName        = $project['name'] ?? $project['projectName'] ?? $project['title'] ?? '';
        $this->formDescription = $project['description'] ?? '';
        $this->formStatus      = $project['statusName'] ?? $project['status'] ?? '';

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

    /** Return current selected member IDs for the edit form (used by "Yes, Update" to sync before submit). */
    public function getEditMemberIds(): array
    {
        return array_values(array_map('intval', $this->selectedMemberIds));
    }

    /** Lock in current member selection when user clicks "Update Project", then show confirm dialog. */
    public function prepareEditSubmit(): void
    {
        $this->confirmedMemberIds = array_values(array_filter(array_map('intval', $this->selectedMemberIds), static fn ($id) => $id > 0));
        $this->showConfirmDialog = true;
    }

    public function cancelConfirmDialog(): void
    {
        $this->showConfirmDialog = false;
    }

    /** Submit project update using locked member list (confirmedMemberIds) so removal is not lost. */
    public function submitEditProject(): mixed
    {
        $projectId = (int) $this->editingProjectId;
        if ($projectId <= 0) {
            return null;
        }

        $user = Session::get('user', []);
        $requesterId = $user['id'] ?? $user['Id'] ?? null;
        if (!$requesterId) {
            return $this->redirect(route('login'));
        }

        // Use locked-in list from "Update Project" click; fallback to current selection
        $memberIds = !empty($this->confirmedMemberIds)
            ? array_values(array_map('intval', $this->confirmedMemberIds))
            : array_values(array_filter(array_map('intval', $this->selectedMemberIds), static fn ($id) => $id > 0));
        if (empty($memberIds)) {
            $this->addError('memberIds', 'At least one member is required.');
            return null;
        }

        $projectManagerId = (int) $this->creatorId;
        $scrumMasterId = (int) $this->selectedScrumMasterId ?: $projectManagerId;

        $toIso = static function ($v): ?string {
            if ($v === null || $v === '') return null;
            try {
                return \Carbon\Carbon::parse($v)->format('Y-m-d\TH:i:s.v\Z');
            } catch (\Throwable) {
                return null;
            }
        };

        $payload = [
            'name'             => trim((string) $this->formName) ?: 'Project',
            'description'      => (string) $this->formDescription,
            'status'           => $this->formStatus ?: null,
            'projectManagerId' => $projectManagerId,
            'scrumMasterId'    => $scrumMasterId,
            'assigneeIds'      => $memberIds,
            'startDate'        => $toIso($this->formStartDate),
            'endDate'          => $toIso($this->formEndDate),
        ];
        if ($payload['startDate'] === null) unset($payload['startDate']);
        if ($payload['endDate'] === null) unset($payload['endDate']);

        Log::info('Project update payload', ['projectId' => $projectId, 'assigneeIds' => $memberIds, 'assigneeCount' => count($memberIds)]);

        $api = app(CsharpApiService::class);
        try {
            $api->patch("/api/Project/UpdateProject/{$projectId}?requesterId={$requesterId}", $payload);
        } catch (RequestException $e) {
            $fieldErrors = $api->extractFieldErrors($e->response);
            Log::warning('Project update failed', ['projectId' => $projectId, 'errors' => $fieldErrors, 'payload' => $payload]);
            foreach ($fieldErrors as $field => $msgs) {
                foreach ((array) $msgs as $m) {
                    if (trim((string) $m) !== '') {
                        $this->addError($field, $m);
                    }
                }
            }
            return null;
        }

        $updated = app(ProjectController::class)->getProjectData($projectId);
        if (!empty($updated) && is_array($updated)) {
            Session::put('refreshed_project', $updated);
        }

        $this->showEditModal = false;
        $this->showConfirmDialog = false;
        $this->confirmedMemberIds = [];
        $this->editingProjectId = null;
        return $this->redirect(route('Projects'));
    }

    public function render()
    {
        $query = mb_strtolower(trim((string) $this->search));

        if ($query === '') {
            $filtered = $this->projects;
        } else {
            $filtered = array_values(array_filter($this->projects, function ($p) use ($query) {
                $name    = mb_strtolower($p['name'] ?? $p['projectName'] ?? $p['title'] ?? '');
                $leader  = mb_strtolower($p['createdByName'] ?? '');
                $status  = mb_strtolower($p['statusName'] ?? $p['status'] ?? '');
                $members = mb_strtolower(implode(' ', (array) ($p['memberNames'] ?? [])));
                return str_contains($name, $query)
                    || str_contains($leader, $query)
                    || str_contains($status, $query)
                    || str_contains($members, $query);
            }));
        }

        return view('livewire.projects', [
            'filteredProjects' => $filtered,
            'projectStatuses'  => $this->projectStatuses,
        ]);
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
