<?php

namespace App\Http\Controllers;

use App\Services\AccountApiService;
use App\Services\AccountListEnrichment;
use App\Services\CsharpApiService;
use App\Services\ProjectApiService;
use App\Services\TaskApiService;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ProjectController extends Controller
{
    public function __construct(
        protected CsharpApiService $api,
        protected TaskApiService $tasksApi,
        protected ProjectApiService $projectsApi,
        protected AccountApiService $accountsApi,
    ) {}

    public function index()
    {
        $user = Session::get('user', []);
        $accountId = $user['id'] ?? $user['Id'] ?? null;

        if (! $accountId) {
            return view('projects', ['projects' => [], 'accounts' => [], 'creatorId' => 0]);
        }

        // v1 endpoint resolves visibility from the bearer token (admin → all, others → own).
        $projects = $this->projectsApi->listAll();

        if (Session::has('refreshed_project')) {
            $refreshed = Session::get('refreshed_project');
            Session::forget('refreshed_project');
            $refreshedId = $refreshed['id'] ?? $refreshed['Id'] ?? null;
            if ($refreshedId !== null) {
                foreach ($projects as $i => $project) {
                    $pid = $project['id'] ?? $project['Id'] ?? null;
                    if ((int) $pid === (int) $refreshedId) {
                        $projects[$i] = array_merge($project, $refreshed);
                        break;
                    }
                }
            }
        }

        // Derive task-based progress/status only when missing (one POST /api/v1/tasks/stats/batch per page).
        $needsStats = [];
        foreach ($projects as $i => $project) {
            $projectId = $project['id'] ?? $project['Id'] ?? null;
            if (! $projectId) {
                continue;
            }

            $existingProgress = $project['completionPercentage'] ?? $project['progress'] ?? null;
            $hasProgress = is_numeric($existingProgress);
            $existingStatus = trim((string) ($project['_derivedStatus'] ?? $project['statusName'] ?? $project['status'] ?? ''));
            $hasStatus = $existingStatus !== '';

            if ($hasProgress && $hasStatus) {
                $progressInt = max(0, min(100, (int) $existingProgress));
                $projects[$i]['completionPercentage'] = $progressInt;
                $projects[$i]['_derivedStatus'] = $existingStatus;

                continue;
            }

            $needsStats[$i] = (int) $projectId;
        }

        $statsByProject = [];
        if ($needsStats !== []) {
            try {
                $batch = $this->tasksApi->projectStatsBatch(array_values(array_unique(array_values($needsStats))));
                $rows = $batch['items'] ?? $batch['Items'] ?? [];
                foreach ((array) $rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $pid = (int) ($row['projectId'] ?? $row['ProjectId'] ?? 0);
                    if ($pid <= 0) {
                        continue;
                    }
                    $statsByProject[$pid] = [
                        'total' => (int) ($row['total'] ?? $row['Total'] ?? 0),
                        'completed' => (int) ($row['completed'] ?? $row['Completed'] ?? 0),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Task stats batch for project list failed', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        foreach ($needsStats as $i => $projectId) {
            $total = $statsByProject[$projectId]['total'] ?? 0;
            $completed = $statsByProject[$projectId]['completed'] ?? 0;

            $projects[$i]['_taskTotal'] = $total;
            $projects[$i]['_taskCompleted'] = $completed;

            if ($total > 0) {
                $projects[$i]['completionPercentage'] = (int) round(($completed / $total) * 100);
            }

            if ($total === 0) {
                $derivedStatus = 'Not Started';
            } elseif ($completed === $total) {
                $derivedStatus = 'Completed';
            } else {
                $derivedStatus = 'Active';
            }
            $projects[$i]['_derivedStatus'] = $derivedStatus;
        }

        $accounts = $this->accountsApi->listAssignableUsers();
        $accounts = app(AccountListEnrichment::class)->mergeFullProfilesWhereMissing($projects, $accounts);

        return view('projects', [
            'projects' => $projects,
            'accounts' => $accounts,
            'creatorId' => (int) $accountId,
        ]);
    }

    public function archive()
    {
        $user = Session::get('user', []);
        $accountId = $user['id'] ?? $user['Id'] ?? null;

        if (! $accountId) {
            return view('projects-archive', ['projects' => [], 'accounts' => [], 'creatorId' => 0]);
        }

        $projects = $this->projectsApi->listAll(includeDeleted: true);

        try {
            $accounts = $this->accountsApi->listAssignableUsers();
            $accounts = app(AccountListEnrichment::class)->mergeFullProfilesWhereMissing($projects, $accounts);
        } catch (\Throwable) {
            $accounts = [];
        }

        return view('projects-archive', [
            'projects' => $projects,
            'accounts' => $accounts,
            'creatorId' => (int) $accountId,
        ]);
    }

    /**
     * DELETE a project via the C# API.
     * Endpoint: DELETE /api/v1/projects/{id}
     */
    public function destroy(int $projectId)
    {
        $user = Session::get('user', []);
        $accountId = (int) ($user['id'] ?? $user['Id'] ?? 0);

        if ($accountId <= 0) {
            return redirect()->route('login');
        }

        try {
            $this->projectsApi->delete($projectId);
        } catch (RequestException $e) {
            $fieldErrors = $this->api->extractFieldErrors($e->response);
            Log::warning('Project delete failed', ['projectId' => $projectId, 'errors' => $fieldErrors]);

            return back()->withErrors(['api_error' => 'Failed to delete project. Please try again.']);
        }

        return redirect()->route('Projects')->with('message', 'Project deleted successfully.');
    }

    /**
     * Same delete behavior, usable from Livewire without an HTTP form submit.
     * The $accountId argument is preserved for backward compatibility — v1 resolves the
     * caller from the bearer token, so this value is no longer sent over the wire.
     */
    public function deleteProjectApi(int $projectId, int $accountId): bool
    {
        try {
            $this->projectsApi->delete($projectId);

            return true;
        } catch (RequestException $e) {
            $fieldErrors = $this->api->extractFieldErrors($e->response);
            Log::warning('Project delete failed', ['projectId' => $projectId, 'errors' => $fieldErrors]);

            return false;
        }
    }

    public function show($id)
    {
        $project = $this->projectsApi->find((int) $id);

        return view('project', ['project' => $project]);
    }

    public function update(int $projectId, Request $request)
    {
        $user = Session::get('user', []);
        $requesterId = $user['id'] ?? $user['Id'] ?? null;

        if (! $requesterId) {
            return redirect()->route('login');
        }

        $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'statusId' => ['nullable', 'integer'],
            'memberIds' => ['required', 'array', 'min:1'],
            'memberIds.*' => ['integer'],
            'scrumMasterId' => ['nullable', 'integer'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
        ]);

        $rawMemberIds = $request->input('memberIds', []);
        if (! is_array($rawMemberIds)) {
            $rawMemberIds = $rawMemberIds !== null && $rawMemberIds !== '' ? [(int) $rawMemberIds] : [];
        }
        $memberIds = array_values(array_filter(array_map('intval', $rawMemberIds), static fn ($id) => $id > 0));

        $projectManagerId = (int) ($user['id'] ?? $user['Id'] ?? 0);
        $scrumMasterId = (int) ($request->scrumMasterId ?? 0) ?: $projectManagerId;

        $payload = [
            'name' => $request->name,
            'description' => $request->description ?? '',
            'projectManagerId' => $projectManagerId,
            'scrumMasterId' => $scrumMasterId,
            'memberIds' => $memberIds,
            'startDate' => $this->toIso8601OrNull($request->input('startDate')),
            'endDate' => $this->toIso8601OrNull($request->input('endDate')),
        ];
        if ($request->filled('statusId')) {
            $payload['statusId'] = (int) $request->statusId;
        } elseif ($request->filled('status')) {
            $payload['status'] = (string) $request->status;
        }

        try {
            $this->projectsApi->update($projectId, $payload);
        } catch (RequestException $e) {
            $fieldErrors = $this->api->extractFieldErrors($e->response);
            Log::warning('Project update failed', ['projectId' => $projectId, 'errors' => $fieldErrors]);

            return back()->withInput()->withErrors($fieldErrors);
        }

        // Re-fetch updated project (including members) for the list.
        $updated = $this->projectsApi->find($projectId);
        if (! empty($updated)) {
            Session::put('refreshed_project', $updated);
        }

        $redirect = (string) $request->input('redirect_to', '');
        if ($redirect === 'dashboard') {
            return redirect()->route('dashboard');
        }

        return redirect()->route('Projects');
    }

    /**
     * Update a project via the C# API, usable from Livewire.
     * Accepts both legacy (`status` name + `assigneeIds`) and v1 (`statusId` + `memberIds`) payload shapes.
     * The third `$requesterId` parameter is no longer sent over the wire — kept for signature stability.
     */
    public function updateProjectApi(int $projectId, array $payload, int $requesterId): array
    {
        try {
            $this->projectsApi->update($projectId, $payload);

            return ['ok' => true, 'errors' => []];
        } catch (RequestException $e) {
            $fieldErrors = $this->api->extractFieldErrors($e->response);

            return ['ok' => false, 'errors' => $fieldErrors];
        }
    }

    /**
     * Update only the project status. Resolves statusId via v1 catalog and PATCHes via v1.
     */
    public function updateProjectStatusApi(int $projectId, int $statusId, int $requesterId): array
    {
        $statuses = $this->projectsApi->getStatusesFormatted();
        if (! isset($statuses['mapById'][$statusId])) {
            return ['ok' => false, 'errors' => ['status' => ['Invalid status selected.']]];
        }

        return $this->updateProjectApi($projectId, ['statusId' => $statusId], $requesterId);
    }

    /**
     * Restore (reactivate) a deleted project via the C# API.
     * Endpoint: PATCH /api/v1/projects/{projectId}/restore
     * The $accountId argument is preserved for compatibility with existing Livewire callers.
     */
    public function restoreProjectApi(int $projectId, int $accountId): array
    {
        try {
            $this->projectsApi->restore($projectId);

            return ['ok' => true, 'errors' => []];
        } catch (RequestException $e) {
            $fieldErrors = $this->api->extractFieldErrors($e->response);
            Log::warning('Project restore failed', ['projectId' => $projectId, 'accountId' => $accountId, 'errors' => $fieldErrors]);

            return ['ok' => false, 'errors' => $fieldErrors];
        }
    }

    public function store(Request $request)
    {
        $user = Session::get('user', []);
        $creatorId = $user['id'] ?? $user['Id'] ?? null;
        if (! $creatorId) {
            return redirect()->route('login');
        }

        $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'statusId' => ['nullable', 'integer'],
            'memberIds' => ['required', 'array', 'min:1'],
            'memberIds.*' => ['integer'],
            'scrumMasterId' => ['nullable', 'integer'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
        ]);

        $memberIds = [];
        if (! empty($request->memberIds) && is_array($request->memberIds)) {
            $memberIds = array_values(array_filter(array_map('intval', $request->memberIds)));
        }

        $projectManagerId = (int) $creatorId;
        $scrumMasterId = (int) ($request->scrumMasterId ?? 0) ?: $projectManagerId;
        $isAlsoScrumMaster = $projectManagerId === $scrumMasterId;

        $payload = [
            'name' => $request->name,
            'description' => $request->description,
            'projectManagerId' => $projectManagerId,
            'scrumMasterId' => $scrumMasterId,
            'memberIds' => $memberIds,
            'isAlsoScrumMaster' => $isAlsoScrumMaster,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
        ];

        $createdResponse = [];
        try {
            $createdResponse = $this->projectsApi->create($payload);
        } catch (RequestException $e) {
            $fieldErrors = $this->api->extractFieldErrors($e->response);
            Log::warning('Project create failed', ['errors' => $fieldErrors]);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Failed to add project. Please check the form and try again.',
                    'errors' => $fieldErrors,
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors($fieldErrors)
                ->with('error_toast', 'Failed to add project. Please check the form and try again.');
        }

        $successMessage = __('Project created successfully.');
        if ($request->expectsJson()) {
            $createdId = (int) ($createdResponse['id'] ?? $createdResponse['Id'] ?? 0);

            return response()->json([
                'ok' => true,
                'message' => $successMessage,
                'project' => [
                    'id' => $createdId > 0 ? $createdId : null,
                    'name' => (string) ($createdResponse['name'] ?? $payload['name'] ?? 'Project'),
                    'createdByName' => (string) ($createdResponse['createdByName'] ?? $user['name'] ?? $user['Name'] ?? 'You'),
                    'status' => (string) ($createdResponse['statusName'] ?? 'Not Started'),
                    'completionPercentage' => (int) ($createdResponse['completionPercentage'] ?? 0),
                    'createdAt' => (string) ($createdResponse['createdAt'] ?? now()->toDateString()),
                    'endDate' => (string) ($createdResponse['endDate'] ?? $payload['endDate'] ?? ''),
                ],
            ]);
        }

        $redirect = (string) $request->input('redirect_to', '');
        if ($redirect === 'dashboard') {
            return redirect()->route('dashboard')->with('success', $successMessage);
        }

        return redirect()->route('Projects')->with('success', $successMessage);
    }

    /**
     * Fetch a single project by ID from the C# API.
     * Returns the raw project array, or [] on failure.
     */
    public function getProjectData(int $projectId): array
    {
        return $this->projectsApi->find($projectId);
    }

    /**
     * Fetch all assignable accounts (members) used in the project form.
     * Endpoint: GET /api/v1/accounts?role=User&active=true (paginated, walked by AccountApiService::listAll).
     */
    public function getAccountsData(): array
    {
        try {
            return $this->accountsApi->listAssignableUsers();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Returns ['items' => [{id, name}, ...], 'names' => [...], 'map' => [name => id], 'mapById' => [id => name]].
     */
    public function getStatuses(): array
    {
        return $this->projectsApi->getStatusesFormatted();
    }

    /** Return ISO 8601 date string or null for PATCH request body. */
    private function toIso8601OrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $date = Carbon::parse($value);

            return $date->format('Y-m-d\TH:i:s.v\Z');
        } catch (\Throwable) {
            return null;
        }
    }
}
