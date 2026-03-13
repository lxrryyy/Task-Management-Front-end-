<?php

namespace App\Livewire;

use Livewire\Component;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Session;

class Calendar extends Component
{
    public array $calendarTasks = [];

    public function mount(): void
    {
        $user = Session::get('user', null);
        $accountId = (int) ($user['id'] ?? $user['Id'] ?? 0);

        if ($accountId <= 0) {
            return;
        }

        /** @var DashboardController $controller */
        $controller = app(DashboardController::class);
        $projects = $controller->getMyProjectsAndTasks($accountId);

        $priorityColorMap = [
            'Urgent'    => 'red',
            'Important' => 'pink',
            'Medium'    => 'blue',
            'Low'       => 'gray',
        ];

        // Build account name map for assignee initials
        $accountMap = [];
        try {
            $accountsRaw = app(\App\Services\CsharpApiService::class)->get('/api/Account/GetAllUserRoleAccount');
            $list = is_array($accountsRaw) ? ($accountsRaw['data'] ?? $accountsRaw['accounts'] ?? $accountsRaw) : [];
            foreach ((array) $list as $acc) {
                $id   = (int) ($acc['id'] ?? $acc['Id'] ?? 0);
                $name = $acc['name'] ?? $acc['Name'] ?? $acc['fullName'] ?? trim(($acc['firstName'] ?? '') . ' ' . ($acc['lastName'] ?? '')) ?: null;
                if ($id > 0 && $name) $accountMap[$id] = $name;
            }
        } catch (\Throwable) {}

        $tasks = [];
        foreach ((array) $projects as $project) {
            $projectName = $project['name'] ?? $project['Name'] ?? $project['title'] ?? '';
            $projectId   = (int) ($project['id'] ?? $project['Id'] ?? 0);
            foreach ((array) ($project['tasks'] ?? $project['Tasks'] ?? []) as $task) {
                $dueRaw = $task['dueDate'] ?? $task['dueAt'] ?? null;
                if (!$dueRaw) continue;

                $priority     = $task['priorityName'] ?? $task['priority'] ?? 'Medium';
                $subtaskCount = (int) ($task['subtaskCount'] ?? $task['childCount'] ?? 0);

                $aids = $task['assigneeIds'] ?? $task['assigneeId'] ?? [];
                if (!is_array($aids)) $aids = $aids ? [$aids] : [];
                $assigneeNames = [];
                foreach ($aids as $aid) {
                    $name = $accountMap[(int)$aid] ?? null;
                    if ($name) $assigneeNames[] = $name;
                }

                $tasks[] = [
                    'id'            => (int) ($task['id'] ?? $task['Id'] ?? 0),
                    'title'         => $task['name'] ?? $task['title'] ?? 'Task',
                    'project'       => $projectName,
                    'projectId'     => $projectId,
                    'dueDate'       => substr($dueRaw, 0, 10),
                    'status'        => $task['statusName'] ?? $task['status'] ?? '',
                    'priority'      => $priority,
                    'color'         => $priorityColorMap[$priority] ?? 'blue',
                    'subtasks'      => $subtaskCount,
                    'assigneeNames' => $assigneeNames,
                ];
            }
        }

        $this->calendarTasks = $tasks;
    }

    public function render()
    {
        return view('livewire.calendar', [
            'calendarTasks' => $this->calendarTasks,
        ]);
    }
}
