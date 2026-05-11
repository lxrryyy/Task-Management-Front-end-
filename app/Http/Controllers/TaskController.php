<?php

namespace App\Http\Controllers;

use App\Http\Requests\Task\CreateTaskRequest;
use App\Services\AccountApiService;
use App\Services\AccountListEnrichment;
use App\Services\CsharpApiService;
use App\Services\ProjectApiService;
use App\Services\TaskApiService;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class TaskController extends Controller
{
    public function __construct(
        protected CsharpApiService $api,
        protected TaskApiService $tasksApi,
        protected ProjectApiService $projectsApi,
        protected AccountApiService $accountsApi,
    ) {}

    public function index(int $projectId)
    {
        $user = Session::get('user', []);
        $accountId = $user['id'] ?? $user['Id'] ?? null;

        $page = max(1, (int) request('page', 1));
        $pageSize = min(100, max(1, (int) request('page_size', request('pageSize', 20))));

        $tasksTotal = 0;
        $tasksPage = $page;
        $tasksPageSize = $pageSize;
        $tasksLastPage = 1;

        if (! $accountId) {
            return view('tasks', [
                'projectId' => $projectId,
                'tasks' => [],
                'project' => null,
                'accounts' => [],
                'tasksTotal' => 0,
                'tasksPage' => 1,
                'tasksPageSize' => $pageSize,
                'tasksLastPage' => 1,
            ]);
        }

        $project = null;
        try {
            $project = $this->projectsApi->find($projectId);
            if ($project === []) {
                $project = null;
            }
        } catch (\Exception $e) {
            $project = null;
        }

        $tasks = [];
        try {
            $listData = $this->tasksApi->list($page, $pageSize, $projectId);
            $rawItems = $listData['items'] ?? $listData['Items'] ?? [];

            foreach ((array) $rawItems as $t) {
                if (! is_array($t)) {
                    continue;
                }
                $assigneeIds = $t['assigneeIds'] ?? $t['AssigneeIds'] ?? [];
                $ids = array_map('intval', (array) $assigneeIds);
                $t['isMine'] = (bool) ($accountId && in_array((int) $accountId, $ids, true));
                $tasks[] = $t;
            }

            $tasksTotal = (int) ($listData['total'] ?? $listData['Total'] ?? 0);
            $tasksPage = (int) ($listData['page'] ?? $listData['Page'] ?? $page);
            $tasksPageSize = (int) ($listData['pageSize'] ?? $listData['PageSize'] ?? $pageSize);
            $tasksLastPage = $tasksPageSize > 0 ? max(1, (int) ceil($tasksTotal / $tasksPageSize)) : 1;
        } catch (\Exception $e) {
            $tasks = [];
        }

        // Fetch all accounts then narrow down to project members for the assignee dropdown
        $accounts = [];
        try {
            $allAccounts = $this->accountsApi->listAssignableUsers();

            // Keep only accounts that belong to this project
            $accounts = $this->projectMemberAccounts($project ?? [], $allAccounts);
            $accounts = app(AccountListEnrichment::class)->mergeFullProfilesWhereMissing(
                $project ? [$project] : [],
                $accounts
            );
        } catch (\Exception $e) {
            $accounts = [];
        }

        return view('tasks', [
            'projectId' => $projectId,
            'project' => $project ?? null,
            'tasks' => $tasks,
            'accounts' => $accounts,
            'tasksTotal' => $tasksTotal,
            'tasksPage' => $tasksPage,
            'tasksPageSize' => $tasksPageSize,
            'tasksLastPage' => $tasksLastPage,
        ]);
    }

    /**
     * Response shape: [{id, name, description, active, createdAt}, ...]
     * Returns ['map' => [name => id, ...], 'names' => [name, ...]]
     */
    public function getStatuses(): array
    {
        return $this->tasksApi->getStatusesFormatted();
    }

    /**
     * Response shape: [{id, name, description, active, createdAt}, ...]
     * Returns ['map' => [name => id, ...], 'names' => [name, ...], 'items' => [{id, name}, ...]]
     */
    public function getPriorities(): array
    {
        return $this->tasksApi->getPrioritiesFormatted();
    }

    public function updateStatus(int $projectId, int $taskId, int $statusId): void
    {
        $this->tasksApi->updateStatus($taskId, $statusId);
    }

    /**
     * GET /tasks/calculate-due-date
     * Proxies to GET /api/v1/tasks/check-workload
     */
    public function calculateDueDate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'startDate' => 'required|date',
            'storyPoints' => 'required|integer|min:0',
            'assigneeIds' => 'nullable',
            'assigneeIds.*' => 'integer|min:1',
            'projectId' => 'nullable|integer|min:1',
        ]);

        $storyPoints = (int) $data['storyPoints'];
        $allowedStoryPoints = [1, 2, 3, 5, 8, 13, 21];
        if (! in_array($storyPoints, $allowedStoryPoints, true)) {
            return response()->json([
                'dueDate' => null,
                'warnings' => ['Story points must be a Fibonacci number (1, 2, 3, 5, 8, 13, 21).'],
            ], 422);
        }
        $params = [
            // Keep original shape that worked for single-assignee flow.
            'startDate' => (string) $data['startDate'],
            'storyPoints' => $storyPoints,
        ];
        $rawAssigneeIds = $request->input('assigneeIds');
        $assigneeIds = is_array($rawAssigneeIds)
            ? array_values(array_filter(array_map('intval', $rawAssigneeIds)))
            : array_values(array_filter(array_map('intval', array_filter(explode(',', (string) $rawAssigneeIds)))));
        if (! empty($data['projectId'])) {
            $params['projectId'] = (int) $data['projectId'];
        }

        Log::info('[due-calc-debug] incoming calculate-due-date', [
            'request_query' => $request->query(),
            'validated' => $data,
            'raw_assignee_ids' => $rawAssigneeIds,
            'normalized_assignee_ids' => $assigneeIds,
            'proxy_base_params' => $params,
        ]);

        $queryParams = array_merge($params, ['_no_cache' => 1]);
        if (! empty($assigneeIds)) {
            $queryParams['assigneeIds'] = $assigneeIds;
        }

        try {
            Log::info('[due-calc-debug] single upstream request', ['params' => $queryParams]);
            $item = $this->tasksApi->checkWorkload($queryParams);
        } catch (RequestException $e) {
            Log::warning('[due-calc-debug] upstream request failed', [
                'status' => $e->response?->status(),
                'body' => $e->response?->body(),
            ]);
            $message = trim((string) ($e->response?->body() ?? ''));
            if ($message === '') {
                $message = 'Unable to auto-compute due date right now.';
            }

            return response()->json([
                'dueDate' => null,
                'warnings' => [$message],
            ], 422);
        }

        if (! is_array($item)) {
            return response()->json([
                'dueDate' => null,
                'warnings' => ['Unable to auto-compute due date right now.'],
            ], 422);
        }

        Log::info('[due-calc-debug] upstream response', [
            'result_keys' => array_keys($item),
            'projected_due_date' => $item['projectedDueDate'] ?? $item['ProjectedDueDate'] ?? null,
            'warnings_count' => count((array) ($item['warnings'] ?? $item['Warnings'] ?? [])),
        ]);

        $due = null;
        $candidateDue = $item['projectedDueDate']
            ?? $item['ProjectedDueDate']
            ?? $item['dueDate']
            ?? $item['DueDate']
            ?? $item['dueAt']
            ?? null;
        if ($candidateDue) {
            try {
                $due = (string) $candidateDue;
            } catch (\Throwable) {
                $due = null;
            }
        }

        $warningsRaw = (array) ($item['warnings'] ?? $item['Warnings'] ?? []);
        $warningMessages = [];
        foreach ($warningsRaw as $w) {
            if (is_array($w) && ! empty($w['message'])) {
                $warningMessages[] = (string) $w['message'];
            } elseif (is_string($w) && trim($w) !== '') {
                $warningMessages[] = trim($w);
            }
        }
        $warningMessages = array_values(array_unique($warningMessages));

        $responsePayload = ['dueDate' => $due, 'warnings' => $warningMessages];
        Log::info('[due-calc-debug] outgoing frontend response', $responsePayload);

        return response()->json($responsePayload);
    }

    public function store(int $projectId, CreateTaskRequest $request)
    {
        $user = Session::get('user', []);
        $creatorId = $user['id'] ?? $user['Id'] ?? null;

        $toDate = static function (mixed $v): ?string {
            if (! $v) {
                return null;
            }
            try {
                return Carbon::parse($v)->format('Y-m-d\TH:i:s.v\Z');
            } catch (\Throwable) {
                return null;
            }
        };

        $parentTaskId = $request->integer('parentTaskId') ?: null;
        $raw = $request->input('assigneeIds');
        $assigneeIds = is_array($raw)
            ? array_values(array_filter(array_map('intval', $raw)))
            : array_values(array_filter(array_map('intval', array_filter(explode(',', (string) $raw)))));

        $priorityId = $request->integer('priorityId');

        $payload = [
            'title' => $request->input('name'),
            'description' => $request->input('description') ?? '',
            'priorityId' => $priorityId,
            'storyPoints' => (int) $request->input('storyPoints'),
            'projectId' => $projectId,
            'parentTaskId' => $parentTaskId,
            'startDate' => $toDate($request->input('startDate')),
            'assigneeIds' => $assigneeIds,
        ];

        if ($payload['startDate'] === null) {
            unset($payload['startDate']);
        }
        if ($payload['parentTaskId'] === null) {
            unset($payload['parentTaskId']);
        }

        try {
            $result = $this->tasksApi->create($payload);

            $warnings = $result['warnings'] ?? [];
            $warningMessages = [];
            foreach ((array) $warnings as $w) {
                if (is_array($w) && ! empty($w['message'])) {
                    $warningMessages[] = $w['message'];
                }
            }

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
            $status = $response?->status();
            $body = $response?->body();

            $fieldErrors = $this->api->extractFieldErrors($response);

            // Sanitize: never pass empty/falsey error strings to the view
            $fieldErrors = array_map(function ($msgs) {
                $clean = [];
                foreach ((array) $msgs as $m) {
                    if (! is_string($m)) {
                        continue;
                    }
                    $t = trim($m);
                    if ($t === '' || $t === '0') {
                        continue;
                    }
                    $clean[] = $t;
                }

                return array_values(array_unique($clean));
            }, $fieldErrors);
            $fieldErrors = array_filter($fieldErrors, fn ($msgs) => ! empty($msgs));

            Log::warning('Task create failed', [
                'projectId' => $projectId,
                'status' => $status,
                'body' => is_string($body) ? mb_substr($body, 0, 5000) : null,
                'payload' => $payload,
                'errors' => $fieldErrors,
            ]);

            if (empty($fieldErrors) && $creatorId) {
                try {
                    $listResponse = $this->tasksApi->list(1, 500, $projectId);
                    $allTasks = $listResponse['items'] ?? $listResponse['Items'] ?? [];

                    $needleTitle = mb_strtolower(trim((string) ($payload['title'] ?? '')));
                    $needlePrio = (int) ($payload['priorityId'] ?? 0);

                    foreach ((array) $allTasks as $t) {
                        if (! is_array($t)) {
                            continue;
                        }
                        $tTitle = mb_strtolower(trim((string) ($t['title'] ?? $t['name'] ?? '')));
                        $tPrio = (int) ($t['priorityId'] ?? $t['PriorityId'] ?? 0);

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
            $project = $this->projectsApi->find($projectId);
        } catch (\Throwable) {
            $project = [];
        }

        try {
            $allAccounts = $this->accountsApi->listAssignableUsers();

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

            return app(AccountListEnrichment::class)->mergeFullProfilesWhereMissing(
                $projArr !== [] ? [$projArr] : [],
                $accounts
            );
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
            if (! empty($project[$key])) {
                $memberIds[(int) $project[$key]] = true;
                break;
            }
        }

        foreach (['scrumMasterId', 'ScrumMasterId'] as $key) {
            if (! empty($project[$key])) {
                $memberIds[(int) $project[$key]] = true;
                break;
            }
        }

        foreach (['memberIds', 'MemberIds', 'assigneeIds'] as $key) {
            if (! empty($project[$key]) && is_array($project[$key])) {
                foreach ($project[$key] as $mid) {
                    if ($mid) {
                        $memberIds[(int) $mid] = true;
                    }
                }
                break;
            }
        }

        $memberNames = $project['memberNames'] ?? $project['Members'] ?? [];
        if (! empty($memberNames) && is_array($memberNames)) {
            $normalised = array_map('mb_strtolower', array_map('trim', $memberNames));
            foreach ($allAccounts as $account) {
                $aid = $account['id'] ?? $account['Id'] ?? null;
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
