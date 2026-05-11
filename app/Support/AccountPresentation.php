<?php

namespace App\Support;

/**
 * Normalizes account "bio" and specialization-style fields from API payloads
 * (flat keys and naming variants). Matches resolution used in Blade person-option flows.
 */
final class AccountPresentation
{
    /**
     * Normalize API/session profile picture values for HTML img[src] / Alpine.
     *
     * - http(s) and data:image/* pass through.
     * - Paths starting with /uploads/ are prefixed with the .NET API base URL (legacy API-hosted files).
     * - Paths starting with /storage/ are prefixed with the public disk base (PUBLIC_DISK_URL or APP_URL + /storage).
     * - Other non-empty strings are treated as legacy raw base64 and wrapped as a JPEG data URL.
     */
    public static function profilePictureDisplayUrl(mixed $pic): ?string
    {
        if (! is_string($pic)) {
            return null;
        }
        $pic = trim($pic);
        if ($pic === '') {
            return null;
        }
        if (
            str_starts_with($pic, 'http://')
            || str_starts_with($pic, 'https://')
            || str_starts_with($pic, 'data:image/')
        ) {
            return $pic;
        }
        if (str_starts_with($pic, '/uploads/')) {
            $base = rtrim((string) config('services.csharp_api.url'), '/');

            return $base !== '' ? $base.$pic : $pic;
        }
        if (str_starts_with($pic, '/storage/')) {
            $diskUrl = rtrim((string) (config('filesystems.disks.public.url') ?? ''), '/');
            if ($diskUrl === '') {
                return $pic;
            }
            $suffix = ltrim(substr($pic, strlen('/storage')), '/');

            return $diskUrl.'/'.$suffix;
        }

        return 'data:image/jpeg;base64,'.$pic;
    }

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
