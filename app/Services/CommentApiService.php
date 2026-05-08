<?php

namespace App\Services;

class CommentApiService
{
    public function __construct(private CsharpApiService $api) {}

    /**
     * GET /api/v1/tasks/{taskId}/comments
     *
     * @return list<array<string, mixed>>
     */
    public function listByTask(int $taskId): array
    {
        $raw = $this->api->get("/api/v1/tasks/{$taskId}/comments", ['_no_cache' => 1]);
        $list = is_array($raw)
            ? ($raw['data'] ?? $raw['comments'] ?? $raw['items'] ?? (isset($raw[0]) ? $raw : []))
            : [];

        $comments = [];
        foreach ((array) $list as $c) {
            if (! is_array($c)) {
                continue;
            }
            $comments[] = [
                'id' => (int) ($c['id'] ?? $c['Id'] ?? 0),
                'taskId' => (int) ($c['taskId'] ?? $c['TaskId'] ?? 0),
                'accountId' => (int) ($c['accountId'] ?? $c['AccountId'] ?? 0),
                'accountName' => (string) ($c['accountName'] ?? $c['AccountName'] ?? 'User'),
                'content' => (string) ($c['content'] ?? $c['Content'] ?? ''),
                'createdAt' => (string) ($c['createdAt'] ?? $c['CreatedAt'] ?? ''),
                'updatedAt' => (string) ($c['updatedAt'] ?? $c['UpdatedAt'] ?? ''),
            ];
        }

        return $comments;
    }

    /**
     * POST /api/v1/tasks/{taskId}/comments
     *
     * @return array<string, mixed>
     */
    public function create(int $taskId, string $content): array
    {
        return $this->api->post("/api/v1/tasks/{$taskId}/comments", ['content' => $content]);
    }

    /** PATCH /api/v1/tasks/{taskId}/comments/{commentId} */
    public function update(int $taskId, int $commentId, string $content): void
    {
        $this->api->patch("/api/v1/tasks/{$taskId}/comments/{$commentId}", ['content' => $content]);
    }

    /** DELETE /api/v1/tasks/{taskId}/comments/{commentId} */
    public function delete(int $taskId, int $commentId): void
    {
        $this->api->delete("/api/v1/tasks/{$taskId}/comments/{$commentId}");
    }
}
