<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
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

        // Snapshot project state BEFORE the update so we can diff it
        $before = $this->api->get("/api/Project/GetProjectById/{$projectId}");

        // C# backend uses AssigneeIds (not MemberIds) for updating the member list
        $payload = [
            'name'             => $request->name,
            'description'      => $request->description ?? '',
            'status'           => $request->status ?? null,
            'projectManagerId' => $projectManagerId,
            'scrumMasterId'    => $scrumMasterId,
            'assigneeIds'      => $memberIds,
            'startDate'        => $this->toIso8601OrNull($request->input('startDate')),
            'endDate'          => $this->toIso8601OrNull($request->input('endDate')),
        ];

        $this->api->patch("/api/Project/UpdateProject/{$projectId}?requesterId={$requesterId}", $payload);

        // Re-fetch updated project (including members) via GetProjectById for the list
        $updated = $this->api->get("/api/Project/GetProjectById/{$projectId}");
        if (!empty($updated) && is_array($updated)) {
            Session::put('refreshed_project', $updated);
        }

        // --- Diff & log what actually changed ---
        $changes = $this->diffProject($before, $payload, $memberIds, $updated);

        Log::info('Project updated', [
            'projectId'           => $projectId,
            'requesterId'         => $requesterId,
            'changes'             => $changes ?: ['(no changes detected)'],
            'submitted_memberIds' => $request->input('memberIds', []),
            'assigneeIds_sent'    => $memberIds,
            'before_members'      => $before['memberNames'] ?? [],
            'after_members'       => $updated['memberNames'] ?? [],
        ]);

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

    /**
     * Compare the project snapshot before the PATCH with what was submitted and
     * what the API returned afterwards. Returns a human-readable list of changes.
     */
    private function diffProject(array $before, array $payload, array $submittedMemberIds, array $after): array
    {
        $changes = [];

        // --- Name ---
        $nameBefore = $before['name'] ?? $before['Name'] ?? null;
        $nameAfter  = $payload['name'] ?? null;
        if ($nameBefore !== null && $nameAfter !== null && $nameBefore !== $nameAfter) {
            $changes[] = "Name: \"{$nameBefore}\" → \"{$nameAfter}\"";
        }

        // --- Description ---
        $descBefore = $before['description'] ?? $before['Description'] ?? '';
        $descAfter  = $payload['description'] ?? '';
        if ($descBefore !== $descAfter) {
            $changes[] = 'Description changed';
        }

        // --- Status ---
        // Only report a status change when we actually submitted a non-null value
        $statusBefore = $before['status'] ?? $before['Status'] ?? null;
        $statusAfter  = $payload['status'] ?? null;
        if ($statusAfter !== null && $statusBefore !== $statusAfter) {
            $changes[] = "Status: \"{$statusBefore}\" → \"{$statusAfter}\"";
        }

        // --- Members ---
        // Primary: compare memberNames from before/after API responses.
        // Fallback (ID-based) only runs when before has NO memberNames at all.
        $memberNamesBefore = array_values(array_filter(array_map('strval', (array) ($before['memberNames'] ?? []))));
        $memberNamesAfter  = array_values(array_filter(array_map('strval', (array) ($after['memberNames']  ?? []))));
        sort($memberNamesBefore);
        sort($memberNamesAfter);

        $beforeHasNames = !empty($memberNamesBefore);

        if ($memberNamesBefore !== $memberNamesAfter) {
            $removed = array_values(array_diff($memberNamesBefore, $memberNamesAfter));
            $added   = array_values(array_diff($memberNamesAfter,  $memberNamesBefore));
            if ($removed) {
                $changes[] = 'Members removed: ' . implode(', ', $removed);
            }
            if ($added) {
                $changes[] = 'Members added: ' . implode(', ', $added);
            }
        } elseif (!$beforeHasNames) {
            // No names available at all — fall back to raw ID comparison
            $memberIdsBefore = array_values(array_map('intval', array_filter(
                (array) ($before['memberIds'] ?? $before['MemberIds'] ?? [])
            )));
            sort($memberIdsBefore);
            $sortedSubmitted = $submittedMemberIds;
            sort($sortedSubmitted);
            if ($memberIdsBefore !== $sortedSubmitted) {
                $removedIds = array_values(array_diff($memberIdsBefore, $sortedSubmitted));
                $addedIds   = array_values(array_diff($sortedSubmitted, $memberIdsBefore));
                if ($removedIds) {
                    $changes[] = 'Members removed (IDs): ' . implode(', ', $removedIds);
                }
                if ($addedIds) {
                    $changes[] = 'Members added (IDs): ' . implode(', ', $addedIds);
                }
            }
        }

        // --- Scrum Master ---
        $smBefore     = $before['scrumMasterId']   ?? $before['ScrumMasterId']   ?? null;
        $smAfter      = $after['scrumMasterId']    ?? $after['ScrumMasterId']    ?? null;
        if ((int) $smBefore !== (int) $smAfter) {
            $smNameBefore = $before['scrumMasterName'] ?? $before['ScrumMasterName'] ?? $smBefore;
            $smNameAfter  = $after['scrumMasterName']  ?? $after['ScrumMasterName']  ?? $smAfter;
            $changes[] = "Scrum Master: \"{$smNameBefore}\" → \"{$smNameAfter}\"";
        }

        return $changes;
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