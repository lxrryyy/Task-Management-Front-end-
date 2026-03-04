<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
use Illuminate\Http\Request;
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

        $accountsResponse = $this->api->get('/api/Account/GetAllUserRoleAccount');
        $accounts = $this->normalizeAccounts($accountsResponse);

        return view('projects', [
            'projects' => $projects,
            'accounts' => $accounts,
            'creatorId' => (int) $accountId,
        ]);
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

        // Creator is project manager; scrum master from request or creator
        $projectManagerId = (int) ($user['id'] ?? $user['Id'] ?? 0);
        $scrumMasterId = (int) ($request->scrumMasterId ?? 0) ?: $projectManagerId;

        // Payload per PATCH /api/Project/UpdateProject/{projectId}; send MemberIds (PascalCase) for .NET binding
        $payload = [
            'name' => $request->name,
            'description' => $request->description ?? '',
            'status' => $request->status ?? null,
            'projectManagerId' => $projectManagerId,
            'scrumMasterId' => $scrumMasterId,
            'memberIds' => $memberIds,
            'MemberIds' => $memberIds,
            'startDate' => $this->toIso8601OrNull($request->input('startDate')),
            'endDate' => $this->toIso8601OrNull($request->input('endDate')),
        ];

        $this->api->patch("/api/Project/UpdateProject/{$projectId}?requesterId={$requesterId}", $payload);

        // Re-fetch updated project (including members) via GetProjectById for the list
        $updated = $this->api->get("/api/Project/GetProjectById/{$projectId}");
        if (!empty($updated) && is_array($updated)) {
            Session::put('refreshed_project', $updated);
        }

        return redirect()->route('Projects');
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

        // API requires creatorId query parameter
        $this->api->post("/api/Project/CreateProject?creatorId={$creatorId}", $payload);

        return redirect()->route('Projects');
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