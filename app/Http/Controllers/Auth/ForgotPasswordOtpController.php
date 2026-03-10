<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\CsharpApiService;
use Illuminate\Http\Client\RequestException;

class ForgotPasswordOtpController extends Controller
{
    public function __construct(protected CsharpApiService $api) {}

    public function sendResetOtp(string $email): array
    {
        $email = trim($email);
        if ($email === '') {
            return ['ok' => false, 'errors' => ['email' => ['Email is required.']]];
        }

        try {
            $this->api->post('/api/Auth/ForgotPassword', ['email' => $email]);
            return ['ok' => true, 'errors' => []];
        } catch (RequestException $e) {
            return ['ok' => false, 'errors' => $this->api->extractFieldErrors($e->response)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'errors' => ['api_error' => [$e->getMessage()]]];
        }
    }

    public function verifyOtp(string $email, string $code): array
    {
        $email = trim($email);
        $code  = trim($code);

        $errors = [];
        if ($email === '') $errors['email'][] = 'Email is required.';
        if ($code === '') $errors['code'][] = 'OTP code is required.';
        if (!empty($errors)) return ['ok' => false, 'errors' => $errors];

        try {
            $this->api->post('/api/Auth/VerifyOtp', ['email' => $email, 'code' => $code]);
            return ['ok' => true, 'errors' => []];
        } catch (RequestException $e) {
            return ['ok' => false, 'errors' => $this->api->extractFieldErrors($e->response)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'errors' => ['api_error' => [$e->getMessage()]]];
        }
    }

    public function resetPassword(string $email, string $newPassword): array
    {
        $email       = trim($email);
        $newPassword = (string) $newPassword;

        $errors = [];
        if ($email === '') $errors['email'][] = 'Email is required.';
        if (trim($newPassword) === '') $errors['newPassword'][] = 'New password is required.';
        if (!empty($errors)) return ['ok' => false, 'errors' => $errors];

        try {
            $this->api->post('/api/Auth/ResetPassword', [
                'email'       => $email,
                'newPassword' => $newPassword,
            ]);
            return ['ok' => true, 'errors' => []];
        } catch (RequestException $e) {
            return ['ok' => false, 'errors' => $this->api->extractFieldErrors($e->response)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'errors' => ['api_error' => [$e->getMessage()]]];
        }
    }
}

