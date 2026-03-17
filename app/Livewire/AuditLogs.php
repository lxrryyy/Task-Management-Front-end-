<?php

namespace App\Livewire;

use App\Services\CsharpApiService;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class AuditLogs extends Component
{
    public array $logs = [];
    public array $accounts = [];

    public string $search = '';

    public ?int $filterUserId = null;
    public ?int $filterTaskId = null;
    public string $filterAction = '';
    public ?string $filterFrom = null; // YYYY-MM-DD
    public ?string $filterTo = null;   // YYYY-MM-DD
    public string $filterRole = '';
    public string $filterProject = '';
    public string $filterStatus = '';

    public ?string $loadError = null;

    public function mount(): void
    {
        $this->loadAccounts();
        $this->fetchLogs();
    }

    public function updated(string $name, mixed $value): void
    {
        // Search is client-side (does not change endpoint)
        if ($name === 'search') {
            return;
        }

        // Only re-fetch when it changes the backend endpoint/result set.
        // Role / Project / Status are client-side filters on the fetched logs.
        if (in_array($name, ['filterRole', 'filterProject', 'filterStatus'], true)) {
            return;
        }

        // Any other filter change re-fetches from most specific endpoint
        if (str_starts_with($name, 'filter')) {
            $this->fetchLogs();
        }
    }

    public function clearFilters(): void
    {
        $this->filterUserId = null;
        $this->filterTaskId = null;
        $this->filterAction = '';
        $this->filterFrom = null;
        $this->filterTo = null;
        $this->filterRole = '';
        $this->filterProject = '';
        $this->filterStatus = '';
        $this->fetchLogs();
    }

    private function requesterId(): int
    {
        $user = Session::get('user', []);
        return (int) ($user['id'] ?? $user['Id'] ?? 0);
    }

    private function loadAccounts(): void
    {
        try {
            $raw = app(CsharpApiService::class)->get('/api/Account/GetAllUserRoleAccount');
            $list = is_array($raw) ? ($raw['data'] ?? $raw['accounts'] ?? $raw) : [];
            $this->accounts = array_values(array_filter(array_map(function ($acc) {
                if (!is_array($acc)) return null;
                $id = (int) ($acc['id'] ?? $acc['Id'] ?? 0);
                if ($id <= 0) return null;
                $name = $acc['name'] ?? $acc['Name'] ?? $acc['fullName']
                    ?? trim(($acc['firstName'] ?? '') . ' ' . ($acc['lastName'] ?? ''))
                    ?: ('User #' . $id);
                $email = (string) ($acc['email'] ?? $acc['Email'] ?? '');
                $email = trim($email);

                $role = (string) ($acc['role'] ?? $acc['Role'] ?? $acc['roleName'] ?? $acc['RoleName'] ?? '');
                $role = trim($role);

                return [
                    'id' => $id,
                    'name' => (string) $name,
                    'email' => $email,
                    'role' => $role,
                ];
            }, (array) $list)));
        } catch (\Throwable) {
            $this->accounts = [];
        }
    }

    public function fetchLogs(): void
    {
        $this->loadError = null;

        $requesterId = $this->requesterId();
        if ($requesterId <= 0) {
            $this->logs = [];
            return;
        }

        $api = app(CsharpApiService::class);

        try {
            // Default: GetAllLogs (generalization)
            $endpoint = '/api/AuditLog/GetAllLogs';
            $query = ['requesterId' => $requesterId];

            // Filtering (use more specific endpoints)
            $from = $this->filterFrom ? trim($this->filterFrom) : '';
            $to = $this->filterTo ? trim($this->filterTo) : '';
            $action = trim($this->filterAction);
            $taskId = (int) ($this->filterTaskId ?? 0);
            $userId = (int) ($this->filterUserId ?? 0);

            if ($from !== '' || $to !== '') {
                $endpoint = '/api/AuditLog/GetLogsByDateRange';
                if ($from !== '') $query['from'] = $from;
                if ($to !== '')   $query['to'] = $to;
            } elseif ($action !== '') {
                $endpoint = '/api/AuditLog/GetLogsByAction';
                $query['action'] = $action;
            } elseif ($taskId > 0) {
                $endpoint = "/api/AuditLog/GetTaskLogs/{$taskId}";
            } elseif ($userId > 0) {
                $endpoint = "/api/AuditLog/GetUserLogs/{$userId}";
            }

            $raw = $api->get($endpoint, $query);

            // Unwrap common response shapes
            $list = is_array($raw)
                ? ($raw['data'] ?? $raw['logs'] ?? $raw['items'] ?? (isset($raw[0]) ? $raw : []))
                : [];

            $this->logs = array_values(array_filter(array_map(
                fn ($l) => is_array($l) ? $this->normaliseLog($l) : null,
                (array) $list
            )));
        } catch (\Throwable $e) {
            $this->logs = [];
            $this->loadError = 'Failed to load audit logs. Please try again.';
        }
    }

    private function normaliseLog(array $log): array
    {
        $id = (int) ($log['id'] ?? $log['Id'] ?? $log['auditLogId'] ?? $log['AuditLogId'] ?? 0);

        $action = (string) ($log['action'] ?? $log['Action'] ?? $log['activity'] ?? $log['Activity'] ?? '');
        $action = trim($action);

        $accountId = (int) ($log['accountId'] ?? $log['AccountId'] ?? $log['userId'] ?? $log['UserId'] ?? 0);
        $userName = (string) ($log['userName'] ?? $log['UserName'] ?? $log['accountName'] ?? $log['AccountName'] ?? $log['name'] ?? $log['Name'] ?? '');
        $userName = trim($userName);
        $userEmail = (string) ($log['userEmail'] ?? $log['UserEmail'] ?? $log['email'] ?? $log['Email'] ?? '');
        $userEmail = trim($userEmail);
        $userRole = (string) ($log['role'] ?? $log['Role'] ?? $log['roleName'] ?? $log['RoleName'] ?? '');
        $userRole = trim($userRole);

        $taskId = (int) ($log['taskId'] ?? $log['TaskId'] ?? $log['entityId'] ?? $log['EntityId'] ?? 0);
        $entity = (string) ($log['entity'] ?? $log['Entity'] ?? $log['entityType'] ?? $log['EntityType'] ?? '');
        $entity = trim($entity);

        $projectName = (string) (
            $log['projectName'] ?? $log['ProjectName']
            ?? $log['project'] ?? $log['Project']
            ?? $log['projectTitle'] ?? $log['ProjectTitle']
            ?? ''
        );
        $projectName = trim($projectName);

        $status = (string) (
            $log['status'] ?? $log['Status']
            ?? $log['statusName'] ?? $log['StatusName']
            ?? $log['state'] ?? $log['State']
            ?? ''
        );
        $status = trim($status);

        $message = (string) ($log['message'] ?? $log['Message'] ?? $log['description'] ?? $log['Description'] ?? $log['details'] ?? $log['Details'] ?? '');
        $message = trim($message);

        $at = (string) ($log['createdAt'] ?? $log['CreatedAt'] ?? $log['timestamp'] ?? $log['Timestamp'] ?? $log['date'] ?? $log['Date'] ?? '');
        $at = trim($at);

        return [
            'id' => $id,
            'action' => $action,
            'accountId' => $accountId,
            'userName' => $userName,
            'userEmail' => $userEmail,
            'userRole' => $userRole,
            'taskId' => $taskId,
            'projectName' => $projectName,
            'status' => $status,
            'entity' => $entity,
            'message' => $message,
            'at' => $at,
            'raw' => $log,
        ];
    }

    public function render()
    {
        $q = mb_strtolower(trim($this->search));
        $filtered = $this->logs;

        if ($q !== '') {
            $filtered = array_values(array_filter($this->logs, function ($l) use ($q) {
                $hay = implode(' ', [
                    mb_strtolower((string) ($l['action'] ?? '')),
                    mb_strtolower((string) ($l['userName'] ?? '')),
                    mb_strtolower((string) ($l['message'] ?? '')),
                    (string) ($l['taskId'] ?? ''),
                    (string) ($l['accountId'] ?? ''),
                    mb_strtolower((string) ($l['entity'] ?? '')),
                ]);
                return str_contains($hay, $q);
            }));
        }

        $roleNeedle = mb_strtolower(trim($this->filterRole));
        if ($roleNeedle !== '') {
            $filtered = array_values(array_filter($filtered, function ($l) use ($roleNeedle) {
                $r = mb_strtolower(trim((string) ($l['userRole'] ?? '')));
                return $r === $roleNeedle;
            }));
        }

        $projectNeedle = mb_strtolower(trim($this->filterProject));
        if ($projectNeedle !== '') {
            $filtered = array_values(array_filter($filtered, function ($l) use ($projectNeedle) {
                $p = mb_strtolower(trim((string) ($l['projectName'] ?? '')));
                return $p === $projectNeedle;
            }));
        }

        $statusNeedle = mb_strtolower(trim($this->filterStatus));
        if ($statusNeedle !== '') {
            $filtered = array_values(array_filter($filtered, function ($l) use ($statusNeedle) {
                $s = mb_strtolower(trim((string) ($l['status'] ?? '')));
                return $s === $statusNeedle;
            }));
        }

        // Action list for dropdown
        $actions = [];
        foreach ($this->logs as $l) {
            $a = trim((string) ($l['action'] ?? ''));
            if ($a !== '') $actions[$a] = true;
        }
        $actionOptions = array_keys($actions);
        sort($actionOptions);

        $roles = [];
        foreach ($this->accounts as $a) {
            if (!is_array($a)) continue;
            $r = trim((string) ($a['role'] ?? ''));
            if ($r !== '') $roles[$r] = true;
        }
        foreach ($this->logs as $l) {
            $r = trim((string) ($l['userRole'] ?? ''));
            if ($r !== '') $roles[$r] = true;
        }
        $roleOptions = array_keys($roles);
        sort($roleOptions);

        $projects = [];
        foreach ($this->logs as $l) {
            $p = trim((string) ($l['projectName'] ?? ''));
            if ($p !== '') $projects[$p] = true;
        }
        $projectOptions = array_keys($projects);
        sort($projectOptions);

        $statuses = [];
        foreach ($this->logs as $l) {
            $s = trim((string) ($l['status'] ?? ''));
            if ($s !== '') $statuses[$s] = true;
        }
        $statusOptions = array_keys($statuses);
        sort($statusOptions);

        // Map accountId => details for display fallback
        $accountMap = [];
        foreach ($this->accounts as $a) {
            if (!is_array($a)) continue;
            $id = (int) ($a['id'] ?? 0);
            if ($id <= 0) continue;
            $accountMap[$id] = [
                'name' => (string) ($a['name'] ?? ''),
                'email' => (string) ($a['email'] ?? ''),
                'role' => (string) ($a['role'] ?? ''),
            ];
        }

        return view('livewire.audit-logs', [
            'filteredLogs' => $filtered,
            'accountMap' => $accountMap,
            'actionOptions' => $actionOptions,
            'roleOptions' => $roleOptions,
            'projectOptions' => $projectOptions,
            'statusOptions' => $statusOptions,
        ]);
    }
}
