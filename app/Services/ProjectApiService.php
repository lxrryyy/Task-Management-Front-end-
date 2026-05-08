<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ProjectApiService
{
    /**
     * Per-request memoization of `find()` results, keyed by project id.
     * The service is registered as a Laravel singleton (per-request lifecycle), so this
     * cache is naturally scoped to the current HTTP request and reset on the next one.
     * Mutating methods (update/delete/restore/create) invalidate the relevant entries.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $findCache = [];

    public function __construct(private CsharpApiService $api) {}

    /**
     * GET /api/v1/projects?includeDeleted=&page=&pageSize=
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageSize: int}
     */
    public function list(bool $includeDeleted = false, int $page = 1, int $pageSize = 50): array
    {
        $raw = $this->api->get('/api/v1/projects', [
            'includeDeleted' => $includeDeleted ? 'true' : 'false',
            'page' => max(1, $page),
            'pageSize' => max(1, min(100, $pageSize)),
            '_no_cache' => 1,
        ]);

        $items = is_array($raw) ? ($raw['items'] ?? $raw['Items'] ?? []) : [];

        return [
            'items' => is_array($items) ? array_values($items) : [],
            'total' => (int) ($raw['total'] ?? $raw['Total'] ?? 0),
            'page' => (int) ($raw['page'] ?? $raw['Page'] ?? $page),
            'pageSize' => (int) ($raw['pageSize'] ?? $raw['PageSize'] ?? $pageSize),
        ];
    }

    /**
     * Convenience: walk pages and return all items (matches the legacy "fetch everything" expectation
     * used by views that don't yet paginate).
     *
     * @return list<array<string, mixed>>
     */
    public function listAll(bool $includeDeleted = false, int $pageSize = 100): array
    {
        $items = [];
        $page = 1;
        do {
            $result = $this->list($includeDeleted, $page, $pageSize);
            foreach ($result['items'] as $item) {
                $items[] = $item;
            }
            $fetched = ($page - 1) * $pageSize + count($result['items']);
            $page++;
        } while ($fetched < $result['total'] && count($result['items']) > 0 && $page < 1000);

        return $items;
    }

    /**
     * GET /api/v1/projects/{id}
     *
     * Memoized within the current request — multiple callsites (e.g. enrichment from
     * separate Livewire components on the same page) only hit the API once per id.
     *
     * @return array<string, mixed>
     */
    public function find(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }
        if (array_key_exists($projectId, $this->findCache)) {
            return $this->findCache[$projectId];
        }
        try {
            $raw = $this->api->get("/api/v1/projects/{$projectId}", ['_no_cache' => 1]);
            $result = is_array($raw) ? $raw : [];
        } catch (\Throwable) {
            $result = [];
        }
        return $this->findCache[$projectId] = $result;
    }

    /**
     * Drop the memoized find() entry for a project. Call after a mutation so the next
     * find() in the same request reflects the latest server state.
     */
    public function forgetFind(int $projectId): void
    {
        unset($this->findCache[$projectId]);
    }

    /**
     * POST /api/v1/projects
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->api->post('/api/v1/projects', $this->normalizeCreatePayload($payload));
    }

    /**
     * PATCH /api/v1/projects/{id}
     *
     * @param  array<string, mixed>  $payload
     */
    public function update(int $projectId, array $payload): void
    {
        $this->api->patch("/api/v1/projects/{$projectId}", $this->normalizeUpdatePayload($payload));
        $this->forgetFind($projectId);
    }

    /** DELETE /api/v1/projects/{id} */
    public function delete(int $projectId): void
    {
        $this->api->delete("/api/v1/projects/{$projectId}");
        $this->forgetFind($projectId);
    }

    /**
     * PATCH /api/v1/projects/{id}/restore
     *
     * @return array{restoredCount: int}
     */
    public function restore(int $projectId): array
    {
        $raw = $this->api->patch("/api/v1/projects/{$projectId}/restore");
        $this->forgetFind($projectId);
        return [
            'restoredCount' => (int) ($raw['restoredCount'] ?? $raw['RestoredCount'] ?? 0),
        ];
    }

    /**
     * GET /api/v1/projects/catalog/statuses
     *
     * Returns the same shape the legacy ProjectController::getStatuses() returned so
     * existing callers (Livewire components, blade includes) keep working unchanged.
     * Cached briefly because statuses change rarely.
     *
     * @return array{
     *     items: list<array{id: int, name: string}>,
     *     names: list<string>,
     *     map: array<string, int>,
     *     mapById: array<int, string>
     * }
     */
    public function getStatusesFormatted(): array
    {
        $raw = $this->getStatusesRaw();

        $items = [];
        $names = [];
        $map = [];
        $mapById = [];
        foreach ($raw as $s) {
            $id = (int) ($s['id'] ?? $s['Id'] ?? 0);
            $name = trim((string) ($s['name'] ?? $s['Name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $items[] = ['id' => $id, 'name' => $name];
            $names[] = $name;
            $map[$name] = $id;
            $mapById[$id] = $name;
        }

        return ['items' => $items, 'names' => $names, 'map' => $map, 'mapById' => $mapById];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getStatusesRaw(): array
    {
        return Cache::remember('v1.projects.statuses', now()->addMinutes(5), function () {
            try {
                $raw = $this->api->get('/api/v1/projects/catalog/statuses');
                return is_array($raw) ? array_values(array_filter($raw, 'is_array')) : [];
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /**
     * Map legacy create-form payload onto the v1 contract.
     * Accepted legacy keys: name, description, projectManagerId, scrumMasterId,
     * memberIds, isAlsoScrumMaster, startDate, endDate.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeCreatePayload(array $payload): array
    {
        $clean = [
            'name' => isset($payload['name']) ? (string) $payload['name'] : '',
            'description' => isset($payload['description']) ? (string) $payload['description'] : null,
            'projectManagerId' => isset($payload['projectManagerId']) ? (int) $payload['projectManagerId'] : null,
            'scrumMasterId' => isset($payload['scrumMasterId']) ? (int) $payload['scrumMasterId'] : null,
            'isAlsoScrumMaster' => (bool) ($payload['isAlsoScrumMaster'] ?? false),
            'memberIds' => $this->intIdList($payload['memberIds'] ?? []),
            'startDate' => $payload['startDate'] ?? null,
            'endDate' => $payload['endDate'] ?? null,
        ];
        return $clean;
    }

    /**
     * Map legacy update payload onto the v1 contract.
     *
     * The legacy form uses `status` (name) and `assigneeIds`; v1 uses `statusId` and `memberIds`.
     * `scrumMasterIdProvided` is set only when the caller actually included the key — preserving
     * the tri-state semantics (omit = no change, null = clear, value = set).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeUpdatePayload(array $payload): array
    {
        $out = [];

        if (array_key_exists('name', $payload)) {
            $out['name'] = $payload['name'] !== null ? (string) $payload['name'] : null;
        }
        if (array_key_exists('description', $payload)) {
            $out['description'] = $payload['description'] !== null ? (string) $payload['description'] : null;
        }
        if (array_key_exists('startDate', $payload)) {
            $out['startDate'] = $payload['startDate'];
        }
        if (array_key_exists('endDate', $payload)) {
            $out['endDate'] = $payload['endDate'];
        }
        if (array_key_exists('projectManagerId', $payload)) {
            $out['projectManagerId'] = $payload['projectManagerId'] !== null
                ? (int) $payload['projectManagerId']
                : null;
        }

        // Resolve status/statusId.
        if (array_key_exists('statusId', $payload) && $payload['statusId'] !== null) {
            $out['statusId'] = (int) $payload['statusId'];
        } elseif (array_key_exists('status', $payload) && $payload['status'] !== null) {
            $statusName = trim((string) $payload['status']);
            if ($statusName !== '') {
                $statusId = $this->getStatusesFormatted()['map'][$statusName] ?? null;
                if ($statusId !== null) {
                    $out['statusId'] = (int) $statusId;
                }
            }
        }

        // assigneeIds (legacy) → memberIds (v1).
        if (array_key_exists('memberIds', $payload)) {
            $out['memberIds'] = $this->intIdList($payload['memberIds']);
        } elseif (array_key_exists('assigneeIds', $payload)) {
            $out['memberIds'] = $this->intIdList($payload['assigneeIds']);
        }

        // Scrum master tri-state.
        if (array_key_exists('scrumMasterId', $payload)) {
            $smValue = $payload['scrumMasterId'];
            $out['scrumMasterId'] = $smValue !== null && $smValue !== '' ? (int) $smValue : null;
            $out['scrumMasterIdProvided'] = true;
        }

        return $out;
    }

    /**
     * @param  mixed  $ids
     * @return list<int>
     */
    private function intIdList(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }
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
