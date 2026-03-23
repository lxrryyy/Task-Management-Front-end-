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
        $this->profilePicture = $user['profilePicture'] ?? $user['ProfilePicture'] ?? null;
        $this->bio = '';

        // Split full name into first/last for display + initials fallback
        $parts = preg_split('/\s+/', trim($this->fullName));
        $parts = array_values(array_filter($parts, fn ($p) => is_string($p) && trim($p) !== ''));
        $this->firstName = (string) ($parts[0] ?? '');
        $this->lastName = (string) (!empty($parts) ? implode(' ', array_slice($parts, 1)) : '');

        $this->saveError = null;
        $this->saveSuccess = false;

        // Try to enrich profile from the accounts endpoint (we already use this in other components)
        if ($this->accountId > 0) {
            try {
                $raw = app(CsharpApiService::class)->get('/api/Account/GetAllUserRoleAccount');
                $list = is_array($raw) ? ($raw['data'] ?? $raw['accounts'] ?? $raw) : [];

                foreach ((array) $list as $acc) {
                    if (!is_array($acc)) continue;
                    $id = (int) ($acc['id'] ?? $acc['Id'] ?? 0);
                    if ($id !== $this->accountId) continue;

                    $this->profilePicture = $acc['profilePicture'] ?? $acc['ProfilePicture'] ?? $this->profilePicture;
                    $this->bio = (string) ($acc['description'] ?? $acc['Description'] ?? $acc['specialization'] ?? $acc['Specialization'] ?? $acc['bio'] ?? $acc['Bio'] ?? $this->bio);

                    // If API returns structured names, prefer them
                    if (!empty($acc['firstName']) || !empty($acc['lastName'])) {
                        $this->firstName = (string) ($acc['firstName'] ?? $acc['FirstName'] ?? $this->firstName);
                        $this->lastName = (string) ($acc['lastName'] ?? $acc['LastName'] ?? $this->lastName);
                    } elseif (!empty($acc['name']) || !empty($acc['fullName'])) {
                        $this->fullName = (string) ($acc['name'] ?? $acc['Name'] ?? $acc['fullName'] ?? $acc['FullName'] ?? $this->fullName);
                        $parts2 = preg_split('/\s+/', trim($this->fullName));
                        $parts2 = array_values(array_filter($parts2, fn ($p) => is_string($p) && trim($p) !== ''));
                        $this->firstName = (string) ($parts2[0] ?? $this->firstName);
                        $this->lastName = (string) (!empty($parts2) ? implode(' ', array_slice($parts2, 1)) : $this->lastName);
                    }
                    break;
                }
            } catch (\Throwable) {
                // Keep whatever we got from Session
            }
        }

        // Important: compute avatar bg only when we (re)load profile (mount/save/remove),
        // so it doesn't change while the user types or selects a new photo.
        $this->avatarBg = $this->computeAvatarBg();
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
            // Base payload follows your Swagger example for PATCH /api/Account/UpdateAccount/{id}
            $payload = [
                'passwordHash' => $this->currentPassword,
                'role' => $this->role,
                'isActive' => true,
                'profilePicture' => $this->profilePicture, // overwritten if new photo selected
                'specializationPassword' => $this->bio,
                'newPassword' => $this->newPassword,
                'confirmPassword' => $this->confirmPassword,
                // Extra fields (ignored server-side if not supported)
                'firstName' => $this->firstName,
                'lastName' => $this->lastName,
                'email' => $this->email,
            ];

            if ($this->photo) {
                $payload['profilePicture'] = $this->photoFileToDataUrl($this->photo);
            }

            app(CsharpApiService::class)->patch("/api/Account/UpdateAccount/{$this->accountId}", $payload);

            // Refresh from API / session so the UI reflects server state
            $this->photo = null;
            $this->photoPreview = null;
            $this->pendingPhotoName = null;
            $this->loadProfile();
            $this->saveSuccess = true;
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
