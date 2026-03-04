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
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        try {
            $response = $this->api->post('/api/Auth/login', [
                'email'    => $request->email,
                'password' => $request->password,
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
            $body   = $e->response->json();
            return back()->withErrors(['email' => 'Error: ' . json_encode($body)]);

        } catch (\Exception $e) {
            return back()->withErrors(['email' => 'Exception: ' . $e->getMessage()]);
        }
    }

    public function logout()
    {
        Session::forget(['api_token', 'user']);
        return redirect()->route('login');
    }
}
