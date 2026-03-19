<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Services\CsharpApiService;
use Illuminate\Http\Client\RequestException;

class ApiAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!Session::has('api_token')) {
            return redirect()->route('login');
        }

        // Optional: if backend is offline or token is revoked, clear session and force login.
        try {
            /** @var CsharpApiService $api */
            $api = app(CsharpApiService::class);
            // Validate token and, if needed, refresh the user from /api/Auth/me.
            $user = Session::get('user');
            if (!$user) {
                $fetched = $api->get('/api/Auth/me');
                if (is_array($fetched) && !empty($fetched)) {
                    Session::put('user', $fetched);
                }
            } else {
                // Light ping to ensure token is still valid.
                $api->get('/api/Auth/me');
            }
        } catch (RequestException $e) {
            $status = $e->response?->status();
            if (in_array($status, [401, 403], true)) {
                // Token invalid or revoked on backend
                Session::forget(['api_token', 'expires_in', 'user']);
                return redirect()->route('login');
            }
            // For 404/500 etc. we just continue; the controller will handle specifics.
        } catch (\Exception $e) {
            // Network / DNS / connection error → treat as logged out for safety
            Session::forget(['api_token', 'expires_in', 'user']);
            return redirect()->route('login');
        }

        return $next($request);
    }
}
