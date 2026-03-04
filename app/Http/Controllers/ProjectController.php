<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class ProjectController extends Controller
{
    public function __construct(protected CsharpApiService $api) {}

    public function index()
    {
        $user = Session::get('user', []);
        $accountId = $user['id'] ?? $user['Id'] ?? null;

        if (!$accountId) {
            return view('projects', ['projects' => []]);
        }

        $response = $this->api->get("/api/Project/GetMyProjects/{$accountId}");
        $projects = $this->normalizeProjects($response);

        return view('projects', ['projects' => $projects]);
    }

    public function show($id)
    {
        $project = $this->api->get("/api/Project/GetProjectById/{$id}");
        return view('project', ['project' => $project]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $this->api->post('/api/Project/CreateProject', [
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('Projects');
    }

    private function normalizeProjects($response): array
    {
        if (!is_array($response)) {
            return [];
        }

        // Some APIs return: [ {..}, {..} ]
        if (isset($response[0]) && is_array($response[0])) {
            return $response;
        }

        // Some APIs wrap results: { data: [...] } or { projects: [...] }
        foreach (['data', 'projects', 'items', 'value', 'result'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return [];
    }
}