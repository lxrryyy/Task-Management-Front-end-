<?php

namespace App\Support;

/**
 * Normalizes account "bio" and specialization-style fields from API payloads
 * (flat keys and naming variants). Matches resolution used in Blade person-option flows.
 */
final class AccountPresentation
{
    /**
     * @param  array<string, mixed>  $account
     * @return array{0: string, 1: string} [bioLine, specLine]
     */
    public static function resolveBioAndSpec(array $account): array
    {
        $bio = '';
        foreach (['bio', 'Bio', 'about', 'About', 'summary', 'Summary'] as $key) {
            $raw = $account[$key] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            $t = trim((string) $raw);
            if ($t !== '') {
                $bio = $t;
                break;
            }
        }

        $spec = '';
        foreach (
            [
                'specialization', 'Specialization', 'specialisations', 'Specialisations',
                'jobTitle', 'JobTitle', 'position', 'Position',
                'title', 'Title', 'department', 'Department',
            ] as $key
        ) {
            $raw = $account[$key] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            $t = trim((string) $raw);
            if ($t !== '') {
                $spec = $t;
                break;
            }
        }

        return [$bio, $spec];
    }

    public static function mergeBioSpecLine(string $bio, string $spec): string
    {
        if ($bio !== '' && $spec !== '' && $bio !== $spec) {
            return $bio.' · '.$spec;
        }

        return $bio !== '' ? $bio : $spec;
    }

    /**
     * Single string for hover cards / table subtitles.
     *
     * @param  array<string, mixed>  $account
     */
    public static function displaySpecialization(array $account): string
    {
        [$b, $s] = self::resolveBioAndSpec($account);

        return self::mergeBioSpecLine($b, $s);
    }
}
