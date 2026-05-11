<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TaskApiService
{
    public function __construct(private CsharpApiService $api) {}

    /**
     * @return array{items?: array, total?: int, page?: int, pageSize?: int}
     */
    public function list(int $page = 1, int $pageSize = 20, ?int $projectId = null): array
    {
        $query = [
            'page' => $page,
            'pageSize' => $pageSize,
            '_no_cache' => 1,
        ];
        if ($projectId !== null) {
            $query['projectId'] = $projectId;
        }

        return $this->api->get('/api/v1/tasks', $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): array
    {
        return $this->api->post('/api/v1/tasks', $payload);
    }

    public function find(int $taskId): ?array
    {
        try {
            return $this->api->get("/api/v1/tasks/{$taskId}", ['_no_cache' => 1]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(int $taskId, array $payload): void
    {
        $this->api->patch("/api/v1/tasks/{$taskId}", $payload);
    }

    public function updateStatus(int $taskId, int $statusId): void
    {
        $this->api->patch("/api/v1/tasks/{$taskId}/status", ['statusId' => $statusId]);
    }

    /**
     * @param  array<int>  $assigneeIds
     */
    public function assign(int $taskId, array $assigneeIds): array
    {
        return $this->api->patch("/api/v1/tasks/{$taskId}/assignees", [
            'assigneeIds' => array_values(array_filter(array_map('intval', $assigneeIds))),
        ]);
    }

    public function delete(int $taskId): void
    {
        $this->api->delete("/api/v1/tasks/{$taskId}");
    }

    public function restore(int $taskId): array
    {
        return $this->api->patch("/api/v1/tasks/{$taskId}/restore", []);
    }

    public function listAssignedToMe(int $page = 1, int $pageSize = 20): array
    {
        return $this->api->get('/api/v1/tasks/assigned-to-me', [
            'page' => $page,
            'pageSize' => $pageSize,
            '_no_cache' => 1,
        ]);
    }

    /** Raw statuses payload from API (cached). */
    public function getStatusesRaw(): array
    {
        return Cache::remember('task_catalog_statuses_v2', now()->addMinutes(30), function () {
            return $this->api->get('/api/v1/tasks/catalog/statuses', ['_no_cache' => 1]);
        });
    }

    /** Raw priorities payload from API (cached). */
    public function getPrioritiesRaw(): array
    {
        return Cache::remember('task_catalog_priorities_v2', now()->addMinutes(30), function () {
            return $this->api->get('/api/v1/tasks/catalog/priorities', ['_no_cache' => 1]);
        });
    }

    /**
     * @param  list<int>  $projectIds
     * @return array<string, mixed>
     */
    public function projectStatsBatch(array $projectIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $projectIds))));

        return $this->api->post('/api/v1/tasks/stats/batch', ['projectIds' => $ids]);
    }

    /**
     * Same behaviour as legacy GET /api/Task/CheckAssigneeWorkload (due-date + overload warnings).
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function checkWorkload(array $query): array
    {
        return $this->api->get('/api/v1/tasks/check-workload', array_merge($query, ['_no_cache' => 1]));
    }

    /**
     * @return array{map: array<string, int>, names: array<int, string>}
     */
    public function getStatusesFormatted(): array
    {
        try {
            $list = $this->getStatusesRaw();

            $map = [];
            $names = [];
            foreach ((array) $list as $s) {
                $id = $s['id'] ?? $s['Id'] ?? null;
                $name = $s['name'] ?? $s['Name'] ?? null;
                if ($id !== null && $name !== null) {
                    $map[$name] = (int) $id;
                    $names[] = $name;
                }
            }

            return ['map' => $map, 'names' => $names];
        } catch (\Throwable) {
            return ['map' => [], 'names' => []];
        }
    }

    /**
     * @return array{map: array<string, int>, names: array<int, string>, items: array<int, array{id:int, name:string}>}
     */
    public function getPrioritiesFormatted(): array
    {
        try {
            $raw = $this->getPrioritiesRaw();

            $list = $raw['data'] ?? $raw['Data']
                ?? $raw['items'] ?? $raw['Items']
                ?? $raw['value'] ?? $raw['Value']
                ?? $raw['priorities'] ?? $raw['Priorities']
                ?? $raw['result'] ?? $raw['Result']
                ?? $raw['results'] ?? $raw['Results']
                ?? $raw;

            if (! is_array($list)) {
                $list = [];
            }

            if (! empty($list) && array_keys($list) !== range(0, count($list) - 1)) {
                $list = [$list];
            }

            $map = [];
            $names = [];
            $items = [];
            foreach ($list as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $id = $p['id'] ?? $p['Id'] ?? $p['priorityId'] ?? $p['PriorityId'] ?? null;
                $name = $p['name'] ?? $p['Name'] ?? $p['priorityName'] ?? $p['PriorityName'] ?? null;
                if ($id !== null && $name !== null) {
                    $id = (int) $id;
                    $name = (string) trim($name);
                    if ($name !== '') {
                        $map[$name] = $id;
                        $names[] = $name;
                        $items[] = ['id' => $id, 'name' => $name];
                    }
                }
            }

            return ['map' => $map, 'names' => $names, 'items' => $items];
        } catch (\Throwable $e) {
            Log::warning('Task catalog priorities failed', ['message' => $e->getMessage()]);

            return ['map' => [], 'names' => [], 'items' => []];
        }
    }
}
