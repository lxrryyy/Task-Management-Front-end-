<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    public function __construct(protected CsharpApiService $api) {}

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
            $response = $this->api->post('/api/Auth/login', [
                'email'      => $request->email,
                'password'   => $request->password,
                'rememberMe' => (bool) $request->boolean('remember'),
            ]);

            $token = $response['token'] ?? '';
            $token = preg_replace('/^Bearer\s+/i', '', $token);
            Session::put('api_token', trim($token));
            Session::put('expires_in', $response['expiresIn'] ?? null);

            // Use the user object returned directly by the login endpoint
            $user = $response['user'] ?? ['email' => $request->email];

            Session::put('user', $user);

            return redirect()->route('dashboard');
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $status = $e->response->status();
            $body = $e->response->json();
            $message = $body['message'] ?? 'Invalid credentials';
            return back()->withErrors(['email' => $message]);
        } catch (\Exception $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }
    }

    public function logout()
    {
        try {
            // Best-effort backend logout; ignore failures so user can still log out client-side.
            $this->api->post('/api/Auth/logout', []);
        } catch (\Throwable $e) {
            // Optionally log the error, but don't block logout.
        }

        Session::forget(['api_token', 'expires_in', 'user']);
        return redirect()->route('login');
    }
}
