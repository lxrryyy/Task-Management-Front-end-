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

        // Fetch all accounts for the assignee dropdown in the Add Task modal
        $accounts = [];
        try {
            $accountsRaw = $this->api->get('/api/Account/GetAllUserRoleAccount');
            $accounts = is_array($accountsRaw)
                ? $accountsRaw
                : ($accountsRaw['data'] ?? $accountsRaw['accounts'] ?? []);
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

    public function store(int $projectId, Request $request)
    {
        $user      = Session::get('user', []);
        $requesterId = $user['id'] ?? $user['Id'] ?? null;

        $request->validate([
            'name'     => 'required|string|max:255',
            'assigneeId' => 'nullable|integer',
        ]);

        $toDate = static function (mixed $v): ?string {
            if (!$v) return null;
            try { return \Carbon\Carbon::parse($v)->format('Y-m-d\TH:i:s.v\Z'); }
            catch (\Throwable) { return null; }
        };

        $parentTaskId = $request->integer('parentTaskId') ?: null;

        $payload = array_filter([
            'name'         => $request->input('name'),
            'description'  => $request->input('description') ?: null,
            'projectId'    => $projectId,
            'assigneeId'   => $request->integer('assigneeId') ?: null,
            'parentTaskId' => $parentTaskId,
            'priority'     => $request->input('priority') ?: null,
            'storyPoints'  => $request->integer('storyPoints') ?: null,
            'startDate'    => $toDate($request->input('startDate')),
            'dueDate'      => $toDate($request->input('dueDate')),
            'requesterId'  => $requesterId,
        ], static fn ($v) => $v !== null);

        try {
            $this->api->post('/api/Task/CreateTask', $payload);
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
}

