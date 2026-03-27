<?php

namespace App\Livewire;

use App\Services\CsharpApiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
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
    public ?string $profilePicture = null;
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
    public bool $saveSuccess = false;

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
            $parts = array_values(array_filter($parts, fn ($p) => is_string($p) && trim($p) !== ''));
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
        $parts = array_values(array_filter($parts, fn ($p) => is_string($p) && trim($p) !== ''));
        $this->firstName = (string) ($parts[0] ?? '');
        $this->lastName = (string) (!empty($parts) ? implode(' ', array_slice($parts, 1)) : '');

        $this->saveError = null;
        $this->saveSuccess = false;

        // Enrich from GetAccountById endpoint.
        if ($this->accountId > 0) {
            try {
                $acc = app(CsharpApiService::class)->get("/api/Account/GetAccountById/{$this->accountId}");
                if (is_array($acc) && $acc !== []) {
                    $this->fullName = (string) ($acc['name'] ?? $acc['Name'] ?? $this->fullName);
                    $this->email = (string) ($acc['email'] ?? $acc['Email'] ?? $this->email);
                    $this->role = (string) ($acc['role'] ?? $acc['Role'] ?? $this->role);
                    $this->bio = (string) ($acc['specialization'] ?? $acc['Specialization'] ?? $acc['bio'] ?? $acc['Bio'] ?? $this->bio);
                    $this->profilePicture = $this->normalizeProfilePicture(
                        $acc['profilePicture'] ?? $acc['ProfilePicture'] ?? $this->profilePicture
                    );

                    $parts2 = preg_split('/\s+/', trim($this->fullName));
                    $parts2 = array_values(array_filter($parts2, fn ($p) => is_string($p) && trim($p) !== ''));
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
    }

    private function normalizeProfilePicture(mixed $value): ?string
    {
        $pic = is_string($value) ? trim($value) : '';
        if ($pic === '') return null;
        if (str_starts_with($pic, 'http://') || str_starts_with($pic, 'https://') || str_starts_with($pic, 'data:image/')) {
            return $pic;
        }
        // API may return raw base64 only.
        return 'data:image/jpeg;base64,' . $pic;
    }

    public function updatedPhoto(): void
    {
        $this->saveError = null;
        $this->saveSuccess = false;

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

    private function photoFileToDataUrl($file): string
    {
        // Livewire's TemporaryUploadedFile supports getRealPath()
        $path = method_exists($file, 'getRealPath') ? $file->getRealPath() : null;
        if (!$path || !is_string($path) || !file_exists($path)) {
            return '';
        }
        $bytes = file_get_contents($path);
        if (!is_string($bytes) || $bytes === '') return '';

        $base64 = base64_encode($bytes);
        // For many APIs this is what they mean by "URL string": a data URL is still a URL.
        $mime = method_exists($file, 'getMimeType') ? (string) $file->getMimeType() : 'image';
        if (!str_contains($mime, '/')) $mime = 'image/'.$mime;

        return "data:{$mime};base64,{$base64}";
    }

    public function removeProfilePicture(): void
    {
        if ($this->accountId <= 0) return;

        $this->saveError = null;
        $this->saveSuccess = false;

        try {
            app(CsharpApiService::class)->delete("/api/Account/RemoveProfilePicture/{$this->accountId}");
            $this->profilePicture = null;
            $this->photoPreview = null;
            $this->photo = null;
            $this->pendingPhotoName = null;
            $this->showPhotoConfirmModal = false;

            // Refresh profile so initials + avatar background are consistent
            $this->loadProfile();

            $initialsTextClass = $this->avatarBg === '#F0EFEF' ? 'text-gray-800' : 'text-white';

            // Keep header (and other pages) in sync without requiring manual refresh
            $user = Session::get('user', []);
            $user['profilePicture'] = $this->profilePicture;
            $user['ProfilePicture'] = $this->profilePicture;
            $user['name'] = $this->fullName;
            $user['Name'] = $this->fullName;
            $user['id'] = $this->accountId;
            $user['Id'] = $this->accountId;
            Session::put('user', $user);

            $this->dispatch('avatar-updated', profilePicture: $this->profilePicture, initials: $this->computeInitials($this->firstName, $this->lastName, $this->fullName), avatarBg: $this->avatarBg, initialsTextClass: $initialsTextClass);
        } catch (\Throwable $e) {
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

    public function saveChanges(): void
    {
        if ($this->accountId <= 0) return;

        $this->saving = true;
        $this->saveError = null;
        $this->saveSuccess = false;
        $this->showPhotoConfirmModal = false;

        try {
            $composedFullName = trim(implode(' ', array_filter([
                trim((string) $this->firstName),
                trim((string) $this->lastName),
            ], fn ($v) => $v !== '')));
            if ($composedFullName !== '') {
                $this->fullName = $composedFullName;
            }

            // If user selected a new photo, compute it once for both:
            // 1) sending to the backend
            // 2) instant UI update for the header (in case refresh response is delayed)
            $pendingPicForUi = null;
            if ($this->photo) {
                $pendingPicForUi = $this->photoFileToDataUrl($this->photo);
            }

            $editor = Session::get('user', []);
            $editorId = (int) ($editor['id'] ?? $editor['Id'] ?? 0);

            $roleRaw = trim((string) $this->role);
            $roleNormalized = mb_strtolower($roleRaw) === 'admin' ? 'Admin' : 'User';

            $payload = [
                'name' => $this->fullName,
                'passwordHash' => trim((string) $this->currentPassword) !== '' ? $this->currentPassword : null,
                'role' => $roleNormalized,
                'isActive' => true,
                'profilePicture' => $this->photo
                    ? ($pendingPicForUi ?: null)
                    : ($this->profilePicture ?: null),
                'specialization' => trim((string) $this->bio) !== '' ? $this->bio : null,
                'currentPassword' => trim((string) $this->currentPassword) !== '' ? $this->currentPassword : null,
                'newPassword' => trim((string) $this->newPassword) !== '' ? $this->newPassword : null,
                'confirmPassword' => trim((string) $this->confirmPassword) !== '' ? $this->confirmPassword : null,
            ];

            app(CsharpApiService::class)->patch(
                "/api/Account/UpdateAccount/{$this->accountId}?editorId={$editorId}",
                $payload
            );

            // Refresh from API / session so the UI reflects server state
            $this->photo = null;
            $this->photoPreview = null;
            $this->pendingPhotoName = null;
            $this->loadProfile();
            $this->saveSuccess = true;

            $initialsTextClass = $this->avatarBg === '#F0EFEF' ? 'text-gray-800' : 'text-white';
            $picForHeader = !empty($this->profilePicture) ? $this->profilePicture : $pendingPicForUi;

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
            Session::put('user', $user);

            $this->dispatch(
                'avatar-updated',
                profilePicture: $picForHeader,
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

            $this->saveError = "Failed to update account (HTTP {$status}). {$msg}";
        } catch (\Throwable $e) {
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
