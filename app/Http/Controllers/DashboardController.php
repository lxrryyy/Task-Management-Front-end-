<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Client\RequestException;

class DashboardController extends Controller
{
    public function __construct(protected CsharpApiService $api) {}

    public function index()
    {
        try {
            $user = Session::get('user', ['email' => 'User']);
            return view('dashboard', ['user' => $user]);
        } catch (RequestException $e) {
            Session::forget(['api_token', 'user']);
            return redirect()
                ->route('login')
                ->withErrors(['session' => 'Session expired. Please log in again.']);
        } catch (\Exception $e) {
            return redirect()
                ->route('login')
                ->withErrors(['session' => 'An error occurred. Please log in again.']);
        }
    }

    /**
     * Fetch projects and their tasks for the given account from the C# API.
     * Endpoint: GET /api/Dashboard/MyProjectsAndTasks?requesterId={accountId}
     */
    public function getMyProjectsAndTasks(int $accountId): array
    {
        try {
            $response = $this->api->get('/api/Dashboard/MyProjectsAndTasks', [
                'requesterId' => $accountId,
            ]);

            return is_array($response) ? $response : [];
        } catch (RequestException $e) {
            // If the token is invalid, clear session so middleware will redirect on next request.
            if (in_array($e->response?->status(), [401, 403], true)) {
                Session::forget(['api_token', 'user', 'expires_in']);
            }
            return [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Fetch project task summary for the dashboard pie chart.
     * Endpoint: GET /api/Dashboard/ProjectTaskSummary/{projectId}?requesterId={id}
     * Response: { projectId, projectName, totalTasks, completionPercentage, completed: {count, percentage}, forReview, inProgress, notStarted }
     * Returns normalized: { totalTasks, breakdown: [{ statusName, count, percentage }] }
     */
    public function getProjectTaskSummary(int $projectId, int $requesterId): array
    {
        try {
            $response = $this->api->get("/api/Dashboard/ProjectTaskSummary/{$projectId}", [
                'requesterId' => $requesterId,
            ]);
            if (! is_array($response)) {
                return ['totalTasks' => 0, 'breakdown' => []];
            }
            $totalTasks = (int) ($response['totalTasks'] ?? 0);
            $raw = $response;
            $breakdown = [];
            $map = [
                'completed'   => 'Completed',
                'forReview'   => 'For Review',
                'inProgress'  => 'In Progress',
                'notStarted'  => 'Not Started',
            ];
            foreach ($map as $key => $statusName) {
                $block = $raw[$key] ?? null;
                if (! is_array($block)) {
                    $block = ['count' => 0, 'percentage' => 0];
                }
                $breakdown[] = [
                    'statusName' => $statusName,
                    'count'      => (int) ($block['count'] ?? 0),
                    'percentage' => (float) ($block['percentage'] ?? 0),
                ];
            }
            return [
                'totalTasks' => $totalTasks,
                'breakdown'  => $breakdown,
            ];
        } catch (RequestException $e) {
            if (in_array($e->response?->status(), [401, 403], true)) {
                Session::forget(['api_token', 'user', 'expires_in']);
            }
            return ['totalTasks' => 0, 'breakdown' => []];
        } catch (\Throwable $e) {
            return ['totalTasks' => 0, 'breakdown' => []];
        }
    }
}
