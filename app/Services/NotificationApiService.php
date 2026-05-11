<?php

namespace App\Services;

class NotificationApiService
{
    public function __construct(private CsharpApiService $api) {}

    /**
     * GET /api/v1/notifications?unreadOnly=&page=&pageSize=
     *
     * Returns a normalized list of notifications (newest first) the way the legacy
     * frontend code expects. Server-side pagination is honored, but for the dropdown
     * the caller asks for a single page large enough to cover the dropdown.
     *
     * @return list<array<string, mixed>>
     */
    public function list(bool $unreadOnly = false, int $page = 1, int $pageSize = 50): array
    {
        $raw = $this->api->get('/api/v1/notifications', [
            'unreadOnly' => $unreadOnly ? 'true' : 'false',
            'page' => max(1, $page),
            'pageSize' => max(1, min(100, $pageSize)),
            '_no_cache' => 1,
        ]);

        $items = is_array($raw) ? ($raw['items'] ?? $raw['Items'] ?? []) : [];

        return $this->normalizeMany(is_array($items) ? $items : []);
    }

    /**
     * GET /api/v1/notifications/unread/count
     *
     * Lightweight endpoint for badge polling — returns just a count integer.
     */
    public function unreadCount(): int
    {
        try {
            $raw = $this->api->get('/api/v1/notifications/unread/count', ['_no_cache' => 1]);
        } catch (\Throwable) {
            return 0;
        }

        if (! is_array($raw)) {
            return 0;
        }

        return (int) ($raw['count'] ?? $raw['Count'] ?? 0);
    }

    /** PATCH /api/v1/notifications/{id}/read */
    public function markRead(int $id): void
    {
        $this->api->patch("/api/v1/notifications/{$id}/read");
    }

    /** PATCH /api/v1/notifications/{id}/unread */
    public function markUnread(int $id): void
    {
        $this->api->patch("/api/v1/notifications/{$id}/unread");
    }

    /** PATCH /api/v1/notifications/read-all */
    public function markAllRead(): int
    {
        $raw = $this->api->patch('/api/v1/notifications/read-all');
        return is_array($raw) ? (int) ($raw['affected'] ?? $raw['Affected'] ?? 0) : 0;
    }

    /**
     * PATCH /api/v1/notifications/read
     *
     * @param  list<int>  $ids
     */
    public function markManyRead(array $ids): int
    {
        $clean = $this->cleanIds($ids);
        if ($clean === []) {
            return 0;
        }
        $raw = $this->api->patch('/api/v1/notifications/read', ['ids' => $clean]);
        return is_array($raw) ? (int) ($raw['affected'] ?? $raw['Affected'] ?? 0) : 0;
    }

    /** DELETE /api/v1/notifications/{id} */
    public function delete(int $id): void
    {
        $this->api->delete("/api/v1/notifications/{$id}");
    }

    /**
     * POST /api/v1/notifications/delete
     *
     * @param  list<int>  $ids
     */
    public function deleteMany(array $ids): int
    {
        $clean = $this->cleanIds($ids);
        if ($clean === []) {
            return 0;
        }
        $raw = $this->api->post('/api/v1/notifications/delete', ['ids' => $clean]);
        return is_array($raw) ? (int) ($raw['affected'] ?? $raw['Affected'] ?? 0) : 0;
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeMany(array $items): array
    {
        $out = [];
        foreach ($items as $n) {
            if (! is_array($n)) {
                continue;
            }
            $out[] = [
                'id' => (int) ($n['id'] ?? $n['Id'] ?? 0),
                'accountId' => (int) ($n['accountId'] ?? $n['AccountId'] ?? 0),
                'projectId' => isset($n['projectId']) || isset($n['ProjectId'])
                    ? (int) ($n['projectId'] ?? $n['ProjectId'])
                    : null,
                'taskId' => isset($n['taskId']) || isset($n['TaskId'])
                    ? (int) ($n['taskId'] ?? $n['TaskId'])
                    : null,
                'message' => (string) ($n['message'] ?? $n['Message'] ?? ''),
                'isRead' => (bool) ($n['isRead'] ?? $n['IsRead'] ?? false),
                'createdAt' => (string) ($n['createdAt'] ?? $n['CreatedAt'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * @param  list<int|string|null>  $ids
     * @return list<int>
     */
    private function cleanIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[] = $n;
            }
        }
        return array_values(array_unique($out));
    }
}
