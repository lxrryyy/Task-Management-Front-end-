<?php

namespace App\Livewire;

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class Projects extends Component
{
    public string $search = '';

    /** Show the "Add Project" modal (create only). */
    public bool $showAddModal = false;

    /** Show the "Edit Project" modal (update only). */
    public bool $showEditModal = false;

    public array $projects = [];
    public array $accounts = [];
    public int $creatorId = 0;

    /** 0 = creator is scrum master; otherwise member id chosen in table */
    public int $selectedScrumMasterId = 0;

    /** @var int[] */
    public array $selectedMemberIds = [];

    /** Locked member IDs when user clicks "Update Project". */
    public array $confirmedMemberIds = [];

    /** Show the "Confirm Update" overlay. */
    public bool $showConfirmDialog = false;

    /** Show the "Confirm Delete" overlay. */
    public bool $showDeleteConfirmDialog = false;

    public ?int $deletingProjectId = null;
    public string $deletingProjectName = '';

    /** @var array<int, string> memberId => role ('Member' or 'Scrum Master') */
    public array $memberRoles = [];

    public ?int $editingProjectId = null;

    // Form fields for editing
    public string $formName = '';
    public string $formDescription = '';
    public string $formStartDate = '';
    public string $formEndDate = '';
    public string $formStatus = '';
    public int $formStatusId = 0;

    // Project statuses from GET /api/Project/GetAllProjectsStatus
    public array $projectStatuses = [];
    public array $projectStatusItems = [];
    public array $projectStatusMapById = [];
    public array $projectStatusMap = [];

    public function mount(
        array $projects = [],
        array $accounts = [],
        int $creatorId = 0,
        array $selectedMemberIds = [],
        bool $showAddModal = false,
        bool $showEditModal = false,
        $editingProjectId = null
    ): void {
        $this->projects = $projects;
        $this->accounts = $accounts;
        $this->creatorId = (int) $creatorId;
        $this->selectedMemberIds = array_values(array_map('intval', $selectedMemberIds));
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
                $this->formStatusId    = (int) old('statusId', 0);

                $oldScrumMasterId = (int) old('scrumMasterId', 0);
                $this->selectedScrumMasterId = $oldScrumMasterId;
                $this->memberRoles = [];
                foreach ($this->selectedMemberIds as $mid) {
                    $this->memberRoles[(int) $mid] = ($oldScrumMasterId > 0 && (int) $mid === $oldScrumMasterId)
                        ? 'Scrum Master'
                        : 'Member';
                }
            } else {
                $this->startEdit((int) $editingProjectId);
            }
        }

        $statusData = app(ProjectController::class)->getStatuses();
        $this->projectStatuses      = $statusData['names'] ?? [];
        $this->projectStatusItems   = $statusData['items'] ?? [];
        $this->projectStatusMapById = $statusData['mapById'] ?? [];
        $this->projectStatusMap     = $statusData['map'] ?? [];

        if (empty($this->projectStatusItems)) {
            $this->projectStatuses      = ['Not Started', 'Active', 'Completed'];
            $this->projectStatusItems   = [
                ['id' => 1, 'name' => 'Not Started'],
                ['id' => 2, 'name' => 'Active'],
                ['id' => 3, 'name' => 'Completed'],
            ];
            $this->projectStatusMapById = [1 => 'Not Started', 2 => 'Active', 3 => 'Completed'];
            $this->projectStatusMap     = ['Not Started' => 1, 'Active' => 2, 'Completed' => 3];
        }
    }

    public function openModal(): void
    {
        $this->showAddModal = true;
        $this->showEditModal = false;
        $this->editingProjectId = null;
        $this->formName = '';
        $this->formDescription = '';
        $this->formStartDate = '';
        $this->formEndDate = '';
        $this->formStatus = '';
        $this->formStatusId = 0;
        $this->selectedMemberIds = [];
        $this->memberRoles = [];
        $this->selectedScrumMasterId = 0;
    }

    public function archiveSelected(): mixed
    {
        return $this->redirect(route('projects.archive'));
    }

    public function confirmDelete(int $projectId): void
    {
        $project = collect($this->projects)->first(function ($p) use ($projectId) {
            $id = (int) ($p['id'] ?? $p['Id'] ?? 0);
            return $id === (int) $projectId;
        }) ?? [];

        $this->deletingProjectId = (int) $projectId;
        $this->deletingProjectName = (string) ($project['name'] ?? $project['projectName'] ?? $project['title'] ?? 'this project');
        $this->showDeleteConfirmDialog = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteConfirmDialog = false;
        $this->deletingProjectId = null;
        $this->deletingProjectName = '';
    }

    public function deleteProject(): mixed
    {
        $projectId = (int) ($this->deletingProjectId ?? 0);
        if ($projectId <= 0) {
            $this->cancelDelete();
            return null;
        }

        $user = Session::get('user', []);
        $accountId = (int) ($user['id'] ?? $user['Id'] ?? 0);
        if ($accountId <= 0) {
            return $this->redirect(route('login'));
        }

        $ok = app(ProjectController::class)->deleteProjectApi($projectId, $accountId);
        if (!$ok) {
            $this->addError('api_error', 'Failed to delete project. Please try again.');
            $this->showDeleteConfirmDialog = false;
            return null;
        }

        $this->projects = array_values(array_filter($this->projects, function ($p) use ($projectId) {
            $id = (int) ($p['id'] ?? $p['Id'] ?? 0);
            return $id !== $projectId;
        }));

        $this->cancelDelete();
        session()->flash('message', 'Project deleted successfully.');
        return null;
    }

    public function closeModal(): void
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

    public function closeAddModal(): void
    {
        $this->showAddModal = false;
        $this->formStatus = '';
        $this->selectedMemberIds = [];
        $this->memberRoles = [];
        $this->selectedScrumMasterId = 0;
    }

    public function closeEditModal(): void
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
        $this->formStatusId = 0;
        $this->selectedMemberIds = [];
        $this->memberRoles = [];
        $this->selectedScrumMasterId = 0;
    }

    public function startEdit(int $projectId): void
    {
        $this->editingProjectId = $projectId;
        $this->showAddModal = false;
        $this->showEditModal = true;
        $this->showConfirmDialog = false;
        $this->confirmedMemberIds = [];

        $project = app(ProjectController::class)->getProjectData($projectId);
        if (empty($project)) {
            $project = collect($this->projects)->first(function ($p) use ($projectId) {
                $id = $p['id'] ?? $p['Id'] ?? null;
                return (int) $id === (int) $projectId;
            }) ?? [];
        }

        $this->formName        = (string) ($project['name'] ?? $project['projectName'] ?? $project['title'] ?? '');
        $this->formDescription = (string) ($project['description'] ?? '');
        $this->formStatus      = (string) ($project['statusName'] ?? $project['status'] ?? '');
        $this->formStatusId    = (int) ($project['statusId'] ?? $project['StatusId'] ?? ($this->projectStatusMap[$this->formStatus] ?? 0));

        $rawStart = $project['startDate'] ?? $project['StartDate'] ?? null;
        $rawEnd   = $project['endDate']   ?? $project['EndDate']   ?? null;
        $this->formStartDate = $rawStart ? \Carbon\Carbon::parse($rawStart)->format('Y-m-d') : '';
        $this->formEndDate   = $rawEnd   ? \Carbon\Carbon::parse($rawEnd)->format('Y-m-d')   : '';

        $memberIds = $project['assigneeIds'] ?? $project['AssigneeIds'] ?? $project['memberIds'] ?? $project['MemberIds'] ?? [];
        $this->selectedMemberIds = [];
        if (is_array($memberIds) && !empty($memberIds)) {
            $this->selectedMemberIds = array_values(array_filter(array_map('intval', $memberIds)));
        } else {
            $memberNames = $project['memberNames'] ?? $project['Members'] ?? [];
            if (is_array($memberNames) && !empty($memberNames)) {
                foreach ($memberNames as $memberName) {
                    $normalizedName = trim((string) $memberName);
                    if ($normalizedName === '') continue;
                    foreach ($this->accounts as $account) {
                        $aid = $account['id'] ?? $account['Id'] ?? null;
                        $aname = $account['name'] ?? $account['Name'] ?? '';
                        if ($aid !== null && trim((string) $aname) === $normalizedName) {
                            $this->selectedMemberIds[] = (int) $aid;
                            break;
                        }
                    }
                }
                $this->selectedMemberIds = array_values(array_unique(array_map('intval', $this->selectedMemberIds)));
            }
        }

        $creatorIdInt = (int) $this->creatorId;
        $this->selectedMemberIds = array_values(array_filter($this->selectedMemberIds, static fn ($id) => (int) $id !== $creatorIdInt));

        $scrumMasterId = $project['scrumMasterId'] ?? $project['ScrumMasterId'] ?? null;
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

        if ($scrumMasterIdInt > 0 && $scrumMasterIdInt !== $creatorIdInt) {
            if (!in_array($scrumMasterIdInt, $this->selectedMemberIds, true)) {
                $this->selectedMemberIds[] = $scrumMasterIdInt;
                $this->selectedMemberIds = array_values(array_unique(array_map('intval', $this->selectedMemberIds)));
            }
        }

        $this->memberRoles = [];
        foreach ($this->selectedMemberIds as $mid) {
            $mid = (int) $mid;
            $this->memberRoles[$mid] = ($scrumMasterIdInt > 0 && $mid === $scrumMasterIdInt) ? 'Scrum Master' : 'Member';
        }
    }

    public function setMemberRole(int $memberId, string $role): void
    {
        $role = $role === 'Scrum Master' ? 'Scrum Master' : 'Member';
        $this->memberRoles[$memberId] = $role;

        if ($role === 'Scrum Master') {
            $this->selectedScrumMasterId = $memberId;
        } elseif ($this->selectedScrumMasterId === $memberId) {
            $this->selectedScrumMasterId = 0;
        }
    }

    public function toggleMember(int $id): void
    {
        $id = (int) $id;

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
            if ($this->creatorId && (int) $this->creatorId === $id) {
                return;
            }
            $this->selectedMemberIds[] = $id;
            $this->selectedMemberIds = array_values(array_unique(array_map('intval', $this->selectedMemberIds)));
        }
    }

    public function removeMember(int $id): void
    {
        $this->toggleMember($id);
    }

    public function getEditMemberIds(): array
    {
        return array_values(array_map('intval', $this->selectedMemberIds));
    }

    public function prepareEditSubmit(): void
    {
        $this->confirmedMemberIds = array_values(array_filter(array_map('intval', $this->selectedMemberIds), static fn ($id) => $id > 0));
        $this->showConfirmDialog = true;
    }

    public function cancelConfirmDialog(): void
    {
        $this->showConfirmDialog = false;
    }

    public function submitEditProject(): mixed
    {
        $projectId = (int) $this->editingProjectId;
        if ($projectId <= 0) return null;

        $user = Session::get('user', []);
        $requesterId = $user['id'] ?? $user['Id'] ?? null;
        if (!$requesterId) {
            return $this->redirect(route('login'));
        }

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
            'projectManagerId' => $projectManagerId,
            'scrumMasterId'    => $scrumMasterId,
            'assigneeIds'      => $memberIds,
            'startDate'        => $toIso($this->formStartDate),
            'endDate'          => $toIso($this->formEndDate),
        ];
        if ($payload['startDate'] === null) unset($payload['startDate']);
        if ($payload['endDate'] === null) unset($payload['endDate']);

        $result = app(ProjectController::class)->updateProjectApi($projectId, $payload, (int) $requesterId);
        if (!($result['ok'] ?? false)) {
            $fieldErrors = $result['errors'] ?? [];
            foreach ($fieldErrors as $field => $msgs) {
                foreach ((array) $msgs as $m) {
                    if (trim((string) $m) !== '') $this->addError($field, $m);
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
            'filteredProjects'      => $filtered,
            'projectStatuses'       => $this->projectStatuses,
            'projectStatusItems'    => $this->projectStatusItems,
            'projectStatusMapById'  => $this->projectStatusMapById,
            'projectStatusMap'      => $this->projectStatusMap,
            'formStatusId'          => $this->formStatusId,
        ]);
    }
}

