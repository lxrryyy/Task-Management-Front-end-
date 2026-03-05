<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class TaskController extends Controller
{
    public function __construct(protected CsharpApiService $api) {}

    public function index(int $projectId)
    {
        $user = Session::get('user', []);
        $accountId = $user['id'] ?? $user['Id'] ?? null;

        if (!$accountId) {
            return view('tasks', [
                'projectId' => $projectId,
                'tasks'     => [],
            ]);
        }

        // Check if current user is the project leader
        $isLeader = false;
        try {
            $project = $this->api->get("/api/Project/GetProjectById/{$projectId}");
            $createdById = $project['createdById'] ?? $project['createdBy'] ?? null;
            $isLeader = $createdById && (int) $createdById === (int) $accountId;
        } catch (\Exception $e) {
            $project = null;
        }

        try {
            // Always fetch all tasks for the project so parent/sub/grandchild layout works.
            // API requires requesterId as a query parameter.
            $projectResponse = $this->api->get(
                "/api/Task/GetTasksByProject/{$projectId}",
                ['requesterId' => $accountId]
            );
            $allTasks = is_array($projectResponse)
                ? $projectResponse
                : ($projectResponse['data'] ?? $projectResponse['tasks'] ?? []);

            // Mark tasks assigned to current user, so the view can highlight them if desired.
            $assignedIds = [];
            if ($accountId) {
                try {
                    $assignedResponse = $this->api->get("/api/Task/GetTasksByAssignee/{$accountId}");
                    $assignedTasks = is_array($assignedResponse)
                        ? $assignedResponse
                        : ($assignedResponse['data'] ?? $assignedResponse['tasks'] ?? []);
                    foreach ($assignedTasks as $t) {
                        if (isset($t['id'])) {
                            $assignedIds[(int) $t['id']] = true;
                        }
                    }
                } catch (\Exception $e) {
                    // If this call fails we still show tasks, just without isMine flag.
                }
            }

            $tasks = [];
            foreach ($allTasks as $t) {
                if (isset($t['id']) && isset($assignedIds[(int) $t['id']])) {
                    $t['isMine'] = true;
                } else {
                    $t['isMine'] = false;
                }
                $tasks[] = $t;
            }
        } catch (\Exception $e) {
            $tasks = [];
        }

        // Fetch all accounts then narrow down to project members for the assignee dropdown
        $accounts = [];
        try {
            $accountsRaw = $this->api->get('/api/Account/GetAllUserRoleAccount');
            $allAccounts = is_array($accountsRaw)
                ? $accountsRaw
                : ($accountsRaw['data'] ?? $accountsRaw['accounts'] ?? []);

            // Keep only accounts that belong to this project
            $accounts = $this->projectMemberAccounts($project ?? [], $allAccounts);
        } catch (\Exception $e) {
            $accounts = [];
        }

        return view('tasks', [
            'projectId' => $projectId,
            'project'   => $project ?? null,
            'tasks'     => $tasks,
            'accounts'  => $accounts,
        ]);
    }

    /**
     * Fetch all task statuses from the C# API.
     * Returns ['map' => [name => id, ...], 'names' => [name, ...]]
     */
    /**
     * Response shape: [{id, name, description, active, createdAt}, ...]
     * Returns ['map' => [name => id, ...], 'names' => [name, ...]]
     */
    public function getStatuses(): array
    {
        try {
            $list = $this->api->get('/api/Task/GetAllTasksStatuses');

            $map   = [];
            $names = [];
            foreach ((array) $list as $s) {
                $id   = $s['id']   ?? null;
                $name = $s['name'] ?? null;
                if ($id !== null && $name !== null) {
                    $map[$name] = (int) $id;
                    $names[]    = $name;
                }
            }
            return ['map' => $map, 'names' => $names];
        } catch (\Throwable) {
            return ['map' => [], 'names' => []];
        }
    }

    /**
     * Response shape: [{id, name, description, active, createdAt}, ...]
     * Returns an ordered list of priority name strings.
     */
    public function getPriorities(): array
    {
        try {
            $list = $this->api->get('/api/Task/GetAllTasksPriorities');

            $names = [];
            foreach ((array) $list as $p) {
                if (isset($p['name'])) {
                    $names[] = $p['name'];
                }
            }
            return $names;
        } catch (\Throwable) {
            return [];
        }
    }

    public function updateStatus(int $projectId, int $taskId, int $statusId): void
    {
        $user        = Session::get('user', []);
        $requesterId = $user['id'] ?? $user['Id'] ?? null;

        $this->api->patch(
            "/api/Task/UpdateTaskStatus/{$taskId}?requesterId={$requesterId}",
            ['statusId' => $statusId]
        );
    }

    public function store(int $projectId, Request $request)
    {
        $user      = Session::get('user', []);
        $creatorId = $user['id'] ?? $user['Id'] ?? null;

        $request->validate([
            'name'       => 'required|string|max:255',
            'assigneeId' => 'nullable|integer',
        ]);

        $toDate = static function (mixed $v): ?string {
            if (!$v) return null;
            try { return \Carbon\Carbon::parse($v)->format('Y-m-d\TH:i:s.v\Z'); }
            catch (\Throwable) { return null; }
        };

        $parentTaskId = $request->integer('parentTaskId') ?: null;
        $assigneeId   = $request->integer('assigneeId')   ?: null;

        // Build body matching the C# API spec
        $payload = [
            'title'        => $request->input('name'),
            'description'  => $request->input('description') ?? '',
            'priority'     => $request->input('priority') ?? '',
            'storyPoints'  => $request->integer('storyPoints') ?: 0,
            'projectId'    => $projectId,
            'parentTaskId' => $parentTaskId,
            'startDate'    => $toDate($request->input('startDate')),
            'dueDate'      => $toDate($request->input('dueDate')),
            'assigneeIds'  => $assigneeId ? [$assigneeId] : [],
        ];

        // Remove null date values so we don't send null strings
        if ($payload['startDate'] === null) unset($payload['startDate']);
        if ($payload['dueDate']   === null) unset($payload['dueDate']);
        if ($payload['parentTaskId'] === null) unset($payload['parentTaskId']);

        try {
            // creatorId passed as query parameter per the API spec
            $this->api->post("/api/Task/CreateTask?creatorId={$creatorId}", $payload);
        } catch (RequestException $e) {
            $fieldErrors = $this->api->extractFieldErrors($e->response);
            Log::warning('Task create failed', ['projectId' => $projectId, 'errors' => $fieldErrors]);
            return back()->withInput()->withErrors($fieldErrors);
        } catch (\Throwable $e) {
            Log::error('Task create exception', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['api_error' => ['Failed to create task. Please try again.']]);
        }

        return redirect()->route('projects.tasks', $projectId)
            ->with('success', 'Task created successfully.');
    }

    /**
     * Given the raw project array and the full accounts list, return only
     * the accounts that are members of the project (PM, Scrum Master, members).
     *
     * Falls back to the full list when the project data carries no member info.
     */
    private function projectMemberAccounts(array $project, array $allAccounts): array
    {
        if (empty($project)) {
            return $allAccounts;
        }

        // Collect every person ID associated with the project
        $memberIds = [];

        // Project manager / creator
        foreach (['projectManagerId', 'createdById', 'createdBy'] as $key) {
            if (!empty($project[$key])) {
                $memberIds[(int) $project[$key]] = true;
                break;
            }
        }

        // Scrum master
        foreach (['scrumMasterId', 'ScrumMasterId'] as $key) {
            if (!empty($project[$key])) {
                $memberIds[(int) $project[$key]] = true;
                break;
            }
        }

        // Regular members by ID array
        foreach (['memberIds', 'MemberIds', 'assigneeIds'] as $key) {
            if (!empty($project[$key]) && is_array($project[$key])) {
                foreach ($project[$key] as $mid) {
                    if ($mid) $memberIds[(int) $mid] = true;
                }
                break;
            }
        }

        // If member IDs are unavailable, fall back to matching memberNames against the account list
        $memberNames = $project['memberNames'] ?? $project['Members'] ?? [];
        if (!empty($memberNames) && is_array($memberNames)) {
            $normalised = array_map('mb_strtolower', array_map('trim', $memberNames));
            foreach ($allAccounts as $account) {
                $aid   = $account['id']   ?? $account['Id']   ?? null;
                $aname = mb_strtolower(trim($account['name'] ?? $account['Name'] ?? ''));
                if ($aid && in_array($aname, $normalised, true)) {
                    $memberIds[(int) $aid] = true;
                }
            }
        }

        // If we still couldn't identify any members, return the full list
        if (empty($memberIds)) {
            return $allAccounts;
        }

        return array_values(
            array_filter($allAccounts, fn ($a) => isset($memberIds[(int) ($a['id'] ?? $a['Id'] ?? 0)])
        ));
    }
}

