<?php

namespace App\Services;

/**
 * Typed wrapper around the v1 sticky-notes API.
 *
 * Replaces /api/StickyNote/* with /api/v1/sticky-notes/*. Resolves the owner from
 * the bearer token; callers no longer pass /accountId/.
 */
class StickyNoteApiService
{
    public function __construct(private CsharpApiService $api) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        try {
            $raw = $this->api->get('/api/v1/sticky-notes', ['_no_cache' => 1]);
        } catch (\Throwable) {
            return [];
        }

        return is_array($raw) ? array_values($raw) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $raw = $this->api->get("/api/v1/sticky-notes/{$id}", ['_no_cache' => 1]);
        } catch (\Throwable) {
            return null;
        }

        return is_array($raw) && $raw !== [] ? $raw : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $content, bool $isPinned = false): array
    {
        return $this->api->post('/api/v1/sticky-notes', [
            'content' => $content,
            'isPinned' => $isPinned,
        ]);
    }

    /**
     * @param  array{content?: string|null, isPinned?: bool|null}  $payload
     * @return array<string, mixed>
     */
    public function update(int $id, array $payload): array
    {
        $body = [];
        if (array_key_exists('content', $payload) && $payload['content'] !== null) {
            $body['content'] = (string) $payload['content'];
        }
        if (array_key_exists('isPinned', $payload) && $payload['isPinned'] !== null) {
            $body['isPinned'] = (bool) $payload['isPinned'];
        }

        return $this->api->patch("/api/v1/sticky-notes/{$id}", $body);
    }

    public function delete(int $id): void
    {
        $this->api->delete("/api/v1/sticky-notes/{$id}");
    }
}
