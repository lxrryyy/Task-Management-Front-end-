<?php

namespace App\Http\Controllers;

use App\Services\StickyNoteApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class StickyNoteController extends Controller
{
    public function __construct(private readonly StickyNoteApiService $notesApi) {}

    private function accountId(): int
    {
        $user = Session::get('user', null);
        return (int) ($user['id'] ?? $user['Id'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $note
     * @return array<string, mixed>
     */
    private function normalise(array $note): array
    {
        return [
            'id' => (int) ($note['id'] ?? $note['Id'] ?? 0),
            'content' => (string) ($note['content'] ?? $note['Content'] ?? ''),
            'isPinned' => (bool) ($note['isPinned'] ?? $note['IsPinned'] ?? false),
            'createdAt' => (string) ($note['createdAt'] ?? $note['CreatedAt'] ?? ''),
            'updatedAt' => (string) ($note['updatedAt'] ?? $note['UpdatedAt'] ?? $note['createdAt'] ?? $note['CreatedAt'] ?? ''),
        ];
    }

    public function index(): JsonResponse
    {
        if ($this->accountId() <= 0) {
            return response()->json([]);
        }

        try {
            $list = $this->notesApi->list();
            $notes = array_values(array_map(fn ($n) => $this->normalise((array) $n), $list));
            return response()->json($notes);
        } catch (\Throwable $e) {
            Log::warning('StickyNote list failed', ['message' => $e->getMessage()]);
            return response()->json([]);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:500',
            'isPinned' => 'boolean',
        ]);

        $created = $this->notesApi->create(
            (string) $validated['content'],
            (bool) ($validated['isPinned'] ?? false),
        );

        $note = !empty($created['id']) || !empty($created['Id'])
            ? $this->normalise($created)
            : array_merge(
                $this->normalise($created),
                [
                    'content' => (string) $validated['content'],
                    'isPinned' => (bool) ($validated['isPinned'] ?? false),
                    'createdAt' => now()->toIso8601String(),
                    'updatedAt' => now()->toIso8601String(),
                ]
            );

        return response()->json($note, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'sometimes|string|max:500',
            'isPinned' => 'sometimes|boolean',
        ]);

        $updated = $this->notesApi->update($id, $validated);

        $note = !empty($updated['id']) || !empty($updated['Id'])
            ? $this->normalise($updated)
            : array_merge(
                ['id' => $id],
                $validated,
                ['updatedAt' => now()->toIso8601String()],
            );

        return response()->json($note);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->notesApi->delete($id);
        return response()->json(['deleted' => true]);
    }
}
