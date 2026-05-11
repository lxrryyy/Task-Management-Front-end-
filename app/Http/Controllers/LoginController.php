<?php

namespace App\Http\Controllers;

use App\Services\AccountApiService;
use App\Services\AuthApiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    public function __construct(
        protected AccountApiService $accountsApi,
        protected AuthApiService $authApi,
    ) {}

    public function showForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        try {
            $response = $this->authApi->login(
                (string) $request->email,
                (string) $request->password,
                (bool) $request->boolean('remember'),
            );

            Session::put('api_token', $response['token']);
            Session::put('expires_in', $response['expiresIn']);

            $user = $response['user'] !== [] ? $response['user'] : ['email' => $request->email];

            $userId = (int) ($user['id'] ?? $user['Id'] ?? 0);
            if ($userId > 0) {
                $profile = $this->accountsApi->find($userId);
                if (is_array($profile)) {
                    $specialization = $profile['specialization']
                        ?? $profile['Specialization']
                        ?? null;
                    $user['specialization'] = is_string($specialization) ? trim($specialization) : $specialization;
                    $user['Specialization'] = $user['specialization'];
                }
            }

            Session::put('user', $user);

            return redirect()->route('dashboard');
        } catch (RequestException $e) {
            $body = $e->response?->json() ?? [];
            // v1 returns { "error": "..." } via GlobalExceptionMiddleware; legacy used { "message": "..." }.
            $message = (string) ($body['error'] ?? $body['message'] ?? 'Invalid credentials');

            return back()->withErrors(['email' => $message]);
        } catch (\Exception $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }
    }

    public function logout()
    {
        $this->authApi->logout();

        Session::forget(['api_token', 'expires_in', 'user']);

        return redirect()->route('login');
    }
}
