<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
use Illuminate\Http\Request;
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

        return view('tasks', [
            'projectId' => $projectId,
            'project'   => $project ?? null,
            'tasks'     => $tasks,
        ]);
    }
}

