<?php

namespace App\Livewire;

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class Projects extends Component
{
    public string $search = '';
    public string $filterStatus = '';
    public string $filterProjectManager = '';
    public string $filterProgress = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

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
    public bool $showDetailsModal = false;
    public string $detailsProjectName = '';
    public string $detailsProjectDescription = '';
    public string $detailsProjectStartDate = '';
    public string $detailsProjectEndDate = '';
    public array $detailsMemberRows = [];
    public string $currentUserRole = '';
    public string $currentUserName = '';

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
    public bool $loading = true;

    public function mount(
        array $projects = [],
        array $accounts = [],
        int $creatorId = 0,
        array $selectedMemberIds = [],
        bool $showAddModal = false,
        bool $showEditModal = false,
        $editingProjectId = null
    ): void {
        $user = Session::get('user', []);
        $this->currentUserRole = mb_strtolower(trim((string) ($user['role'] ?? ($user['Role'] ?? ($user['roleName'] ?? ($user['RoleName'] ?? ''))))));
        $this->currentUserName = trim((string) ($user['name'] ?? ($user['Name'] ?? '')));

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

        $this->dispatch('projects-loaded');
    }

    #[\Livewire\Attributes\On('projects-loaded')]
    public function onProjectsLoaded(): void
    {
        $this->loading = false;
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

    public function clearFilters(): void
    {
        $this->filterStatus = '';
        $this->filterProjectManager = '';
        $this->filterProgress = '';
        $this->filterDateFrom = '';
        $this->filterDateTo = '';
    }

    public function confirmDelete(int $projectId): void
    {
        if (!$this->canManageProject($projectId)) {
            $this->addError('api_error', 'You are not allowed to delete this project.');
            return;
        }

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
        if (!$this->canManageProject($projectId)) {
            $this->addError('api_error', 'You are not allowed to delete this project.');
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
        if (!$this->canManageProject($projectId)) {
            $this->addError('api_error', 'You are not allowed to edit this project.');
            return;
        }

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
        if (!$this->canManageProject($projectId)) {
            $this->addError('api_error', 'You are not allowed to edit this project.');
            return null;
        }

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

    public function openDetails(int $projectId): void
    {
        $project = collect($this->projects)->first(function ($p) use ($projectId) {
            $id = (int) ($p['id'] ?? $p['Id'] ?? 0);
            return $id === (int) $projectId;
        }) ?? [];

        $pmId = (int) ($project['projectManagerId'] ?? $project['ProjectManagerId'] ?? $project['createdById'] ?? $project['CreatedById'] ?? 0);
        $smId = (int) ($project['scrumMasterId'] ?? $project['ScrumMasterId'] ?? 0);
        $assigneeIds = $project['assigneeIds'] ?? $project['AssigneeIds'] ?? [];
        $memberNames = $project['memberNames'] ?? $project['Members'] ?? [];
        $accountsById = [];
        foreach ($this->accounts as $acc) {
            $aid = (int) ($acc['id'] ?? $acc['Id'] ?? 0);
            if ($aid > 0) {
                $accountsById[$aid] = $acc;
            }
        }
        $rows = [];
        if (is_array($assigneeIds)) {
            foreach ($assigneeIds as $aid) {
                $aid = (int) $aid;
                if ($aid <= 0 || !isset($accountsById[$aid])) {
                    continue;
                }
                $acc = $accountsById[$aid];
                $rows[] = [
                    'name' => (string) ($acc['name'] ?? $acc['Name'] ?? 'Unknown'),
                    'email' => (string) ($acc['email'] ?? $acc['Email'] ?? ''),
                    'role' => $aid === $pmId ? 'Project Manager' : ($aid === $smId ? 'Scrum Master' : 'Member'),
                ];
            }
        }
        // Fallback: some list payloads provide member names but not assignee IDs.
        if (empty($rows) && is_array($memberNames) && !empty($memberNames)) {
            foreach ($memberNames as $memberName) {
                $memberName = trim((string) $memberName);
                if ($memberName === '') {
                    continue;
                }

                $matched = null;
                foreach ($this->accounts as $acc) {
                    $aname = trim((string) ($acc['name'] ?? $acc['Name'] ?? ''));
                    if ($aname !== '' && mb_strtolower($aname) === mb_strtolower($memberName)) {
                        $matched = $acc;
                        break;
                    }
                }

                $role = 'Member';
                if ($matched) {
                    $aid = (int) ($matched['id'] ?? $matched['Id'] ?? 0);
                    if ($aid > 0) {
                        $role = $aid === $pmId ? 'Project Manager' : ($aid === $smId ? 'Scrum Master' : 'Member');
                    }
                }

                $rows[] = [
                    'name' => $matched ? (string) ($matched['name'] ?? $matched['Name'] ?? $memberName) : $memberName,
                    'email' => $matched ? (string) ($matched['email'] ?? $matched['Email'] ?? '') : '',
                    'role' => $role,
                ];
            }
        }

        $this->detailsProjectName = (string) ($project['name'] ?? $project['projectName'] ?? $project['title'] ?? 'Project Details');
        $this->detailsProjectDescription = trim((string) ($project['description'] ?? ''));
        $rawStart = $project['startDate'] ?? $project['StartDate'] ?? null;
        $rawEnd = $project['endDate'] ?? $project['EndDate'] ?? null;
        $this->detailsProjectStartDate = $rawStart ? \Carbon\Carbon::parse($rawStart)->format('Y-m-d') : '';
        $this->detailsProjectEndDate = $rawEnd ? \Carbon\Carbon::parse($rawEnd)->format('Y-m-d') : '';
        $this->detailsMemberRows = $rows;
        $this->showDetailsModal = true;
    }

    public function closeDetailsModal(): void
    {
        $this->showDetailsModal = false;
        $this->detailsProjectName = '';
        $this->detailsProjectDescription = '';
        $this->detailsProjectStartDate = '';
        $this->detailsProjectEndDate = '';
        $this->detailsMemberRows = [];
    }

    private function canManageProject(int $projectId): bool
    {
        if ($this->currentUserRole === 'admin') {
            return true;
        }

        $project = collect($this->projects)->first(function ($p) use ($projectId) {
            $id = (int) ($p['id'] ?? $p['Id'] ?? 0);
            return $id === (int) $projectId;
        }) ?? [];

        if (empty($project)) {
            return false;
        }

        $pmId = (int) ($project['projectManagerId'] ?? $project['ProjectManagerId'] ?? $project['createdById'] ?? $project['CreatedById'] ?? 0);
        return $pmId > 0 && $pmId === (int) $this->creatorId;
    }

    public function render()
    {
        $query = mb_strtolower(trim((string) $this->search));
        $statusNeedle = mb_strtolower(trim((string) $this->filterStatus));
        $managerNeedle = trim((string) $this->filterProjectManager);
        $progressNeedle = trim((string) $this->filterProgress);
        $fromDate = trim((string) $this->filterDateFrom);
        $toDate = trim((string) $this->filterDateTo);

        $filtered = array_values(array_filter($this->projects, function ($p) use (
            $query,
            $statusNeedle,
            $managerNeedle,
            $progressNeedle,
            $fromDate,
            $toDate
        ) {
            $name = mb_strtolower((string) ($p['name'] ?? $p['projectName'] ?? $p['title'] ?? ''));
            $leaderDisplay = (string) ($p['createdByName'] ?? '—');
            $leader = mb_strtolower($leaderDisplay);
            $statusRaw = (string) ($p['_derivedStatus'] ?? $p['statusName'] ?? $p['status'] ?? '');
            $status = mb_strtolower($statusRaw);
            $members = mb_strtolower(implode(' ', (array) ($p['memberNames'] ?? [])));

            if ($query !== '') {
                $matchesQuery = str_contains($name, $query)
                    || str_contains($leader, $query)
                    || str_contains($status, $query)
                    || str_contains($members, $query);
                if (! $matchesQuery) return false;
            }

            if ($statusNeedle !== '' && $status !== $statusNeedle) {
                return false;
            }

            if ($managerNeedle !== '' && trim($leaderDisplay) !== $managerNeedle) {
                return false;
            }

            $progressValue = (int) ($p['completionPercentage'] ?? $p['progress'] ?? 0);
            if ($progressValue < 0) $progressValue = 0;
            if ($progressValue > 100) $progressValue = 100;

            if ($progressNeedle !== '') {
                $okProgress = match ($progressNeedle) {
                    '0-25' => $progressValue >= 0 && $progressValue <= 25,
                    '26-50' => $progressValue >= 26 && $progressValue <= 50,
                    '51-75' => $progressValue >= 51 && $progressValue <= 75,
                    '76-99' => $progressValue >= 76 && $progressValue <= 99,
                    '100' => $progressValue === 100,
                    default => true,
                };
                if (! $okProgress) return false;
            }

            $createdAt = (string) ($p['createdAt'] ?? '');
            if (($fromDate !== '' || $toDate !== '') && $createdAt !== '') {
                $createdTs = strtotime($createdAt);
                if ($createdTs === false) return false;
                if ($fromDate !== '') {
                    $fromTs = strtotime($fromDate . ' 00:00:00');
                    if ($fromTs !== false && $createdTs < $fromTs) return false;
                }
                if ($toDate !== '') {
                    $toTs = strtotime($toDate . ' 23:59:59');
                    if ($toTs !== false && $createdTs > $toTs) return false;
                }
            } elseif (($fromDate !== '' || $toDate !== '') && $createdAt === '') {
                return false;
            }

            return true;
        }));

        $projectManagerOptions = [];
        foreach ($this->projects as $p) {
            $pm = trim((string) ($p['createdByName'] ?? ''));
            if ($pm !== '') $projectManagerOptions[$pm] = true;
        }
        $projectManagerOptions = array_keys($projectManagerOptions);
        sort($projectManagerOptions);

        return view('livewire.projects', [
            'filteredProjects'      => $filtered,
            'projectStatuses'       => $this->projectStatuses,
            'projectStatusItems'    => $this->projectStatusItems,
            'projectStatusMapById'  => $this->projectStatusMapById,
            'projectStatusMap'      => $this->projectStatusMap,
            'formStatusId'          => $this->formStatusId,
            'projectManagerOptions' => $projectManagerOptions,
        ]);
    }
}

