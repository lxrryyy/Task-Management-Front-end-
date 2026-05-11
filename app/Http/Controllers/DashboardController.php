<?php

namespace App\Http\Controllers;

use App\Services\DashboardApiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Session;

class DashboardController extends Controller
{
    public function __construct(protected DashboardApiService $dashboardApi) {}

    public function index()
    {
        try {
            $user = Session::get('user', ['email' => 'User']);
            return view('dashboard', ['user' => $user]);
        } catch (RequestException) {
            Session::forget(['api_token', 'user']);
            return redirect()
                ->route('login')
                ->withErrors(['session' => 'Session expired. Please log in again.']);
        } catch (\Exception) {
            return redirect()
                ->route('login')
                ->withErrors(['session' => 'An error occurred. Please log in again.']);
        }
    }

    /**
     * Project + task tree for the dashboard landing page.
     * Endpoint: GET /api/v1/dashboard/projects (requester resolved from bearer token).
     *
     * Note: $accountId is now ignored (kept for callsite compatibility) — the v1 endpoint
     * resolves the requester from the bearer token.
     *
     * @return list<array<string, mixed>>
     */
    public function getMyProjectsAndTasks(int $accountId = 0): array
    {
        return $this->dashboardApi->getMyProjects();
    }

    /**
     * Pie chart data for a single project.
     * Endpoint: GET /api/v1/dashboard/projects/{id}/task-summary
     *
     * @return array{totalTasks:int, breakdown: list<array{statusName:string, count:int, percentage:float}>}
     */
    public function getProjectTaskSummary(int $projectId, int $requesterId = 0): array
    {
        $raw = $this->dashboardApi->getProjectTaskSummary($projectId);

        $breakdown = [];
        foreach ($raw['breakdown'] as $row) {
            $breakdown[] = [
                'statusName' => (string) $row['statusName'],
                'count' => (int) $row['count'],
                'percentage' => (float) $row['percentage'],
            ];
        }

        return [
            'totalTasks' => $raw['totalTasks'],
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Combined summary cards (admin + per-user counts) in a single round-trip.
     * Endpoint: GET /api/v1/dashboard/summary
     *
     * @return array<string, mixed>
     */
    public function getDashboardSummary(): array
    {
        return $this->dashboardApi->getSummary();
    }

    /**
     * Admin-only stat cards. Backwards-compatible shim over the new combined summary endpoint —
     * existing callers expect a flat `{ totalUsers, totalProjects, overdueTasks, deactivatedUsers }`.
     *
     * @return array<string, int>
     */
    public function getDashboardAdminStats(int $requesterId = 0): array
    {
        $summary = $this->dashboardApi->getSummary();
        $admin = $summary['adminCounts'];
        if (!is_array($admin)) {
            return [];
        }
        return [
            'totalUsers' => (int) $admin['totalUsers'],
            'totalProjects' => (int) $admin['totalProjects'],
            'overdueTasks' => (int) $admin['overdueTasks'],
            'deactivatedUsers' => (int) $admin['deactivatedUsers'],
        ];
    }
}
