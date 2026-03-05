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
        $response = $this->client()->post($endpoint, $data)->throw();

        // Many endpoints return 204 or an empty/non-JSON body on success.
        if ($response->status() === 204) {
            return [];
        }

        $body = $response->body();
        if (!is_string($body) || trim($body) === '') {
            return [];
        }

        try {
            $result = $response->json();
            return is_array($result) ? $result : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function patch(string $endpoint, array $data = []): array
    {
        $response = $this->client()->patch($endpoint, $data)->throw();
        // 204 No Content — success but no body
        if ($response->status() === 204) {
            return [];
        }
        $body = $response->body();
        if (!is_string($body) || trim($body) === '') {
            return [];
        }
        try {
            $result = $response->json();
            return is_array($result) ? $result : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Extract field-mapped errors from a C# API error response.
     *
     * Returns a keyed array suitable for Laravel's withErrors():
     *   [
     *     'startDate' => ['Start date must be a valid date.'],
     *     'name'      => ['Project name is required.'],
     *     'api_error' => ['Request could not be processed.'],
     *   ]
     *
     * Handles ASP.NET Core ValidationProblemDetails and plain message shapes.
     */
    public function extractFieldErrors(\Illuminate\Http\Client\Response $response): array
    {
        if ($response->successful()) {
            return [];
        }

        $body      = $response->json() ?? [];
        $fieldMap  = [];

        // Map JSON paths / .NET field names → Laravel form field names
        $pathToField = [
            'startDate'   => 'startDate',
            'StartDate'   => 'startDate',
            '$.startDate' => 'startDate',
            'endDate'     => 'endDate',
            'EndDate'     => 'endDate',
            '$.endDate'   => 'endDate',
            'name'        => 'name',
            'Name'        => 'name',
            '$.name'      => 'name',
            'title'       => 'name',
            'Title'       => 'name',
            '$.title'     => 'name',
            'description' => 'description',
            'Description' => 'description',
            'assigneeIds' => 'assigneeIds',
            'AssigneeIds' => 'assigneeIds',
            'memberIds'   => 'memberIds',
            'MemberIds'   => 'memberIds',
            'priority'    => 'priorityId',
            'Priority'    => 'priorityId',
            'priorityId'  => 'priorityId',
            'PriorityId'  => 'priorityId',
            'storyPoints' => 'storyPoints',
            'StoryPoints' => 'storyPoints',
            'projectId'   => 'api_error',
            'ProjectId'   => 'api_error',
        ];

        // Human-readable translations for common .NET technical messages
        $translate = function (string $field, string $raw): string {
            if (stripos($raw, 'could not be converted to System.DateTime') !== false
                || stripos($raw, 'was not recognized as a valid DateTime') !== false) {
                $labels = ['startDate' => 'Start date', 'endDate' => 'End date'];
                return ($labels[$field] ?? ucfirst($field)) . ' must be a valid date (e.g. 2026-03-05).';
            }
            if (stripos($raw, 'is required') !== false) {
                $labels = [
                    'name'        => 'Project name',
                    'startDate'   => 'Start date',
                    'endDate'     => 'End date',
                    'memberIds'   => 'Members',
                    'assigneeIds' => 'Assignees',
                    'description' => 'Description',
                ];
                return ($labels[$field] ?? ucfirst($field)) . ' is required.';
            }
            // Strip internal .NET path noise (Path: $.x | LineNumber: 0 | BytePosition...)
            $clean = preg_replace('/\s*Path:\s*\S+\s*\|\s*LineNumber:.*$/i', '', $raw);
            return trim($clean ?: $raw);
        };

        // ASP.NET Core ValidationProblemDetails: { errors: { "Field": ["msg"], "$.field": ["msg"] } }
        if (!empty($body['errors']) && is_array($body['errors'])) {
            foreach ($body['errors'] as $rawField => $msgs) {
                // Resolve field name; strip leading $. for path-style keys
                $normalised = ltrim($rawField, '$.');
                $formField  = $pathToField[$rawField] ?? $pathToField[$normalised] ?? 'api_error';

                foreach ((array) $msgs as $msg) {
                    if (is_string($msg) && $msg !== '') {
                        $translated = $translate($formField, $msg);
                        if ($translated !== '') {
                            $fieldMap[$formField][] = $translated;
                        }
                    }
                }
            }
        }

        // Top-level message / detail (add to api_error unless already have field-specific errors for context)
        foreach (['message', 'Message', 'detail', 'Detail', 'error', 'Error'] as $key) {
            if (!empty($body[$key]) && is_string($body[$key])
                && stripos($body[$key], 'one or more validation') === false) {
                $translated = $translate('api_error', $body[$key]);
                if ($translated !== '') {
                    $fieldMap['api_error'][] = $translated;
                }
                break;
            }
        }

        // Fallback: "dto field is required" → generic human message
        if (!empty($body['errors']['dto']) || !empty($body['errors']['Dto'])) {
            $fieldMap['api_error'][] = 'The request could not be processed. Please check all fields and try again.';
        }

        if (empty($fieldMap)) {
            $raw = $response->body();
            $fieldMap['api_error'][] = (is_string($raw) && strlen($raw) < 300)
                ? strip_tags($raw)
                : 'An error occurred (HTTP ' . $response->status() . '). Please try again.';
        }

        // Deduplicate each field's messages
        return array_map(fn ($msgs) => array_values(array_unique($msgs)), $fieldMap);
    }

    /** Flat array of all error strings (used for logging). */
    public function extractErrors(\Illuminate\Http\Client\Response $response): array
    {
        $fieldErrors = $this->extractFieldErrors($response);
        return array_values(array_merge(...array_values($fieldErrors)));
    }

    /** @deprecated Use extractFieldErrors() */
    public function extractError(\Illuminate\Http\Client\Response $response): ?string
    {
        $errors = $this->extractErrors($response);
        return $errors ? implode(' ', $errors) : null;
    }

    public function put(string $endpoint, array $data = []): array
    {
        $response = $this->client()->put($endpoint, $data)->throw();
        if ($response->status() === 204) {
            return [];
        }
        $body = $response->body();
        if (!is_string($body) || trim($body) === '') {
            return [];
        }
        try {
            $result = $response->json();
            return is_array($result) ? $result : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function delete(string $endpoint): array
    {
        $response = $this->client()->delete($endpoint)->throw();
        if ($response->status() === 204) {
            return [];
        }
        $body = $response->body();
        if (!is_string($body) || trim($body) === '') {
            return [];
        }
        try {
            $result = $response->json();
            return is_array($result) ? $result : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
