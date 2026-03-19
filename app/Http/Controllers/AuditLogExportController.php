<?php

namespace App\Http\Controllers;

use App\Services\CsharpApiService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;

class AuditLogExportController extends Controller
{
    private function ensureAdmin(): void
    {
        $user = Session::get('user', []);
        $role = mb_strtolower(trim((string) ($user['role'] ?? $user['Role'] ?? $user['roleName'] ?? $user['RoleName'] ?? '')));
        abort_if($role !== 'admin', 403);
    }

    private function requesterId(): int
    {
        $user = Session::get('user', []);
        return (int) ($user['id'] ?? $user['Id'] ?? 0);
    }

    private function exportQuery(Request $request, int $requesterId): array
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        $q = ['requesterId' => $requesterId];
        if (!empty($data['from'])) $q['from'] = $data['from'];
        if (!empty($data['to']))   $q['to']   = $data['to'];
        return $q;
    }

    public function exportExcel(Request $request, CsharpApiService $api): Response
    {
        $this->ensureAdmin();
        $requesterId = $this->requesterId();
        abort_if($requesterId <= 0, 401);

        $resp = $api->rawGet('/api/AuditLog/ExportExcel', $this->exportQuery($request, $requesterId));

        return response($resp->body(), $resp->status())
            ->withHeaders([
                'Content-Type' => $resp->header('Content-Type') ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => $resp->header('Content-Disposition') ?? 'attachment; filename="audit-logs.xlsx"',
            ]);
    }

    public function exportPdf(Request $request, CsharpApiService $api): Response
    {
        $this->ensureAdmin();
        $requesterId = $this->requesterId();
        abort_if($requesterId <= 0, 401);

        $resp = $api->rawGet('/api/AuditLog/ExportPdf', $this->exportQuery($request, $requesterId));

        return response($resp->body(), $resp->status())
            ->withHeaders([
                'Content-Type' => $resp->header('Content-Type') ?? 'application/pdf',
                'Content-Disposition' => $resp->header('Content-Disposition') ?? 'attachment; filename="audit-logs.pdf"',
            ]);
    }
}

