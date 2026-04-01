<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Http\Client\RequestException;

class UserManagement extends Component
{
    public string $search = '';
    public array $users = [];
    public ?array $filteredUsers = null;

    public bool $showUserDetailModal = false;
    public ?array $selectedUser = null;

    public ?string $apiError = null;

    // Search filter toggles
    public bool $filterUser = true;
    public bool $filterStatus = true;
    public bool $filterSpecialization = true;

    // Add user modal state
    public string $newFirstName = '';
    public string $newLastName = '';
    public string $newEmail = '';
    public string $newTemporaryPassword = '';
    public string $newSpecialization = '';
    public string $newRole = 'User';

    public bool $creatingAccount = false;
    public ?string $createAccountError = null;
    public ?string $createAccountSuccess = null;
    public array $createAccountErrors = [];

    public bool $showEditUserModal = false;
    public int $editUserId = 0;
    public string $editName = '';
    public string $editEmail = '';
    public string $editSpecialization = '';
    public string $editRole = 'User';
    public bool $editIsActive = true;
    public ?string $editUserError = null;
    public ?string $editUserSuccess = null;

    public function mount(): void
    {
        $this->reloadUsersFromApi();
    }

    public function generateTemporaryPassword(): void
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $this->newTemporaryPassword = $password;
    }

    public function createAccount(): void
    {
        $this->createAccountError = null;
        $this->createAccountSuccess = null;
        $this->createAccountErrors = [];

        $first = trim($this->newFirstName);
        $last = trim($this->newLastName);
        $email = trim($this->newEmail);
        $tempPassword = (string) $this->newTemporaryPassword;
        $specRaw = trim($this->newSpecialization);
        $spec = $specRaw !== '' ? $specRaw : null;

        $fullName = trim(($first !== '' ? $first : '') . ' ' . ($last !== '' ? $last : ''));
        $fullName = trim($fullName);

        if ($fullName === '' || $email === '' || $tempPassword === '') {
            $this->createAccountError = 'Please fill First Name, Last Name, Email, and Temporary Password.';
            return;
        }

        $this->creatingAccount = true;
        try {
            $user = \Illuminate\Support\Facades\Session::get('user', []);
            $adminId = (int) ($user['id'] ?? $user['Id'] ?? $user['accountId'] ?? $user['AccountId'] ?? 0);
            // Swagger shows this endpoint requires `adminId` as a query parameter.
            if ($adminId <= 0) {
                $this->creatingAccount = false;
                $this->createAccountError = 'Admin ID is required to create an account. Please log out and log in again as an admin.';
                return;
            }
            $adminQuery = '?adminid=' . $adminId;

            $payload = [
                // Match Swagger request body exactly.
                'name' => $fullName,
                'email' => $email,
                'password' => $tempPassword,
                'passwordHash' => $tempPassword,
                'specialization' => $spec,
                'role' => trim((string) $this->newRole) !== '' ? $this->newRole : 'User',
                'isActive' => true,
            ];

            app(\App\Services\CsharpApiService::class)->post('/api/Account/CreateAccount' . $adminQuery, $payload);

            $this->createAccountSuccess = 'User created successfully.';
            $this->creatingAccount = false;

            // Reset form fields
            $this->newFirstName = '';
            $this->newLastName = '';
            $this->newEmail = '';
            $this->newTemporaryPassword = '';
            $this->newSpecialization = '';
            $this->newRole = 'User';

            // Refresh list
            $this->reloadUsersFromApi();

            // Close the dialog on success so the rest of the page is clickable.
            $this->dispatch('closeAddUserModal');
        } catch (RequestException $e) {
            $this->creatingAccount = false;

            $api = app(\App\Services\CsharpApiService::class);
            $fieldErrors = $api->extractFieldErrors($e->response);

            // Flatten to a simple array of readable messages.
            $flat = [];
            foreach ($fieldErrors as $msgs) {
                foreach ((array) $msgs as $m) {
                    if (is_string($m) && trim($m) !== '') $flat[] = $m;
                }
            }

            $this->createAccountErrors = array_values(array_unique($flat));
            $this->createAccountError = !empty($this->createAccountErrors)
                ? null
                : 'Failed to create user. Please try again.';
        } catch (\Throwable $e) {
            $this->creatingAccount = false;
            $this->createAccountError = $e->getMessage() ?: 'Failed to create user. Please try again.';
        }
    }

    private function reloadUsersFromApi(): void
    {
        $this->apiError = null;

        try {
            $raw = app(\App\Services\CsharpApiService::class)->get('/api/Account/GetAllUsersWithStats');
        } catch (\Throwable $e) {
            $this->apiError = 'Failed to load users from API.';
            return;
        }

        // Normalize common shapes: { data: [...] }, { users: [...] }, [ ... ]
        $list = [];
        if (is_array($raw)) {
            if (isset($raw['data']) && is_array($raw['data'])) {
                $list = $raw['data'];
            } elseif (isset($raw['users']) && is_array($raw['users'])) {
                $list = $raw['users'];
            } elseif (isset($raw['items']) && is_array($raw['items'])) {
                $list = $raw['items'];
            } elseif (isset($raw[0]) && is_array($raw[0])) {
                $list = $raw;
            }
        }

        if (!is_array($list)) $list = [];

        $this->users = array_values(array_map(function ($u) {
            $u = is_array($u) ? $u : [];
            return $this->normalizeUser($u);
        }, $list));
    }

    private function normalizeUser(array $u): array
    {
        $id = (int) ($u['id'] ?? $u['Id'] ?? $u['accountId'] ?? $u['AccountId'] ?? 0);

        $first = trim((string) ($u['firstName'] ?? $u['FirstName'] ?? $u['First'] ?? ''));
        $last  = trim((string) ($u['lastName'] ?? $u['LastName'] ?? $u['Last'] ?? ''));

        $fullName = trim((string) ($u['fullName'] ?? $u['FullName'] ?? $u['name'] ?? $u['Name'] ?? ''));
        if ($fullName === '' && ($first !== '' || $last !== '')) {
            $fullName = trim($first . ' ' . $last);
        }
        if ($fullName === '') {
            $fullName = trim((string) ($u['userName'] ?? $u['username'] ?? $u['UserName'] ?? ''));
        }

        $email = trim((string) ($u['email'] ?? $u['Email'] ?? ''));

        $specialization = trim((string) (
            $u['specialization'] ?? $u['Specialization']
            ?? $u['bio'] ?? $u['Bio']
            ?? $u['specialisations'] ?? $u['Specializations'] ?? ''
        ));

        // API response uses `projectCount` and `activeTaskCount`
        $projectsVal = $u['projectsCount']
            ?? $u['ProjectsCount']
            ?? $u['projectCount']
            ?? $u['ProjectCount']
            ?? $u['projects']
            ?? $u['Projects']
            ?? 0;
        $tasksVal = $u['activeTaskCount']
            ?? $u['ActiveTaskCount']
            ?? $u['tasksCount']
            ?? $u['TasksCount']
            ?? $u['taskCount']
            ?? $u['TaskCount']
            ?? $u['tasks']
            ?? $u['Tasks']
            ?? 0;

        $projectsCount = is_array($projectsVal) ? count($projectsVal) : (int) $projectsVal;
        $tasksCount    = is_array($tasksVal) ? count($tasksVal) : (int) $tasksVal;

        $status = trim((string) (
            $u['status'] ?? $u['Status'] ?? $u['statusName'] ?? $u['StatusName']
            ?? $u['roleName'] ?? $u['RoleName'] ?? $u['role'] ?? $u['Role'] ?? ''
        ));

        return [
            'id' => $id,
            'name' => $fullName ?: '—',
            'email' => $email ?: '—',
            'specialization' => $specialization ?: '—',
            'projectsCount' => max(0, $projectsCount),
            'tasksCount' => max(0, $tasksCount),
            'status' => $status ?: '—',
            'isActive' => (bool) ($u['isActive'] ?? $u['IsActive'] ?? true),
            'raw' => $u,
        ];
    }

    public function openUserDetail(int $userId): void
    {
        $user = collect($this->users)->first(fn ($u) => (int) ($u['id'] ?? 0) === $userId);
        if (! $user) return;

        $this->selectedUser = $user;
        $this->showUserDetailModal = true;
    }

    public function closeUserDetail(): void
    {
        $this->showUserDetailModal = false;
        $this->selectedUser = null;
    }

    public function render()
    {
        $query = mb_strtolower(trim($this->search));

        if ($query === '') {
            $this->filteredUsers = $this->users;
        } else {
            $this->filteredUsers = array_values(array_filter($this->users, function ($u) use ($query) {
                // Build the searchable fields based on active filters.
                $parts = [];
                // If no filters are enabled, default to all.
                $filterUser = $this->filterUser;
                $filterStatus = $this->filterStatus;
                $filterSpec = $this->filterSpecialization;
                if (! $filterUser && ! $filterStatus && ! $filterSpec) {
                    $filterUser = $filterStatus = $filterSpec = true;
                }

                if ($filterUser) {
                    $parts[] = mb_strtolower((string) ($u['name'] ?? ''));
                }
                if ($filterSpec) {
                    $parts[] = mb_strtolower((string) ($u['specialization'] ?? ''));
                }
                if ($filterStatus) {
                    $parts[] = mb_strtolower((string) ($u['status'] ?? ''));
                }

                $haystack = implode(' ', $parts);
                return str_contains($haystack, $query);
            }));
        }

        return view('livewire.user-management', [
            'filteredUsers' => $this->filteredUsers ?? [],
            'apiError' => $this->apiError,
        ]);
    }

    public function resetFilters(): void
    {
        $this->filterUser = true;
        $this->filterStatus = true;
        $this->filterSpecialization = true;
        $this->search = '';
    }

    public function toggleUserStatus(int $userId, bool $isActive): void
    {
    }

    public function editUser(int $userId): void
    {
        $user = collect($this->users)->first(fn ($u) => (int) ($u['id'] ?? 0) === $userId);
        if (!$user) return;

        $this->editUserId = $userId;
        $this->editName = $user['name'] === '—' ? '' : $user['name'];
        $this->editEmail = $user['email'] === '—' ? '' : $user['email'];
        $this->editSpecialization = $user['specialization'] === '—' ? '' : $user['specialization'];
        $this->editRole = $user['raw']['role'] ?? $user['raw']['Role'] ?? 'User';
        $this->editIsActive = $user['isActive'];
        $this->editUserError = null;
        $this->editUserSuccess = null;
        $this->showEditUserModal = true;
    }

    public function closeEditUserModal(): void
    {
        $this->showEditUserModal = false;
        $this->editUserId = 0;
        $this->editName = '';
        $this->editEmail = '';
        $this->editSpecialization = '';
        $this->editRole = 'User';
        $this->editIsActive = true;
        $this->editUserError = null;
        $this->editUserSuccess = null;
    }

    public function saveEditUser(): void
    {
        $this->editUserError = null;
        $this->editUserSuccess = null;

        if (trim($this->editName) === '') {
            $this->editUserError = 'Name is required.';
            return;
        }

        try {
            $user = \Illuminate\Support\Facades\Session::get('user', []);
            $editorId = (int) ($user['id'] ?? $user['Id'] ?? 0);

            $payload = [
                'name' => trim($this->editName),
                'role' => $this->editRole,
                'isActive' => $this->editIsActive,
                'specialization' => trim($this->editSpecialization) ?: null,
            ];

            app(\App\Services\CsharpApiService::class)->patch(
                "/api/Account/UpdateAccount/{$this->editUserId}?editorId={$editorId}",
                $payload
            );

            $this->editUserSuccess = 'User updated successfully.';
            $this->reloadUsersFromApi();
            $this->closeEditUserModal();
        } catch (\Throwable $e) {
            $this->editUserError = 'Failed to update user. Please try again.';
        }
    }
}
