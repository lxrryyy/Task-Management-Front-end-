<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthApiService;

class ForgotPasswordOtpController extends Controller
{
    public function __construct(protected AuthApiService $authApi) {}

    /**
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function sendResetOtp(string $email): array
    {
        $email = trim($email);
        if ($email === '') {
            return ['ok' => false, 'errors' => ['email' => ['Email is required.']]];
        }

        return $this->authApi->forgotPassword($email);
    }

    /**
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function verifyOtp(string $email, string $code): array
    {
        $email = trim($email);
        $code = trim($code);

        $errors = [];
        if ($email === '') $errors['email'][] = 'Email is required.';
        if ($code === '') $errors['code'][] = 'OTP code is required.';
        if (!empty($errors)) return ['ok' => false, 'errors' => $errors];

        return $this->authApi->verifyOtp($email, $code);
    }

    /**
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function resetPassword(string $email, string $newPassword): array
    {
        $email = trim($email);
        $newPassword = (string) $newPassword;

        $errors = [];
        if ($email === '') $errors['email'][] = 'Email is required.';
        if (trim($newPassword) === '') $errors['newPassword'][] = 'New password is required.';
        if (!empty($errors)) return ['ok' => false, 'errors' => $errors];

        return $this->authApi->resetPassword($email, $newPassword);
    }
}
