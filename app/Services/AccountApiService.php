<?php

namespace App\Services;

use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

/**
 * Typed wrapper around the v1 accounts API. Centralises payload shaping, cache opt-out
 * for sensitive reads, and shape normalisation so callers don't have to chase camel/Pascal
 * casing variants returned by the legacy and v1 endpoints.
 */
class AccountApiService
{
    public function __construct(private CsharpApiService $api) {}

    /**
     * Paginated list of accounts, filterable by role and active flag.
     *
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageSize: int}
     */
    public function list(?string $role = null, ?bool $active = null, int $page = 1, int $pageSize = 100): array
    {
        $query = [
            'page' => max(1, $page),
            'pageSize' => max(1, min(100, $pageSize)),
            '_no_cache' => 1,
        ];
        if ($role !== null && $role !== '') {
            $query['role'] = $role;
        }
        if ($active !== null) {
            $query['active'] = $active ? 'true' : 'false';
        }

        $raw = $this->api->get('/api/v1/accounts', $query);
        $items = is_array($raw) ? ($raw['items'] ?? $raw['Items'] ?? []) : [];

        return [
            'items' => is_array($items) ? array_values($items) : [],
            'total' => (int) ($raw['total'] ?? $raw['Total'] ?? 0),
            'page' => (int) ($raw['page'] ?? $raw['Page'] ?? $page),
            'pageSize' => (int) ($raw['pageSize'] ?? $raw['PageSize'] ?? $pageSize),
        ];
    }

    /**
     * Convenience: walk pages and return all accounts. Mirrors the legacy "fetch everything"
     * expectation used by member pickers, calendars, and audit logs.
     *
     * @return list<array<string, mixed>>
     */
    public function listAll(?string $role = null, ?bool $active = null, int $pageSize = 100): array
    {
        $items = [];
        $page = 1;
        do {
            $result = $this->list($role, $active, $page, $pageSize);
            foreach ($result['items'] as $item) {
                $items[] = $item;
            }
            $fetched = ($page - 1) * $pageSize + count($result['items']);
            $page++;
        } while ($fetched < $result['total'] && count($result['items']) > 0 && $page < 1000);

        return $items;
    }

    /**
     * Active "User"-role accounts, the legacy `GetAllUserRoleAccount` shape used by every
     * member/assignee picker.
     *
     * @return list<array<string, mixed>>
     */
    public function listAssignableUsers(): array
    {
        return $this->listAll(role: 'User', active: true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $accountId): ?array
    {
        if ($accountId <= 0) {
            return null;
        }

        try {
            $raw = $this->api->get("/api/v1/accounts/{$accountId}", ['_no_cache' => 1]);
        } catch (\Throwable) {
            return null;
        }

        return is_array($raw) && $raw !== [] ? $raw : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function me(): ?array
    {
        try {
            $raw = $this->api->get('/api/v1/accounts/me', ['_no_cache' => 1]);
        } catch (\Throwable) {
            return null;
        }

        return is_array($raw) && $raw !== [] ? $raw : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUsersWithStats(): array
    {
        $raw = $this->api->get('/api/v1/accounts/stats/users', ['_no_cache' => 1]);
        if (!is_array($raw)) {
            return [];
        }

        // Backend returns a bare array; tolerate a wrapped { data: [...] } too.
        if (isset($raw['data']) && is_array($raw['data'])) {
            $raw = $raw['data'];
        } elseif (isset($raw['items']) && is_array($raw['items'])) {
            $raw = $raw['items'];
        }

        return is_array($raw) ? array_values($raw) : [];
    }

    public function generatePassword(): ?string
    {
        try {
            $raw = $this->api->get('/api/v1/accounts/utils/generated-password', ['_no_cache' => 1]);
        } catch (\Throwable) {
            return null;
        }

        return is_array($raw) ? ($raw['password'] ?? $raw['Password'] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        return $this->api->post('/api/v1/accounts', $this->normalizeCreatePayload($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(int $accountId, array $payload): array
    {
        return $this->api->patch("/api/v1/accounts/{$accountId}", $this->normalizeUpdatePayload($payload));
    }

    public function deactivate(int $accountId): void
    {
        $this->api->delete("/api/v1/accounts/{$accountId}");
    }

    public function reactivate(int $accountId): void
    {
        $this->api->patch("/api/v1/accounts/{$accountId}/restore");
    }

    /**
     * @return array{profilePicture: ?string}
     */
    public function removeProfilePicture(int $accountId): array
    {
        $raw = $this->api->delete("/api/v1/accounts/{$accountId}/profile-picture");
        return [
            'profilePicture' => is_array($raw) ? ($raw['profilePicture'] ?? $raw['ProfilePicture'] ?? null) : null,
        ];
    }

    /**
     * Multipart upload — the typed wrapper bypasses CsharpApiService::post() because
     * that helper forces application/json. We replicate the headers/timeouts here.
     *
     * @return array{profilePicture: ?string}
     */
    public function uploadProfilePicture(int $accountId, string $absolutePath, string $originalFileName): array
    {
        $baseUrl = (string) config('services.csharp_api.url');
        $apiKey = (string) config('services.csharp_api.key');
        $timeout = max(1, (int) config('services.csharp_api.timeout', 90));
        $connectTimeout = max(1, (int) config('services.csharp_api.connect_timeout', 15));

        $headers = [
            'X-Api-Key' => $apiKey,
            'Accept' => 'application/json',
        ];
        if (Session::has('api_token')) {
            $token = trim((string) preg_replace('/^Bearer\s+/i', '', (string) Session::get('api_token')));
            if ($token !== '') {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
        }

        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->baseUrl($baseUrl)
            ->attach('file', file_get_contents($absolutePath), $originalFileName)
            ->post("/api/v1/accounts/{$accountId}/profile-picture")
            ->throw();

        $raw = $response->json();
        return [
            'profilePicture' => is_array($raw) ? ($raw['profilePicture'] ?? $raw['ProfilePicture'] ?? null) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeCreatePayload(array $payload): array
    {
        $normalized = [
            'name' => trim((string) ($payload['name'] ?? $payload['Name'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? $payload['Email'] ?? '')),
            'password' => (string) ($payload['password'] ?? $payload['Password'] ?? ''),
            'role' => (string) ($payload['role'] ?? $payload['Role'] ?? 'User'),
            'isActive' => $this->coerceBool($payload['isActive'] ?? $payload['IsActive'] ?? true),
        ];

        $spec = $payload['specialization'] ?? $payload['Specialization'] ?? null;
        if (is_string($spec) && trim($spec) !== '') {
            $normalized['specialization'] = trim($spec);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeUpdatePayload(array $payload): array
    {
        $allowed = [
            'name' => 'name',
            'Name' => 'name',
            'role' => 'role',
            'Role' => 'role',
            'isActive' => 'isActive',
            'IsActive' => 'isActive',
            'profilePicture' => 'profilePicture',
            'ProfilePicture' => 'profilePicture',
            'specialization' => 'specialization',
            'Specialization' => 'specialization',
            'currentPassword' => 'currentPassword',
            'CurrentPassword' => 'currentPassword',
            'newPassword' => 'newPassword',
            'NewPassword' => 'newPassword',
            'confirmPassword' => 'confirmPassword',
            'ConfirmPassword' => 'confirmPassword',
        ];

        $normalized = [];
        foreach ($payload as $key => $value) {
            if (!isset($allowed[$key])) {
                continue;
            }
            $target = $allowed[$key];

            if ($target === 'isActive') {
                $normalized[$target] = $this->coerceBool($value);
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
                $normalized[$target] = $value === '' ? null : $value;
                continue;
            }

            $normalized[$target] = $value;
        }

        return $normalized;
    }

    private function coerceBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            $v = mb_strtolower(trim($value));
            return in_array($v, ['1', 'true', 'yes', 'on'], true);
        }
        return (bool) $value;
    }
}
