<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class StickyNoteController extends Controller
{
    public function __construct(
        private readonly CsharpApiService $api
    ) {}

    // ── resolve authenticated account ID ─────────────────────────────────────
    private function accountId(): int
    {
        $user = Session::get('user', null);
        return (int) ($user['id'] ?? $user['Id'] ?? 0);
    }

    // ── normalise a single note from whatever shape the API returns ───────────
    private function normalise(array $note): array
    {
        return [
            'id'        => (int)    ($note['id']        ?? $note['Id']        ?? 0),
            'content'   => (string) ($note['content']   ?? $note['Content']   ?? $note['text'] ?? $note['Text'] ?? ''),
            'isPinned'  => (bool)   ($note['isPinned']  ?? $note['IsPinned']  ?? false),
            'createdAt' => (string) ($note['createdAt'] ?? $note['CreatedAt'] ?? $note['createdDate'] ?? ''),
            'updatedAt' => (string) ($note['updatedAt'] ?? $note['UpdatedAt'] ?? $note['modifiedDate'] ?? $note['createdAt'] ?? $note['CreatedAt'] ?? ''),
        ];
    }

    /**
     * GET /notes
     * Returns all sticky notes for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $accountId = $this->accountId();

        if ($accountId <= 0) {
            return response()->json([]);
        }

        try {
            $raw = $this->api->get("/api/StickyNote/GetMyNotes/{$accountId}");
            // C# API returns a top-level array; or a wrapper like { data: [...] }
            $list = [];
            if (is_array($raw)) {
                if (isset($raw['data'])) {
                    $list = $raw['data'];
                } elseif (isset($raw['notes'])) {
                    $list = $raw['notes'];
                } elseif (isset($raw['items'])) {
                    $list = $raw['items'];
                } elseif (array_is_list($raw)) {
                    $list = $raw;
                }
            }
            $notes = array_values(array_map(fn ($n) => $this->normalise($n), (array) $list));
            return response()->json($notes);
        } catch (\Throwable $e) {
            Log::warning('StickyNote GetMyNotes failed', [
                'accountId' => $accountId,
                'message'   => $e->getMessage(),
                'url'       => config('services.csharp_api.url') . "/api/StickyNote/GetMyNotes/{$accountId}",
            ]);
            return response()->json([]);
        }
    }

    /**
     * POST /notes
     * Body: { content: string, isPinned?: bool }
     */
    public function store(Request $request): JsonResponse
    {
        $accountId = $this->accountId();

        $validated = $request->validate([
            'content'  => 'required|string|max:500',
            'isPinned' => 'boolean',
        ]);

        $raw = $this->api->post("/api/StickyNote/CreateNote/{$accountId}", [
            'content'  => $validated['content'],
            'isPinned' => $validated['isPinned'] ?? false,
        ]);

        // Some APIs echo back the created object; others return minimal data.
        // Fall back to a synthetic response using the content we sent.
        $note = !empty($raw['id']) || !empty($raw['Id'])
            ? $this->normalise($raw)
            : array_merge(
                $this->normalise($raw),
                [
                    'content'   => $validated['content'],
                    'isPinned'  => $validated['isPinned'] ?? false,
                    'createdAt' => now()->toIso8601String(),
                    'updatedAt' => now()->toIso8601String(),
                ]
              );

        return response()->json($note, 201);
    }

    /**
     * PATCH /notes/{id}
     * Body: { content?: string, isPinned?: bool }
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $accountId = $this->accountId();

        $validated = $request->validate([
            'content'  => 'sometimes|string|max:500',
            'isPinned' => 'sometimes|boolean',
        ]);

        $raw = $this->api->patch(
            "/api/StickyNote/UpdateNote/{$id}?accountId={$accountId}",
            $validated
        );

        $note = !empty($raw['id']) || !empty($raw['Id'])
            ? $this->normalise($raw)
            : array_merge(
                ['id' => $id],
                $validated,
                ['updatedAt' => now()->toIso8601String()]
              );

        return response()->json($note);
    }

    /**
     * DELETE /notes/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $accountId = $this->accountId();

        $this->api->delete("/api/StickyNote/DeleteNote/{$id}?accountId={$accountId}");

        return response()->json(['deleted' => true]);
    }
}
