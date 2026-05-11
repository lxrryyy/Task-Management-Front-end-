<?php

namespace App\Services;

use Illuminate\Http\Client\Response as HttpResponse;

/**
 * Typed wrapper around the v1 audit-log API.
 *
 * Replaces the legacy /api/AuditLog/* endpoints (8 routes) with two:
 *   GET /api/v1/audit-logs?kind=&...
 *   GET /api/v1/audit-logs/export?kind=&format=&...
 *
 * Authorization is admin-only and resolved server-side from the bearer token —
 * callers no longer pass `requesterId`.
 */
class AuditLogApiService
{
    public const KIND_ALL = 'All';
    public const KIND_ACTION = 'Action';
    public const KIND_LOGIN_LOGOUT = 'LoginLogout';

    public const FORMAT_EXCEL = 'Excel';
    public const FORMAT_PDF = 'Pdf';

    public function __construct(private CsharpApiService $api) {}

    /**
     * GET /api/v1/audit-logs
     *
     * @param  array{
     *   userId?: int|null,
     *   taskId?: int|null,
     *   projectId?: int|null,
     *   action?: string|null,
     *   accountName?: string|null,
     *   accountEmail?: string|null,
     *   from?: string|null,
     *   to?: string|null,
     * }  $filter
     * @return array{
     *   page:int, pageSize:int, totalCount:int, totalPages:int,
     *   items: list<array<string, mixed>>
     * }
     */
    public function list(string $kind = self::KIND_ALL, array $filter = [], int $page = 1, int $pageSize = 50): array
    {
        $default = ['page' => $page, 'pageSize' => $pageSize, 'totalCount' => 0, 'totalPages' => 0, 'items' => []];

        $query = $this->buildQuery($filter);
        $query['kind'] = $kind;
        $query['page'] = max(1, $page);
        $query['pageSize'] = max(1, $pageSize);
        $query['_no_cache'] = 1;

        try {
            $raw = $this->api->get('/api/v1/audit-logs', $query);
        } catch (\Throwable) {
            return $default;
        }

        if (!is_array($raw)) {
            return $default;
        }

        return [
            'page' => (int) ($raw['page'] ?? $raw['Page'] ?? $page),
            'pageSize' => (int) ($raw['pageSize'] ?? $raw['PageSize'] ?? $pageSize),
            'totalCount' => (int) ($raw['totalCount'] ?? $raw['TotalCount'] ?? 0),
            'totalPages' => (int) ($raw['totalPages'] ?? $raw['TotalPages'] ?? 0),
            'items' => array_values((array) ($raw['items'] ?? $raw['Items'] ?? [])),
        ];
    }

    /**
     * Streaming export. Returns the raw HTTP response so the caller can pass through
     * the body + Content-Type / Content-Disposition headers to the browser.
     *
     * @param  array<string, mixed>  $filter  Same shape as list().
     */
    public function export(string $kind, string $format, array $filter = []): HttpResponse
    {
        $query = $this->buildQuery($filter);
        $query['kind'] = $kind;
        $query['format'] = $format;

        return $this->api->rawGet('/api/v1/audit-logs/export', $query);
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<string, scalar>
     */
    private function buildQuery(array $filter): array
    {
        $query = [];
        foreach (['userId', 'taskId', 'projectId'] as $intKey) {
            $value = $filter[$intKey] ?? null;
            if ($value !== null && $value !== '' && (int) $value > 0) {
                $query[$intKey] = (int) $value;
            }
        }
        foreach (['action', 'accountName', 'accountEmail', 'from', 'to'] as $strKey) {
            $value = $filter[$strKey] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                $query[$strKey] = (string) $value;
            }
        }
        return $query;
    }
}
