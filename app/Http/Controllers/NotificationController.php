<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class NotificationController extends Controller
{
    public function __construct(protected CsharpApiService $api) {}

    private function accountId(): int
    {
        $user = Session::get('user', []);
        return (int) ($user['id'] ?? $user['Id'] ?? 0);
    }

    /** GET /notifications */
    public function index(): JsonResponse
    {
        $accountId = $this->accountId();
        if ($accountId <= 0) {
            return response()->json([]);
        }

        try {
            $data = $this->api->get("/api/Notification/GetNotifications/{$accountId}");
            return response()->json(is_array($data) ? $data : []);
        } catch (\Throwable $e) {
            return response()->json([], 200);
        }
    }

    /** GET /notifications/unread */
    public function unread(): JsonResponse
    {
        $accountId = $this->accountId();
        if ($accountId <= 0) {
            return response()->json([]);
        }

        try {
            $data = $this->api->get("/api/Notification/GetUnreadNotifications/{$accountId}");
            return response()->json(is_array($data) ? $data : []);
        } catch (\Throwable) {
            return response()->json([], 200);
        }
    }

    /** PUT /notifications/{id}/read */
    public function markRead(int $id): JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['message' => 'Invalid id.'], 422);
        }

        try {
            $data = $this->api->put("/api/Notification/{$id}/read", []);
            // Some endpoints return {message, notificationId, isRead}
            return response()->json(is_array($data) ? $data : ['message' => 'Notification marked as read.']);
        } catch (RequestException $e) {
            return response()->json(['message' => 'Failed to mark read.'], 200);
        } catch (\Throwable) {
            return response()->json(['message' => 'Failed to mark read.'], 200);
        }
    }

    /** PUT /notifications/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        $accountId = $this->accountId();
        if ($accountId <= 0) {
            return response()->json(['message' => 'Invalid account.'], 422);
        }

        try {
            // API expects accountId as a query parameter (not JSON body).
            $data = $this->api->put('/api/Notification/read-all?accountId='.$accountId, []);
            return response()->json(is_array($data) ? $data : ['message' => 'Notifications marked as read.']);
        } catch (RequestException) {
            return response()->json(['message' => 'Failed to mark all read.'], 200);
        } catch (\Throwable) {
            return response()->json(['message' => 'Failed to mark all read.'], 200);
        }
    }

    /**
     * GET /notifications/resolve-task-project?taskId=
     * Attempts to discover projectId for a task when the notification payload omits it.
     */
    public function resolveTaskProject(Request $request): JsonResponse
    {
        $taskId = (int) $request->query('taskId', 0);
        if ($taskId <= 0) {
            return response()->json(['projectId' => null], 422);
        }

        $requesterId = $this->accountId();
        if ($requesterId <= 0) {
            return response()->json(['projectId' => null], 422);
        }

        $candidates = [
            "/api/Task/GetTask/{$taskId}",
            "/api/Task/GetTaskById/{$taskId}",
            "/api/Task/GetTaskDetail/{$taskId}",
        ];

        foreach ($candidates as $endpoint) {
            try {
                $raw = $this->api->get($endpoint, ['requesterId' => $requesterId]);
                if (! is_array($raw)) {
                    continue;
                }
                $projectId = (int) ($raw['projectId'] ?? $raw['ProjectId'] ?? $raw['projectID'] ?? 0);
                if ($projectId > 0) {
                    return response()->json(['projectId' => $projectId]);
                }
                $inner = $raw['task'] ?? $raw['data'] ?? $raw['Task'] ?? null;
                if (is_array($inner)) {
                    $projectId = (int) ($inner['projectId'] ?? $inner['ProjectId'] ?? 0);
                    if ($projectId > 0) {
                        return response()->json(['projectId' => $projectId]);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return response()->json(['projectId' => null], 404);
    }

    /** DELETE /notifications/{id} */
    public function destroy(int $id): JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['message' => 'Invalid id.'], 422);
        }

        try {
            $data = $this->api->delete("/api/Notification/DeleteNotification/{$id}");
            return response()->json(is_array($data) ? $data : ['message' => 'Notification deleted.']);
        } catch (RequestException) {
            return response()->json(['message' => 'Failed to delete.'], 200);
        } catch (\Throwable) {
            return response()->json(['message' => 'Failed to delete.'], 200);
        }
    }
}

