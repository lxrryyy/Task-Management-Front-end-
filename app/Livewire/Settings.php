<?php

namespace App\Livewire;

use App\Services\AccountApiService;
use App\Support\AccountPresentation;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;

class Settings extends Component
{
    use WithFileUploads;

    public int $accountId = 0;
    public string $role = '';
    public string $email = '';
    public string $fullName = '';

    public string $firstName = '';
    public string $lastName = '';
    public string $bio = '';

    /** @var string|null URL string returned by API (we also support data:image/... URLs) */
    #[Locked]
    public ?string $profilePicture = null;

    #[Locked]
    public ?string $photoPreview = null;
    public $photo = null; // Livewire uploaded file

    public string $initials = '';

    // Avatar background color (computed on load/save only, not while typing/uploading)
    public string $avatarBg = '#102B3C';

    // Photo upload confirmation
    public bool $showPhotoConfirmModal = false;
    public ?string $pendingPhotoName = null;

    // Password inputs
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $confirmPassword = '';

    public bool $saving = false;
    public ?string $saveError = null;
    /** @var string|null Green banner text after save; null = hidden */
    public ?string $saveSuccessMessage = null;

    /** Bumped on each successful save so the banner remounts and the dismiss timer restarts */
    public int $saveSuccessNonce = 0;

    /** Bumped when an error is shown so the error banner remounts and the 4s dismiss timer restarts */
    public int $saveErrorNonce = 0;

    /** Baselines set in loadProfile — used to detect profile vs password-only saves */
    private string $baselineFirstName = '';
    private string $baselineLastName = '';
    private string $baselineBio = '';
    private ?string $baselineProfilePicture = null;

    private function computeInitials(string $firstName, string $lastName, string $fallbackFullName): string
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $fallbackFullName = trim($fallbackFullName);

        $a = mb_substr($firstName, 0, 1);
        $b = mb_substr($lastName, 0, 1);

        if ($a !== '' && $b !== '') return mb_strtoupper($a . $b);
        if ($a !== '') return mb_strtoupper($a);

        // Fallback: use first letters of the first two words
        if ($fallbackFullName !== '') {
            $parts = preg_split('/\s+/', $fallbackFullName);
            $parts = array_values(array_filter($parts, fn($p) => is_string($p) && trim($p) !== ''));
            $first = mb_substr($parts[0] ?? '', 0, 1);
            $second = mb_substr($parts[1] ?? '', 0, 1);
            $out = trim(($first ?? '') . ($second ?? ''));
            if ($out !== '') return mb_strtoupper($out);
        }

        return '?';
    }

    private function computeAvatarBg(): string
    {
        $bgColors = ['#102B3C', '#205375', '#F0EFEF', '#ED1C24'];
        $seed = (string) ($this->accountId ?: ($this->fullName ?: trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''))));
        $idx = (int) (abs(crc32($seed)) % count($bgColors));
        return $bgColors[$idx] ?? '#102B3C';
    }

    public function mount(): void
    {
        $this->loadProfile();
    }

    private function loadProfile(): void
    {
        $user = Session::get('user', []);
        $this->accountId = (int) ($user['id'] ?? $user['Id'] ?? 0);
        $this->role = (string) ($user['role'] ?? $user['Role'] ?? $user['roleName'] ?? $user['RoleName'] ?? '');
        $this->email = (string) ($user['email'] ?? $user['Email'] ?? '');
        $this->fullName = (string) ($user['name'] ?? $user['Name'] ?? $user['fullName'] ?? '');
        $this->profilePicture = $this->normalizeProfilePicture(
            $user['profilePicture'] ?? $user['ProfilePicture'] ?? null
        );
        $this->bio = '';

        // Split full name into first/last for display + initials fallback
        $parts = preg_split('/\s+/', trim($this->fullName));
        $parts = array_values(array_filter($parts, fn($p) => is_string($p) && trim($p) !== ''));
        $this->firstName = (string) ($parts[0] ?? '');
        $this->lastName = (string) (!empty($parts) ? implode(' ', array_slice($parts, 1)) : '');

        $this->saveError = null;

        // Enrich from accounts API.
        if ($this->accountId > 0) {
            try {
                $acc = app(AccountApiService::class)->find($this->accountId);
                if (is_array($acc) && $acc !== []) {
                    $this->fullName = (string) ($acc['name'] ?? $acc['Name'] ?? $this->fullName);
                    $this->email = (string) ($acc['email'] ?? $acc['Email'] ?? $this->email);
                    $this->role = (string) ($acc['role'] ?? $acc['Role'] ?? $this->role);
                    $this->bio = (string) ($acc['specialization'] ?? $acc['Specialization'] ?? $acc['bio'] ?? $acc['Bio'] ?? $this->bio);
                    $this->profilePicture = $this->normalizeProfilePicture(
                        $acc['profilePicture'] ?? $acc['ProfilePicture'] ?? $this->profilePicture
                    );

                    $parts2 = preg_split('/\s+/', trim($this->fullName));
                    $parts2 = array_values(array_filter($parts2, fn($p) => is_string($p) && trim($p) !== ''));
                    $this->firstName = (string) ($parts2[0] ?? $this->firstName);
                    $this->lastName = (string) (!empty($parts2) ? implode(' ', array_slice($parts2, 1)) : $this->lastName);
                }
            } catch (\Throwable) {
                // Keep whatever we got from Session
            }
        }

        // Important: compute avatar bg only when we (re)load profile (mount/save/remove),
        // so it doesn't change while the user types or selects a new photo.
        $this->avatarBg = $this->computeAvatarBg();

        $this->baselineFirstName = $this->firstName;
        $this->baselineLastName = $this->lastName;
        $this->baselineBio = $this->bio;
        $this->baselineProfilePicture = $this->profilePicture;
    }

    private function normalizeProfilePicture(mixed $value): ?string
    {
        return AccountPresentation::profilePictureDisplayUrl($value);
    }

    public function updatedPhoto(): void
    {
        $this->saveError = null;
        $this->saveSuccessMessage = null;

        $this->photoPreview = null;
        if ($this->photo) {
            // Livewire TemporaryUploadedFile provides temporaryUrl()
            try {
                $this->photoPreview = method_exists($this->photo, 'temporaryUrl') ? $this->photo->temporaryUrl() : null;
            } catch (\Throwable) {
                $this->photoPreview = null;
            }

            $this->pendingPhotoName = method_exists($this->photo, 'getClientOriginalName')
                ? (string) $this->photo->getClientOriginalName()
                : null;

            // Ask user to confirm before they hit "Save Changes"
            $this->showPhotoConfirmModal = true;
        }
    }

    /**
     * Persist a new avatar on the public disk and return the absolute public URL for the API.
     *
     * @throws \RuntimeException
     */
    private function storeUploadedProfilePictureOnPublicDisk(): string
    {
        if (! $this->photo) {
            throw new \RuntimeException('No photo selected.');
        }

        $tmp = method_exists($this->photo, 'getRealPath') ? $this->photo->getRealPath() : null;
        if (! is_string($tmp) || $tmp === '' || ! is_file($tmp)) {
            throw new \RuntimeException('Could not read the selected photo file. Please try again.');
        }

        $maxBytes = 5 * 1024 * 1024;
        $size = @filesize($tmp);
        if (is_int($size) && $size > $maxBytes) {
            throw new \RuntimeException('Photo must be 5 MB or smaller.');
        }

        $orig = method_exists($this->photo, 'getClientOriginalName')
            ? (string) $this->photo->getClientOriginalName()
            : 'photo.jpg';
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }

        $relativeDir = 'profiles/'.$this->accountId;
        $relativePath = $relativeDir.'/'.uniqid('', true).'.'.$ext;

        $bytes = file_get_contents($tmp);
        if (! is_string($bytes) || $bytes === '') {
            throw new \RuntimeException('Could not read the selected photo file. Please try again.');
        }

        Storage::disk('public')->put($relativePath, $bytes);

        return Storage::disk('public')->url($relativePath);
    }

    /**
     * Best-effort delete of a prior avatar we stored under storage/app/public/profiles/...
     */
    private function tryDeletePublicProfileIfOwned(?string $absoluteOrDisplayUrl): void
    {
        if (! is_string($absoluteOrDisplayUrl) || trim($absoluteOrDisplayUrl) === '') {
            return;
        }

        $path = parse_url($absoluteOrDisplayUrl, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
            return;
        }

        $relative = ltrim(substr($path, strlen('/storage')), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return;
        }

        if (! str_starts_with($relative, 'profiles/')) {
            return;
        }

        try {
            Storage::disk('public')->delete($relative);
        } catch (\Throwable) {
            // ignore
        }
    }

    public function removeProfilePicture(): void
    {
        if ($this->accountId <= 0) return;

        $this->saveError = null;
        $this->saveSuccessMessage = null;

        try {
            $this->tryDeletePublicProfileIfOwned($this->profilePicture);

            app(AccountApiService::class)->removeProfilePicture($this->accountId);

            $this->profilePicture = null;
            $this->photoPreview = null;
            $this->photo = null;
            $this->pendingPhotoName = null;
            $this->showPhotoConfirmModal = false;

            $this->avatarBg = $this->computeAvatarBg();
            $initialsTextClass = $this->avatarBg === '#F0EFEF' ? 'text-gray-800' : 'text-white';

            // Update session
            $user = Session::get('user', []);
            $user['profilePicture'] = null;
            $user['ProfilePicture'] = null;
            Session::put('user', $user);

            // Show success message
            $this->saveSuccessNonce++;
            $this->saveSuccessMessage = 'Profile picture removed successfully.';

            $this->dispatch(
                'avatar-updated',
                profilePicture: null,
                initials: $this->computeInitials($this->firstName, $this->lastName, $this->fullName),
                avatarBg: $this->avatarBg,
                initialsTextClass: $initialsTextClass
            );
        } catch (RequestException $e) {
            $status = $e->response?->status();
            $body = null;
            try {
                $body = $e->response?->json();
            } catch (\Throwable) {
            }

            $msg = null;
            if (is_array($body)) {
                $msg = $body['message'] ?? $body['Message'] ?? $body['detail'] ?? $body['error'] ?? null;
            }
            if (!$msg) {
                $raw = $e->response?->body();
                $msg = is_string($raw) && trim($raw) !== '' && strlen($raw) < 400 ? trim(strip_tags($raw)) : null;
            }

            $this->saveErrorNonce++;
            $this->saveError = $msg
                ? 'Could not remove photo: ' . $msg
                : 'Could not remove photo. Please try again.';
        } catch (\Throwable $e) {
            $this->saveErrorNonce++;
            $this->saveError = 'Failed to remove profile picture. Please try again.';
        }
    }

    public function cancelPhotoSelection(): void
    {
        $this->photo = null;
        $this->photoPreview = null;
        $this->pendingPhotoName = null;
        $this->showPhotoConfirmModal = false;
    }

    public function confirmPhotoSelection(): void
    {
        // Keep photo selected; actual API call happens in saveChanges()
        $this->showPhotoConfirmModal = false;
    }

    /** Called from Alpine after the success banner timeout */
    public function clearSaveSuccessBanner(): void
    {
        $this->saveSuccessMessage = null;
    }

    /** Called from Alpine after the error banner timeout */
    public function clearSaveErrorBanner(): void
    {
        $this->saveError = null;
    }

    public function saveChanges(): void
    {
        if ($this->accountId <= 0) return;

        $this->saving = true;
        $this->saveError = null;
        $this->saveSuccessMessage = null;
        $this->showPhotoConfirmModal = false;

        $uploadedPublicUrl = null;

        try {
            $composedFullName = trim(implode(' ', array_filter([
                trim((string) $this->firstName),
                trim((string) $this->lastName),
            ], fn($v) => $v !== '')));
            if ($composedFullName !== '') {
                $this->fullName = $composedFullName;
            }

            if ($this->photo) {
                $uploadedPublicUrl = $this->storeUploadedProfilePictureOnPublicDisk();
            }

            $hasProfileEdit = $this->photo !== null
                || trim((string) $this->firstName) !== trim((string) $this->baselineFirstName)
                || trim((string) $this->lastName) !== trim((string) $this->baselineLastName)
                || trim((string) $this->bio) !== trim((string) $this->baselineBio)
                || $this->normalizeProfilePicture($this->profilePicture) !== $this->normalizeProfilePicture($this->baselineProfilePicture);

            $hasPasswordEdit = trim((string) $this->newPassword) !== '';

            $roleRaw = trim((string) $this->role);
            $roleNormalized = mb_strtolower($roleRaw) === 'admin' ? 'Admin' : 'User';

            $payload = [
                'name' => $this->fullName,
                'role' => $roleNormalized,
                'isActive' => true,
                'profilePicture' => $uploadedPublicUrl ?? ($this->profilePicture ?: null),
                'specialization' => trim((string) $this->bio) !== '' ? $this->bio : null,
                'currentPassword' => trim((string) $this->currentPassword) !== '' ? $this->currentPassword : null,
                'newPassword' => trim((string) $this->newPassword) !== '' ? $this->newPassword : null,
                'confirmPassword' => trim((string) $this->confirmPassword) !== '' ? $this->confirmPassword : null,
            ];

            app(AccountApiService::class)->update($this->accountId, $payload);

            if ($uploadedPublicUrl !== null) {
                $this->tryDeletePublicProfileIfOwned($this->baselineProfilePicture);
            }

            // Refresh from API / session so the UI reflects server state
            $this->photo = null;
            $this->photoPreview = null;
            $this->pendingPhotoName = null;
            $this->loadProfile();

            $this->saveSuccessNonce++;
            if ($hasPasswordEdit && $hasProfileEdit) {
                $this->saveSuccessMessage = 'Profile and password updated successfully.';
            } elseif ($hasPasswordEdit) {
                $this->saveSuccessMessage = 'Password updated successfully.';
            } elseif ($hasProfileEdit) {
                $this->saveSuccessMessage = 'Profile updated successfully.';
            } else {
                $this->saveSuccessMessage = 'Saved successfully.';
            }

            $this->currentPassword = '';
            $this->newPassword = '';
            $this->confirmPassword = '';

            $initialsTextClass = $this->avatarBg === '#F0EFEF' ? 'text-gray-800' : 'text-white';
            $picForHeader = $this->profilePicture ?: $uploadedPublicUrl;

            // Update the session user object so the header has the latest profile picture.
            $user = Session::get('user', []);
            if (!empty($picForHeader)) {
                $user['profilePicture'] = $picForHeader;
                $user['ProfilePicture'] = $picForHeader;
            }
            $user['name'] = $this->fullName;
            $user['Name'] = $this->fullName;
            $user['id'] = $this->accountId;
            $user['Id'] = $this->accountId;
            $user['specialization'] = trim((string) $this->bio);
            $user['Specialization'] = trim((string) $this->bio);
            Session::put('user', $user);

            $this->dispatch(
                'avatar-updated',
                profilePicture: $picForHeader,
                initials: $this->computeInitials($this->firstName, $this->lastName, $this->fullName),
                avatarBg: $this->avatarBg,
                initialsTextClass: $initialsTextClass
            );
        } catch (RequestException $e) {
            if ($uploadedPublicUrl !== null) {
                $this->tryDeletePublicProfileIfOwned($uploadedPublicUrl);
            }

            $status = $e->response?->status();
            $body = null;
            try {
                $body = $e->response?->json();
            } catch (\Throwable) {
                $body = null;
            }

            // Best-effort extract a readable backend error.
            $msg = null;
            if (is_array($body)) {
                $msg = $body['message']
                    ?? $body['Message']
                    ?? $body['detail']
                    ?? $body['Detail']
                    ?? $body['error']
                    ?? $body['Error']
                    ?? null;
                if (!$msg && !empty($body['errors'])) $msg = json_encode($body['errors']);
            }
            if (!$msg) {
                $raw = $e->response?->body();
                $msg = is_string($raw) && trim($raw) !== '' ? trim($raw) : 'Request failed.';
            }

            Log::warning('Account update failed', [
                'status' => $status,
                'accountId' => $this->accountId,
                'error' => $msg,
                'response' => $body,
            ]);

            $this->saveErrorNonce++;
            $this->saveError = "Failed to update account (HTTP {$status}). {$msg}";
        } catch (\RuntimeException $e) {
            $this->saveErrorNonce++;
            $this->saveError = $e->getMessage();
        } catch (\Throwable $e) {
            $this->saveErrorNonce++;
            $this->saveError = 'Failed to update account. Please try again.';
        } finally {
            $this->saving = false;
        }
    }

    public function render()
    {
        $this->initials = $this->computeInitials($this->firstName, $this->lastName, $this->fullName);
        return view('livewire.settings');
    }
}
