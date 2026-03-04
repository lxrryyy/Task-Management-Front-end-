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
        return $this->client()->get($endpoint, $query)->throw()->json();
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->client()->post($endpoint, $data)->throw()->json();
    }

    public function put(string $endpoint, array $data = []): array
    {
        return $this->client()->put($endpoint, $data)->throw()->json();
    }

    public function delete(string $endpoint): array
    {
        return $this->client()->delete($endpoint)->throw()->json();
    }
}
