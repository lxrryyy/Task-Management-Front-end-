<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;

/**
 * Typed wrapper around the v1 auth API. Handles the bearer token / session normalisation
 * the legacy CsharpApiService could only do via untyped array access.
 */
class AuthApiService
{
    public function __construct(private CsharpApiService $api) {}

    /**
     * @return array{
     *   token: string,
     *   expiresIn: int,
     *   expiresAt: ?string,
     *   user: array<string, mixed>,
     * }
     */
    public function login(string $email, string $password, bool $rememberMe = false): array
    {
        $response = $this->api->post('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'rememberMe' => $rememberMe,
        ]);

        $token = (string) ($response['token'] ?? $response['Token'] ?? '');
        $token = trim((string) preg_replace('/^Bearer\s+/i', '', $token));

        $expiresIn = (int) ($response['expiresIn'] ?? $response['ExpiresIn'] ?? 0);
        $expiresAt = $response['expiresAt'] ?? $response['ExpiresAt'] ?? null;

        $user = $response['user'] ?? $response['User'] ?? [];
        if (!is_array($user)) {
            $user = [];
        }

        return [
            'token' => $token,
            'expiresIn' => $expiresIn,
            'expiresAt' => is_string($expiresAt) ? $expiresAt : null,
            'user' => $user,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function me(): ?array
    {
        try {
            $raw = $this->api->get('/api/v1/auth/me', ['_no_cache' => 1]);
        } catch (\Throwable) {
            return null;
        }

        return is_array($raw) && $raw !== [] ? $raw : null;
    }

    public function logout(): void
    {
        try {
            $this->api->post('/api/v1/auth/logout', []);
        } catch (\Throwable) {
            // Best effort — don't block client-side logout on a backend failure.
        }
    }

    /**
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function forgotPassword(string $email): array
    {
        try {
            $this->api->post('/api/v1/auth/forgot-password', ['email' => trim($email)]);
            return ['ok' => true, 'errors' => []];
        } catch (RequestException $e) {
            return ['ok' => false, 'errors' => $this->api->extractFieldErrors($e->response)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'errors' => ['api_error' => [$e->getMessage()]]];
        }
    }

    /**
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function verifyOtp(string $email, string $code): array
    {
        try {
            $this->api->post('/api/v1/auth/verify-otp', [
                'email' => trim($email),
                'code' => trim($code),
            ]);
            return ['ok' => true, 'errors' => []];
        } catch (RequestException $e) {
            return ['ok' => false, 'errors' => $this->api->extractFieldErrors($e->response)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'errors' => ['api_error' => [$e->getMessage()]]];
        }
    }

    /**
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function resetPassword(string $email, string $newPassword): array
    {
        try {
            $this->api->post('/api/v1/auth/reset-password', [
                'email' => trim($email),
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
