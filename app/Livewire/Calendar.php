<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\AccountApiService;
use App\Services\DashboardApiService;
use App\Services\StickyNoteApiService;
use Illuminate\Support\Facades\Session;

class Calendar extends Component
{
    public array $calendarTasks = [];
    public array $stickyNotes   = [];

    public function mount(): void
    {
        $user      = Session::get('user', null);
        $accountId = (int) ($user['id'] ?? $user['Id'] ?? 0);

        if ($accountId <= 0) {
            return;
        }

        $this->loadTasks();
        $this->loadNotes();
    }

    /**
     * Pull a flat task projection from /api/v1/dashboard/calendar instead of walking
     * the full /api/v1/dashboard/projects tree. Smaller payload, faster render.
     */
    private function loadTasks(): void
    {
        try {
            $rows = app(DashboardApiService::class)->getCalendarTasks();
        } catch (\Throwable) {
            $this->calendarTasks = [];
            return;
        }

        $priorityColorMap = [
            'Urgent' => 'red',
            'Important' => 'pink',
            'Medium' => 'blue',
            'Low' => 'gray',
        ];

        // Build account profile lookup for assignee avatars/initials
        $accountMap = [];
        try {
            foreach (app(AccountApiService::class)->listAssignableUsers() as $acc) {
                $id = (int) ($acc['id'] ?? $acc['Id'] ?? 0);
                $name = $acc['name'] ?? $acc['Name'] ?? $acc['fullName']
                    ?? trim(($acc['firstName'] ?? '') . ' ' . ($acc['lastName'] ?? ''))
                    ?: null;
                $pic = $acc['profilePicture'] ?? $acc['ProfilePicture'] ?? null;
                $pic = is_string($pic) ? trim($pic) : '';
                if ($id > 0 && $name) {
                    $accountMap[$id] = [
                        'name' => $name,
                        'profilePicture' => $pic !== '' ? $pic : null,
                    ];
                }
            }
        } catch (\Throwable) {
        }

        $tasks = [];
        foreach ($rows as $row) {
            $dueRaw = $row['dueDate'] ?? $row['DueDate'] ?? null;
            if (!$dueRaw) {
                continue;
            }

            $priority = $row['priorityName'] ?? $row['PriorityName'] ?? 'Medium';

            $assigneeIds = $row['assigneeIds'] ?? $row['AssigneeIds'] ?? [];
            $assignees = [];
            foreach ((array) $assigneeIds as $aid) {
                $profile = $accountMap[(int) $aid] ?? null;
                if (is_array($profile) && !empty($profile['name'])) {
                    $assignees[] = [
                        'name' => (string) $profile['name'],
                        'profilePicture' => $profile['profilePicture'] ?? null,
                    ];
                }
            }

            $tasks[] = [
                'id' => (int) ($row['id'] ?? $row['Id'] ?? 0),
                'title' => $row['title'] ?? $row['Title'] ?? 'Task',
                'project' => $row['projectName'] ?? $row['ProjectName'] ?? '',
                'projectId' => (int) ($row['projectId'] ?? $row['ProjectId'] ?? 0),
                'dueDate' => substr((string) $dueRaw, 0, 10),
                'status' => $row['statusName'] ?? $row['StatusName'] ?? '',
                'priority' => $priority,
                'color' => $priorityColorMap[$priority] ?? 'blue',
                'subtasks' => (int) ($row['subtaskCount'] ?? $row['SubtaskCount'] ?? 0),
                'assignees' => $assignees,
            ];
        }

        $this->calendarTasks = $tasks;
    }

    private function loadNotes(): void
    {
        try {
            $list = app(StickyNoteApiService::class)->list();
            $this->stickyNotes = array_values(
                array_map(fn ($n) => $this->normaliseNote((array) $n), $list)
            );
        } catch (\Throwable) {
            $this->stickyNotes = [];
        }
    }

    /**
     * @param  array<string, mixed>  $note
     * @return array<string, mixed>
     */
    private function normaliseNote(array $note): array
    {
        return [
            'id' => (int) ($note['id'] ?? $note['Id'] ?? 0),
            'content' => (string) ($note['content'] ?? $note['Content'] ?? ''),
            'isPinned' => (bool) ($note['isPinned'] ?? $note['IsPinned'] ?? false),
            'createdAt' => (string) ($note['createdAt'] ?? $note['CreatedAt'] ?? now()->toIso8601String()),
            'updatedAt' => (string) ($note['updatedAt'] ?? $note['UpdatedAt'] ?? $note['createdAt'] ?? $note['CreatedAt'] ?? now()->toIso8601String()),
        ];
    }

    public function render()
    {
        return view('livewire.calendar', [
            'calendarTasks' => $this->calendarTasks,
            'stickyNotes' => $this->stickyNotes,
        ]);
    }
}
