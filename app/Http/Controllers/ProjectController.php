<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ProjectController extends Controller
{
    public function __construct(protected CsharpApiService $api) {}

    public function index()
    {
        $user = Session::get('user', []);
        $accountId = $user['id'] ?? $user['Id'] ?? null;

        if (!$accountId) {
            return view('projects', ['projects' => [], 'accounts' => [], 'creatorId' => 0]);
        }

        $response = $this->api->get("/api/Project/GetMyProjects/{$accountId}");
        $projects = $this->normalizeProjects($response);

        // If we just updated a project, merge in fresh data from GetProjectById (members etc.)
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

        // Derive task-based progress and status for each project using GetTasksByProject.
        foreach ($projects as $i => $project) {
            $projectId = $project['id'] ?? $project['Id'] ?? null;
            if (!$projectId) {
                continue;
            }

            // Use project manager / creator as privileged requester so all project tasks are visible,
            // regardless of who is currently logged in.
            $pmId = $project['projectManagerId']
                ?? $project['ProjectManagerId']
                ?? $project['createdById']
                ?? $project['CreatedById']
                ?? $accountId;

            try {
                $tasksResponse = $this->api->get(
                    "/api/Task/GetTasksByProject/{$projectId}",
                    ['requesterId' => $pmId]
                );
                $tasks = is_array($tasksResponse)
                    ? $tasksResponse
                    : ($tasksResponse['data'] ?? $tasksResponse['tasks'] ?? []);

                $total = 0;
                $completed = 0;
                foreach ((array) $tasks as $t) {
                    if (!is_array($t)) {
                        continue;
                    }
                    $status = mb_strtolower(trim((string) ($t['statusName'] ?? $t['status'] ?? '')));
                    if ($status === '') {
                        $status = 'not started';
                    }
                    $total++;
                    if ($status === 'completed') {
                        $completed++;
                    }
                }

                $projects[$i]['_taskTotal']     = $total;
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
            } catch (\Throwable $e) {
                Log::warning('GetTasksByProject for status/progress failed', [
                    'projectId' => $projectId,
                    'message'   => $e->getMessage(),
                ]);
            }
        }

        $accountsResponse = $this->api->get('/api/Account/GetAllUserRoleAccount');
        $accounts = $this->normalizeAccounts($accountsResponse);

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

        if (!$accountId) {
            return view('projects-archive', ['projects' => [], 'accounts' => [], 'creatorId' => 0]);
        }

        // Fetch deleted (archived) projects
        try {
            $response = $this->api->get('/api/Project/GetDeletedProjects');
            $projects = $this->normalizeProjects($response);
        } catch (\Throwable) {
            $projects = [];
        }

        // Accounts are useful for resolving member names when API returns assigneeIds
        try {
            $accountsResponse = $this->api->get('/api/Account/GetAllUserRoleAccount');
            $accounts = $this->normalizeAccounts($accountsResponse);
        } catch (\Throwable) {
            $accounts = [];
        }

        return view('projects-archive', [
            'projects'  => $projects,
            'accounts'  => $accounts,
            'creatorId' => (int) $accountId,
        ]);
    }

    /**
     * DELETE a project via the C# API.
     * Endpoint: DELETE /api/Project/DeleteProject/{id}?accountId={accountId}
     */
    public function destroy(int $projectId)
    {
        $user = Session::get('user', []);
        $accountId = (int) ($user['id'] ?? $user['Id'] ?? 0);

        if ($accountId <= 0) {
            return redirect()->route('login');
        }

        try {
            $this->api->delete("/api/Project/DeleteProject/{$projectId}?accountId={$accountId}");
        } catch (RequestException $e) {
            $fieldErrors = $this->api->extractFieldErrors($e->response);
            Log::warning('Project delete failed', ['projectId' => $projectId, 'errors' => $fieldErrors]);
            return back()->withErrors(['api_error' => 'Failed to delete project. Please try again.']);
        }

        return redirect()->route('Projects')->with('message', 'Project deleted successfully.');
    }

    /**
     * Same delete behavior, usable from Livewire without an HTTP form submit.
     */
    public function deleteProjectApi(int $projectId, int $accountId): bool
    {
        try {
            $this->api->delete("/api/Project/DeleteProject/{$projectId}?accountId={$accountId}");
            return true;
        } catch (RequestException $e) {
            $fieldErrors = $this->api->extractFieldErrors($e->response);
            Log::warning('Project delete failed', ['projectId' => $projectId, 'errors' => $fieldErrors]);
            return false;
        }
    }

    public function show($id)
    {
        $project = $this->api->get("/api/Project/GetProjectById/{$id}");
        return view('project', ['project' => $project]);
    }

    public function update(int $projectId, Request $request)
    {
        $user = Session::get('user', []);
        $requesterId = $user['id'] ?? $user['Id'] ?? null;

        if (!$requesterId) {
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

        // Collect ALL member IDs: form may send memberIds[] or memberIds[0], memberIds[1], etc.
        $rawMemberIds = $request->input('memberIds', []);
        if (!is_array($rawMemberIds)) {
            $rawMemberIds = $rawMemberIds !== null && $rawMemberIds !== '' ? [(int) $rawMemberIds] : [];
        }
        $memberIds = array_values(array_filter(array_map('intval', $rawMemberIds), static fn ($id) => $id > 0));

        // Resolve status: prefer statusId -> name, fallback to status string
        $status = $request->status;
        if ($status === null && $request->filled('statusId')) {
            $statusData = $this->getStatuses();
            $status = $statusData['mapById'][(int) $request->statusId] ?? null;
        }

        // Creator is project manager; scrum master from request or creator
        $projectManagerId = (int) ($user['id'] ?? $user['Id'] ?? 0);
        $scrumMasterId = (int) ($request->scrumMasterId ?? 0) ?: $projectManagerId;

        // C# backend uses AssigneeIds (not MemberIds) for updating the member list
        $payload = [
            'name'             => $request->name,
            'description'      => $request->description ?? '',
            'status'           => $status,
            'projectManagerId' => $projectManagerId,
            'scrumMasterId'    => $scrumMasterId,
            'assigneeIds'      => $memberIds,
            'startDate'        => $this->toIso8601OrNull($request->input('startDate')),
            'endDate'          => $this->toIso8601OrNull($request->input('endDate')),
        ];

        try {
            $this->api->patch("/api/Project/UpdateProject/{$projectId}?requesterId={$requesterId}", $payload);
        } catch (RequestException $e) {
            $fieldErrors = $this->api->extractFieldErrors($e->response);
            Log::warning('Project update failed', ['projectId' => $projectId, 'errors' => $fieldErrors]);
            return back()->withInput()->withErrors($fieldErrors);
        }

        // Re-fetch updated project (including members) via GetProjectById for the list
        $updated = $this->api->get("/api/Project/GetProjectById/{$projectId}");
        if (!empty($updated) && is_array($updated)) {
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
     * Expects a ready-to-send $payload shaped for the backend UpdateProject endpoint.
     */
    public function updateProjectApi(int $projectId, array $payload, int $requesterId): array
    {
        try {
            $this->api->patch("/api/Project/UpdateProject/{$projectId}?requesterId={$requesterId}", $payload);
            return ['ok' => true, 'errors' => []];
        } catch (RequestException $e) {
            $fieldErrors = $this->api->extractFieldErrors($e->response);
            return ['ok' => false, 'errors' => $fieldErrors];
        }
    }

     // Update only the project status. Resolves statusId to name and PATCHes the project.
     
    public function updateProjectStatusApi(int $projectId, int $statusId, int $requesterId): array
    {
        $statusData = $this->getStatuses();
        $statusName = $statusData['mapById'][$statusId] ?? null;
        if ($statusName === null) {
            return ['ok' => false, 'errors' => ['status' => ['Invalid status selected.']]];
        }
        // Some backend implementations require a full payload to update.
        // Fetch the current project and send the minimal required fields + new status.
        $current = $this->getProjectData($projectId);

        $payload = [
            'name'        => $current['name'] ?? $current['projectName'] ?? $current['title'] ?? null,
            'description' => $current['description'] ?? '',
            'status'      => $statusName,
        ];

        // Include IDs if present (common backend requirements)
        $pmId = $current['projectManagerId'] ?? $current['ProjectManagerId'] ?? $current['createdById'] ?? $current['CreatedById'] ?? null;
        $smId = $current['scrumMasterId'] ?? $current['ScrumMasterId'] ?? null;
        if ($pmId !== null) $payload['projectManagerId'] = (int) $pmId;
        if ($smId !== null) $payload['scrumMasterId'] = (int) $smId;

        // Include current members if available so status updates don't accidentally clear them
        $assigneeIds = $current['assigneeIds'] ?? $current['AssigneeIds'] ?? null;
        if (is_array($assigneeIds)) {
            $payload['assigneeIds'] = array_values(array_filter(array_map('intval', $assigneeIds), static fn ($id) => $id > 0));
        }

        return $this->updateProjectApi($projectId, $payload, $requesterId);
    }

    /**
     * Restore (reactivate) a deleted project via the C# API.
     * Endpoint: PATCH /api/Project/ReactivateProject/{projectId}?accountId={accountId}
     */
    public function restoreProjectApi(int $projectId, int $accountId): array
    {
        try {
            $this->api->patch("/api/Project/ReactivateProject/{$projectId}?accountId={$accountId}", []);
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
        if (!$creatorId) {
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

        // memberIds from checkbox selection (hidden memberIds[] in form)
        $memberIds = [];
        if (!empty($request->memberIds) && is_array($request->memberIds)) {
            $memberIds = array_values(array_filter(array_map('intval', $request->memberIds)));
        }

        // Resolve status name from statusId for API
        $status = null;
        if ($request->filled('statusId')) {
            $statusData = $this->getStatuses();
            $status = $statusData['mapById'][(int) $request->statusId] ?? null;
        }

        // Creator is always project manager; scrum master is creator unless a member is chosen in the table
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
        if ($status !== null) {
            $payload['status'] = $status;
        }

        try {
            $this->api->post("/api/Project/CreateProject?creatorId={$creatorId}", $payload);
        } catch (RequestException $e) {
            $fieldErrors = $this->api->extractFieldErrors($e->response);
            Log::warning('Project create failed', ['errors' => $fieldErrors]);
            return back()->withInput()->withErrors($fieldErrors);
        }

        return redirect()->route('Projects');
    }

    /**
     * Fetch a single project by ID from the C# API.
     * Returns the raw project array, or [] on failure.
     */
    public function getProjectData(int $projectId): array
    {
        try {
            $project = $this->api->get("/api/Project/GetProjectById/{$projectId}");
            return is_array($project) ? $project : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Fetch all accounts (members) used in the project form.
     * Endpoint: GET /api/Account/GetAllUserRoleAccount
     */
    public function getAccountsData(): array
    {
        try {
            $accountsResponse = $this->api->get('/api/Account/GetAllUserRoleAccount');
            return $this->normalizeAccounts($accountsResponse);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Response shape: [{id, name, description, active, createdAt}, ...]
     * Returns ['items' => [{id, name}, ...], 'names' => [...], 'map' => [name => id], 'mapById' => [id => name]].
     */
    public function getStatuses(): array
    {
        try {
            $raw = $this->api->get('/api/Project/GetAllProjectsStatus');

            $list = $raw['data'] ?? $raw['Data']
                ?? $raw['items'] ?? $raw['Items']
                ?? $raw['value'] ?? $raw['Value']
                ?? $raw['statuses'] ?? $raw['Statuses']
                ?? $raw['result'] ?? $raw['Result']
                ?? $raw;

            if (!is_array($list)) {
                $list = [];
            }

            if (!empty($list) && array_keys($list) !== range(0, count($list) - 1)) {
                $list = [$list];
            }

            $map     = [];
            $mapById = [];
            $names   = [];
            $items   = [];
            foreach ($list as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $id   = $s['id'] ?? $s['Id'] ?? $s['statusId'] ?? $s['StatusId'] ?? null;
                $name = $s['name'] ?? $s['Name'] ?? $s['statusName'] ?? $s['StatusName'] ?? null;
                if ($id !== null && $name !== null) {
                    $id   = (int) $id;
                    $name = (string) trim($name);
                    if ($name !== '') {
                        $map[$name]   = $id;
                        $mapById[$id] = $name;
                        $names[]      = $name;
                        $items[]      = ['id' => $id, 'name' => $name];
                    }
                }
            }

            return ['items' => $items, 'names' => $names, 'map' => $map, 'mapById' => $mapById];
        } catch (\Throwable) {
            return ['items' => [], 'names' => [], 'map' => [], 'mapById' => []];
        }
    }

    private function normalizeProjects($response): array
    {
        if (!is_array($response)) {
            return [];
        }

        // Some APIs return: [ {..}, {..} ]
        if (isset($response[0]) && is_array($response[0])) {
            return $response;
        }

        // Some APIs wrap results: { data: [...] } or { projects: [...] }
        foreach (['data', 'projects', 'items', 'value', 'result'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return [];
    }

    private function normalizeAccounts($response): array
    {
        if (!is_array($response)) {
            return [];
        }

        if (isset($response[0]) && is_array($response[0])) {
            return $response;
        }

        foreach (['data', 'accounts', 'items', 'value', 'result'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return [];
    }

    /** Return ISO 8601 date string or null for PATCH request body. */
    private function toIso8601OrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            $date = \Carbon\Carbon::parse($value);
            return $date->format('Y-m-d\TH:i:s.v\Z');
        } catch (\Throwable) {
            return null;
        }
    }
}