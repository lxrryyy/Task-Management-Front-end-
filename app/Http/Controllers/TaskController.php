<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
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
     * Returns ['map' => [name => id, ...], 'names' => [name, ...], 'items' => [{id, name}, ...]]
     */
    public function getPriorities(): array
    {
        try {
            $raw = $this->api->get('/api/Task/GetAllTasksPriorities');

            // Unwrap common API response shapes: wrapped object or raw array
            $list = $raw['data'] ?? $raw['Data']
                ?? $raw['items'] ?? $raw['Items']
                ?? $raw['value'] ?? $raw['Value']
                ?? $raw['priorities'] ?? $raw['Priorities']
                ?? $raw['result'] ?? $raw['Result']
                ?? $raw['results'] ?? $raw['Results']
                ?? $raw;

            if (!is_array($list)) {
                $list = [];
            }

            // If $list is associative (object-style), it might be a single item — wrap it
            if (!empty($list) && array_keys($list) !== range(0, count($list) - 1)) {
                $list = [$list];
            }

            $map   = [];
            $names = [];
            $items = [];
            foreach ($list as $p) {
                if (!is_array($p)) {
                    continue;
                }
                $id   = $p['id'] ?? $p['Id'] ?? $p['priorityId'] ?? $p['PriorityId'] ?? null;
                $name = $p['name'] ?? $p['Name'] ?? $p['priorityName'] ?? $p['PriorityName'] ?? null;
                if ($id !== null && $name !== null) {
                    $id   = (int) $id;
                    $name = (string) trim($name);
                    if ($name !== '') {
                        $map[$name]   = $id;
                        $names[]      = $name;
                        $items[]      = ['id' => $id, 'name' => $name];
                    }
                }
            }

            return ['map' => $map, 'names' => $names, 'items' => $items];
        } catch (\Throwable $e) {
            Log::warning('GetAllTasksPriorities failed', ['message' => $e->getMessage(), 'url' => config('services.csharp_api.url') . '/api/Task/GetAllTasksPriorities']);
            return ['map' => [], 'names' => [], 'items' => []];
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

    /**
     * GET /tasks/calculate-due-date
     * Proxies to /api/Task/CheckAssigneeWorkload (new response shape)
     */
    public function calculateDueDate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'startDate'   => 'required|date',
            'storyPoints' => 'required|integer|min:0',
            'assigneeIds' => 'nullable|string',
            'projectId'   => 'nullable|integer|min:1',
        ]);

        $params = [
            'startDate'   => $data['startDate'],
            'storyPoints' => $data['storyPoints'],
        ];
        if (!empty($data['assigneeIds'])) $params['assigneeIds'] = $data['assigneeIds'];
        if (!empty($data['projectId']))  $params['projectId']  = (int) $data['projectId'];

        // New API shape:
        // { projectedStartDate, projectedDueDate, storyPoints, warnings: [{message,...}] }
        $result = $this->api->get('/api/Task/CheckAssigneeWorkload', $params);

        $due = null;
        if (is_array($result)) {
            $due = $result['projectedDueDate']
                ?? $result['ProjectedDueDate']
                ?? $result['dueDate']
                ?? $result['DueDate']
                ?? $result['dueAt']
                ?? null;
        }

        $warningsRaw = is_array($result)
            ? ($result['warnings'] ?? $result['Warnings'] ?? [])
            : [];
        $warningMessages = [];
        foreach ((array) $warningsRaw as $w) {
            if (is_array($w) && !empty($w['message'])) {
                $warningMessages[] = (string) $w['message'];
            } elseif (is_string($w) && trim($w) !== '') {
                $warningMessages[] = trim($w);
            }
        }

        return response()->json(['dueDate' => $due, 'warnings' => array_values(array_unique($warningMessages))]);
    }

    public function store(int $projectId, Request $request)
    {
        $user      = Session::get('user', []);
        $creatorId = $user['id'] ?? $user['Id'] ?? null;

        $request->validate([
            'name'        => 'required|string|max:255',
            'priorityId'  => 'required|integer|min:1',
            'assigneeIds' => 'nullable|string',
        ], [
            'priorityId.required' => 'Please select a priority.',
            'priorityId.min'       => 'Please select a valid priority.',
        ]);

        $toDate = static function (mixed $v): ?string {
            if (!$v) return null;
            try { return \Carbon\Carbon::parse($v)->format('Y-m-d\TH:i:s.v\Z'); }
            catch (\Throwable) { return null; }
        };

        $parentTaskId = $request->integer('parentTaskId') ?: null;
        $raw = $request->input('assigneeIds');
        $assigneeIds = is_array($raw)
            ? array_values(array_filter(array_map('intval', $raw)))
            : array_values(array_filter(array_map('intval', array_filter(explode(',', (string) $raw)))));

        $priorityId = $request->integer('priorityId');

        $payload = [
            'title'        => $request->input('name'),
            'description'  => $request->input('description') ?? '',
            'priorityId'   => $priorityId,
            'storyPoints'  => $request->integer('storyPoints') ?: 0,
            'projectId'    => $projectId,
            'parentTaskId' => $parentTaskId,
            'startDate'    => $toDate($request->input('startDate')),
            'dueDate'      => $toDate($request->input('dueDate')) ?: null,
            'assigneeIds'  => $assigneeIds,
        ];

        if ($payload['startDate'] === null)    unset($payload['startDate']);
        if ($payload['dueDate']   === null)    unset($payload['dueDate']);
        if ($payload['parentTaskId'] === null) unset($payload['parentTaskId']);

        try {
            // ✅ CHANGED: capture result to extract warnings
            $result = $this->api->post("/api/Task/CreateTask?creatorId={$creatorId}", $payload);

            // ✅ NEW: Extract warning messages if any assignee is overloaded
            $warnings = $result['warnings'] ?? [];
            $warningMessages = [];
            foreach ($warnings as $w) {
                if (!empty($w['message'])) {
                    $warningMessages[] = $w['message'];
                }
            }

            // ✅ CHANGED: flash warnings to session alongside success
            $redirect = (string) $request->input('redirect_to', '');
            if ($redirect === 'dashboard') {
                return redirect()->route('dashboard')
                    ->with('success', 'Task created successfully.')
                    ->with('last_project_id', $projectId)
                    ->with('task_warnings', $warningMessages);
            }

            return redirect()->route('projects.tasks', $projectId)
                ->with('success', 'Task created successfully.')
                ->with('task_warnings', $warningMessages);

        } catch (RequestException $e) {
            $response = $e->response;
            $status   = $response?->status();
            $body     = $response?->body();

            $fieldErrors = $this->api->extractFieldErrors($response);

            // Sanitize: never pass empty/falsey error strings to the view
            $fieldErrors = array_map(function ($msgs) {
                $clean = [];
                foreach ((array) $msgs as $m) {
                    if (!is_string($m)) continue;
                    $t = trim($m);
                    if ($t === '' || $t === '0') continue;
                    $clean[] = $t;
                }
                return array_values(array_unique($clean));
            }, $fieldErrors);
            $fieldErrors = array_filter($fieldErrors, fn ($msgs) => !empty($msgs));

            Log::warning('Task create failed', [
                'projectId' => $projectId,
                'status'    => $status,
                'body'      => is_string($body) ? mb_substr($body, 0, 5000) : null,
                'payload'   => $payload,
                'errors'    => $fieldErrors,
            ]);

            if (empty($fieldErrors) && $creatorId) {
                try {
                    $projectResponse = $this->api->get(
                        "/api/Task/GetTasksByProject/{$projectId}",
                        ['requesterId' => $creatorId]
                    );
                    $allTasks = is_array($projectResponse)
                        ? $projectResponse
                        : ($projectResponse['data'] ?? $projectResponse['tasks'] ?? []);

                    $needleTitle = mb_strtolower(trim((string) ($payload['title'] ?? '')));
                    $needlePrio  = (int) ($payload['priorityId'] ?? 0);

                    foreach ((array) $allTasks as $t) {
                        if (!is_array($t)) continue;
                        $tTitle = mb_strtolower(trim((string) ($t['title'] ?? $t['name'] ?? '')));
                        $tPrio  = (int) ($t['priorityId'] ?? $t['PriorityId'] ?? 0);

                        if ($needleTitle !== '' && $tTitle === $needleTitle && ($needlePrio === 0 || $tPrio === $needlePrio)) {
                            return redirect()->route('projects.tasks', $projectId)
                                ->with('success', 'Task created successfully.');
                        }
                    }
                } catch (\Throwable) {
                    // fall through to normal error handling
                }
            }

            if (empty($fieldErrors)) {
                $hint = $status ? " (HTTP {$status})" : '';
                return back()->withInput()->withErrors([
                    'api_error' => ["Failed to create task{$hint}. Please try again."],
                ]);
            }

            return back()->withInput()->withErrors($fieldErrors);

        } catch (\Throwable $e) {
            Log::error('Task create exception', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['api_error' => ['Failed to create task. Please try again.']]);
        }
    }

    /**
     * Fetch project-member accounts for assignee dropdown (same logic as index()).
     */
    public function getAssignableAccountsForProject(int $projectId, int $requesterId): array
    {
        try {
            $project = $this->api->get("/api/Project/GetProjectById/{$projectId}");
        } catch (\Throwable) {
            $project = [];
        }

        try {
            $accountsRaw = $this->api->get('/api/Account/GetAllUserRoleAccount');
            $allAccounts = is_array($accountsRaw)
                ? $accountsRaw
                : ($accountsRaw['data'] ?? $accountsRaw['accounts'] ?? []);

            $projArr = is_array($project) ? $project : [];
            $accounts = $this->projectMemberAccounts($projArr, (array) $allAccounts);

            $creatorId = (int) (
                $projArr['projectManagerId']
                ?? $projArr['ProjectManagerId']
                ?? $projArr['createdById']
                ?? $projArr['CreatedById']
                ?? $projArr['createdBy']
                ?? $projArr['CreatedBy']
                ?? 0
            );
            if ($creatorId > 0) {
                $accounts = array_values(array_filter($accounts, static function ($a) use ($creatorId) {
                    $aid = (int) ($a['id'] ?? $a['Id'] ?? 0);
                    return $aid !== $creatorId;
                }));
            }

            return $accounts;
        } catch (\Throwable) {
            return [];
        }
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

        $memberIds = [];

        foreach (['projectManagerId', 'createdById', 'createdBy'] as $key) {
            if (!empty($project[$key])) {
                $memberIds[(int) $project[$key]] = true;
                break;
            }
        }

        foreach (['scrumMasterId', 'ScrumMasterId'] as $key) {
            if (!empty($project[$key])) {
                $memberIds[(int) $project[$key]] = true;
                break;
            }
        }

        foreach (['memberIds', 'MemberIds', 'assigneeIds'] as $key) {
            if (!empty($project[$key]) && is_array($project[$key])) {
                foreach ($project[$key] as $mid) {
                    if ($mid) $memberIds[(int) $mid] = true;
                }
                break;
            }
        }

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

        if (empty($memberIds)) {
            return $allAccounts;
        }

        return array_values(
            array_filter($allAccounts, fn ($a) => isset($memberIds[(int) ($a['id'] ?? $a['Id'] ?? 0)])
        ));
    }
}
