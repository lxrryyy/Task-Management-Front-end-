<?php

namespace App\Http\Controllers;

use App\Services\NotificationApiService;
use App\Services\TaskApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationApiService $notificationsApi,
        protected TaskApiService $tasksApi,
    ) {}

    private function isAuthenticated(): bool
    {
        $user = Session::get('user', []);
        return (int) ($user['id'] ?? $user['Id'] ?? 0) > 0;
    }

    /** GET /notifications */
    public function index(Request $request): JsonResponse
    {
        if (! $this->isAuthenticated()) {
            return response()->json([]);
        }

        $unreadOnly = filter_var($request->query('unreadOnly', false), FILTER_VALIDATE_BOOLEAN);
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(1, min(100, (int) $request->query('pageSize', 50)));

        try {
            return response()->json($this->notificationsApi->list($unreadOnly, $page, $pageSize));
        } catch (\Throwable $e) {
            Log::warning('NotificationController@index failed', ['error' => $e->getMessage()]);
            return response()->json([]);
        }
    }

    /** GET /notifications/unread */
    public function unread(): JsonResponse
    {
        if (! $this->isAuthenticated()) {
            return response()->json([]);
        }

        try {
            return response()->json($this->notificationsApi->list(unreadOnly: true, pageSize: 100));
        } catch (\Throwable) {
            return response()->json([]);
        }
    }

    /** GET /notifications/unread/count — lightweight, used by the polling badge. */
    public function unreadCount(): JsonResponse
    {
        if (! $this->isAuthenticated()) {
            return response()->json(['count' => 0]);
        }

        return response()->json(['count' => $this->notificationsApi->unreadCount()]);
    }

    /** PATCH /notifications/{id}/read */
    public function markRead(int $id): JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['message' => 'Invalid id.'], 422);
        }

        try {
            $this->notificationsApi->markRead($id);
            return response()->json(['message' => 'Notification marked as read.']);
        } catch (\Throwable $e) {
            Log::warning('NotificationController@markRead failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to mark read.'], 200);
        }
    }

    /** PATCH /notifications/{id}/unread */
    public function markUnread(int $id): JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['message' => 'Invalid id.'], 422);
        }

        try {
            $this->notificationsApi->markUnread($id);
            return response()->json(['message' => 'Notification marked as unread.']);
        } catch (\Throwable $e) {
            Log::warning('NotificationController@markUnread failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to mark unread.'], 200);
        }
    }

    /** PATCH /notifications/read-all */
    public function markAllRead(): JsonResponse
    {
        if (! $this->isAuthenticated()) {
            return response()->json(['message' => 'Invalid account.'], 422);
        }

        try {
            $affected = $this->notificationsApi->markAllRead();
            return response()->json([
                'message' => $affected > 0
                    ? "{$affected} notification(s) marked as read."
                    : 'No unread notifications found.',
                'markedAsRead' => $affected,
            ]);
        } catch (\Throwable $e) {
            Log::warning('NotificationController@markAllRead failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to mark all read.'], 200);
        }
    }

    /**
     * PATCH /notifications/read
     *
     * Body: { ids: number[] } — batch mark-as-read for the dropdown's bulk action.
     */
    public function markManyRead(Request $request): JsonResponse
    {
        $ids = (array) $request->input('ids', []);
        if ($ids === []) {
            return response()->json(['message' => 'No ids provided.', 'markedAsRead' => 0]);
        }

        try {
            $affected = $this->notificationsApi->markManyRead(array_values($ids));
            return response()->json(['markedAsRead' => $affected]);
        } catch (\Throwable $e) {
            Log::warning('NotificationController@markManyRead failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to mark read.', 'markedAsRead' => 0]);
        }
    }

    /**
     * GET /notifications/resolve-task-project?taskId=
     * Discover projectId for a task (notification deep-links).
     */
    public function resolveTaskProject(Request $request): JsonResponse
    {
        $taskId = (int) $request->query('taskId', 0);
        if ($taskId <= 0 || ! $this->isAuthenticated()) {
            return response()->json(['projectId' => null], 422);
        }

        $raw = $this->tasksApi->find($taskId);
        if (is_array($raw)) {
            $projectId = (int) ($raw['projectId'] ?? $raw['ProjectId'] ?? 0);
            if ($projectId > 0) {
                return response()->json(['projectId' => $projectId]);
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
            $this->notificationsApi->delete($id);
            return response()->json(['message' => 'Notification deleted.']);
        } catch (\Throwable $e) {
            Log::warning('NotificationController@destroy failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete.'], 200);
        }
    }

    /**
     * POST /notifications/delete
     *
     * Body: { ids: number[] } — single round-trip for the dropdown's bulk delete.
     */
    public function destroyMany(Request $request): JsonResponse
    {
        $ids = (array) $request->input('ids', []);
        if ($ids === []) {
            return response()->json(['message' => 'No ids provided.', 'deleted' => 0]);
        }

        try {
            $affected = $this->notificationsApi->deleteMany(array_values($ids));
            return response()->json(['deleted' => $affected]);
        } catch (\Throwable $e) {
            Log::warning('NotificationController@destroyMany failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete.', 'deleted' => 0]);
        }
    }
}
