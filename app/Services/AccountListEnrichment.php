<?php

namespace App\Services;

use App\Support\AccountPresentation;

/**
 * GetAllUserRoleAccount often returns a slim DTO without bio/specialization, while
 * GetAccountById returns the full profile (same as after login). Merge full profile
 * for members that need it, with one fetch per account id per HTTP request.
 */
final class AccountListEnrichment
{
    /** @var array<int, array<string, mixed>|null> */
    private static array $fetchedById = [];

    public function __construct(private CsharpApiService $api) {}

    /**
     * @param  array<int, array<string, mixed>>  $projects  Project payloads (assigneeIds / AssigneeIds)
     * @param  array<int, array<string, mixed>>  $accounts
     * @return array<int, array<string, mixed>>
     */
    public function mergeFullProfilesWhereMissing(array $projects, array $accounts): array
    {
        $memberIds = [];
        foreach ($projects as $project) {
            if (! is_array($project)) {
                continue;
            }
            $ids = $project['assigneeIds'] ?? $project['AssigneeIds'] ?? null;
            if (! is_array($ids)) {
                continue;
            }
            foreach ($ids as $aid) {
                $id = (int) $aid;
                if ($id > 0) {
                    $memberIds[$id] = true;
                }
            }
        }

        if ($memberIds === []) {
            foreach ($accounts as $acc) {
                if (! is_array($acc)) {
                    continue;
                }
                $id = (int) ($acc['id'] ?? $acc['Id'] ?? 0);
                if ($id > 0) {
                    $memberIds[$id] = true;
                }
            }
        }

        $byId = [];
        foreach ($accounts as $acc) {
            if (! is_array($acc)) {
                continue;
            }
            $id = (int) ($acc['id'] ?? $acc['Id'] ?? 0);
            if ($id > 0) {
                $byId[$id] = $acc;
            }
        }

        foreach (array_keys($memberIds) as $id) {
            if ($id <= 0 || ! isset($byId[$id])) {
                continue;
            }
            $acc = $byId[$id];
            if (AccountPresentation::displaySpecialization($acc) !== '') {
                continue;
            }
            $full = $this->fetchFullProfile($id);
            if ($full !== [] && AccountPresentation::displaySpecialization($full) !== '') {
                $byId[$id] = array_merge($acc, $full);
            }
        }

        $result = [];
        foreach ($accounts as $acc) {
            if (! is_array($acc)) {
                continue;
            }
            $id = (int) ($acc['id'] ?? $acc['Id'] ?? 0);
            $result[] = ($id > 0 && isset($byId[$id])) ? $byId[$id] : $acc;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchFullProfile(int $id): array
    {
        if (array_key_exists($id, self::$fetchedById)) {
            $cached = self::$fetchedById[$id];

            return is_array($cached) ? $cached : [];
        }

        try {
            $raw = $this->api->get("/api/Account/GetAccountById/{$id}");
            if (is_array($raw)) {
                self::$fetchedById[$id] = $raw;

                return $raw;
            }
        } catch (\Throwable) {
        }

        self::$fetchedById[$id] = [];

        return [];
    }
}
