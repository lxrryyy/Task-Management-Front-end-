<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class CsharpApiService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.csharp_api.url');
        $this->apiKey  = config('services.csharp_api.key');
    }

    private function client()
    {
        $headers = [
            'X-Api-Key'    => $this->apiKey,
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if (Session::has('api_token')) {
            $token = (string) Session::get('api_token');
            $token = preg_replace('/^Bearer\s+/i', '', $token);
            $token = trim($token);
            if ($token !== '') {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
        }

        return Http::withHeaders($headers)->baseUrl($this->baseUrl);
    }

    public function get(string $endpoint, array $query = []): array
    {
        $result = $this->client()->get($endpoint, $query)->throw()->json();
        return is_array($result) ? $result : [];
    }

    public function post(string $endpoint, array $data = []): array
    {
        $result = $this->client()->post($endpoint, $data)->throw()->json();
        return is_array($result) ? $result : [];
    }

    public function patch(string $endpoint, array $data = []): array
    {
        $response = $this->client()->patch($endpoint, $data)->throw();
        // API returns 204 No Content on success (no body) — treat as success, return empty array
        if ($response->status() === 204) {
            return [];
        }
        $result = $response->json();
        return is_array($result) ? $result : [];
    }

    public function put(string $endpoint, array $data = []): array
    {
        $result = $this->client()->put($endpoint, $data)->throw()->json();
        return is_array($result) ? $result : [];
    }

    public function delete(string $endpoint): array
    {
        $result = $this->client()->delete($endpoint)->throw()->json();
        return is_array($result) ? $result : [];
    }
}
