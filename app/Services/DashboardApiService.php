<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Session;

/**
 * Typed wrapper around the v1 dashboard API.
 *
 * Replaces the legacy /api/Dashboard/* endpoints. Resolves the requester from the
 * bearer token, so callers no longer pass `?requesterId=`.
 */
class DashboardApiService
{
    public function __construct(private CsharpApiService $api) {}

    /**
     * GET /api/v1/dashboard/summary
     *
     * @return array{
     *   role: string,
     *   myCounts: array{projects:int, tasks:int, forReview:int, completed:int},
     *   adminCounts: ?array{totalUsers:int, totalProjects:int, overdueTasks:int, deactivatedUsers:int},
     * }
     */
    public function getSummary(): array
    {
        $default = [
            'role' => '',
            'myCounts' => ['projects' => 0, 'tasks' => 0, 'forReview' => 0, 'completed' => 0],
            'adminCounts' => null,
        ];

        try {
            $raw = $this->api->get('/api/v1/dashboard/summary', ['_no_cache' => 1]);
        } catch (RequestException $e) {
            $this->forgetSessionOnAuthError($e);
            return $default;
        } catch (\Throwable) {
            return $default;
        }

        if (!is_array($raw)) {
            return $default;
        }

        $myCounts = $raw['myCounts'] ?? $raw['MyCounts'] ?? [];
        $adminCounts = $raw['adminCounts'] ?? $raw['AdminCounts'] ?? null;

        return [
            'role' => (string) ($raw['role'] ?? $raw['Role'] ?? ''),
            'myCounts' => [
                'projects' => (int) ($myCounts['projects'] ?? $myCounts['Projects'] ?? 0),
                'tasks' => (int) ($myCounts['tasks'] ?? $myCounts['Tasks'] ?? 0),
                'forReview' => (int) ($myCounts['forReview'] ?? $myCounts['ForReview'] ?? 0),
                'completed' => (int) ($myCounts['completed'] ?? $myCounts['Completed'] ?? 0),
            ],
            'adminCounts' => is_array($adminCounts) ? [
                'totalUsers' => (int) ($adminCounts['totalUsers'] ?? $adminCounts['TotalUsers'] ?? 0),
                'totalProjects' => (int) ($adminCounts['totalProjects'] ?? $adminCounts['TotalProjects'] ?? 0),
                'overdueTasks' => (int) ($adminCounts['overdueTasks'] ?? $adminCounts['OverdueTasks'] ?? 0),
                'deactivatedUsers' => (int) ($adminCounts['deactivatedUsers'] ?? $adminCounts['DeactivatedUsers'] ?? 0),
            ] : null,
        ];
    }

    /**
     * GET /api/v1/dashboard/projects
     *
     * @return list<array<string, mixed>>
     */
    public function getMyProjects(): array
    {
        try {
            $raw = $this->api->get('/api/v1/dashboard/projects', ['_no_cache' => 1]);
        } catch (RequestException $e) {
            $this->forgetSessionOnAuthError($e);
            return [];
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($raw)) {
            return [];
        }

        if (isset($raw['items']) && is_array($raw['items'])) {
            return array_values($raw['items']);
        }

        return array_values($raw);
    }

    /**
     * GET /api/v1/dashboard/calendar?from=&to=
     *
     * Lightweight calendar projection — flat list of tasks with due dates only.
     *
     * @return list<array<string, mixed>>
     */
    public function getCalendarTasks(?string $from = null, ?string $to = null): array
    {
        $query = ['_no_cache' => 1];
        if ($from !== null && $from !== '') $query['from'] = $from;
        if ($to !== null && $to !== '') $query['to'] = $to;

        try {
            $raw = $this->api->get('/api/v1/dashboard/calendar', $query);
        } catch (RequestException $e) {
            $this->forgetSessionOnAuthError($e);
            return [];
        } catch (\Throwable) {
            return [];
        }

        return is_array($raw) ? array_values($raw) : [];
    }

    /**
     * GET /api/v1/dashboard/projects/{id}/task-summary
     *
     * @return array{totalTasks:int, completionPercentage:float, breakdown:list<array{statusId:int,statusName:string,count:int,percentage:float}>}
     */
    public function getProjectTaskSummary(int $projectId): array
    {
        $default = ['totalTasks' => 0, 'completionPercentage' => 0.0, 'breakdown' => []];

        try {
            $raw = $this->api->get("/api/v1/dashboard/projects/{$projectId}/task-summary", ['_no_cache' => 1]);
        } catch (RequestException $e) {
            $this->forgetSessionOnAuthError($e);
            return $default;
        } catch (\Throwable) {
            return $default;
        }

        if (!is_array($raw)) {
            return $default;
        }

        $breakdownRaw = $raw['breakdown'] ?? $raw['Breakdown'] ?? [];
        $breakdown = [];
        foreach ((array) $breakdownRaw as $row) {
            if (!is_array($row)) continue;
            $breakdown[] = [
                'statusId' => (int) ($row['statusId'] ?? $row['StatusId'] ?? 0),
                'statusName' => (string) ($row['statusName'] ?? $row['StatusName'] ?? ''),
                'count' => (int) ($row['count'] ?? $row['Count'] ?? 0),
                'percentage' => (float) ($row['percentage'] ?? $row['Percentage'] ?? 0),
            ];
        }

        return [
            'totalTasks' => (int) ($raw['totalTasks'] ?? $raw['TotalTasks'] ?? 0),
            'completionPercentage' => (float) ($raw['completionPercentage'] ?? $raw['CompletionPercentage'] ?? 0),
            'breakdown' => $breakdown,
        ];
    }

    private function forgetSessionOnAuthError(RequestException $e): void
    {
        if (in_array($e->response?->status(), [401, 403], true)) {
            Session::forget(['api_token', 'user', 'expires_in']);
        }
    }
}
