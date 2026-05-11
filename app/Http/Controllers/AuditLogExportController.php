<?php

namespace App\Http\Controllers;

use App\Services\AuditLogApiService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;

class AuditLogExportController extends Controller
{
    public function __construct(private readonly AuditLogApiService $auditApi) {}

    public function exportExcel(Request $request): Response
    {
        return $this->stream($request, AuditLogApiService::KIND_ACTION, AuditLogApiService::FORMAT_EXCEL,
            'audit-logs.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function exportPdf(Request $request): Response
    {
        return $this->stream($request, AuditLogApiService::KIND_ACTION, AuditLogApiService::FORMAT_PDF,
            'audit-logs.pdf', 'application/pdf');
    }

    public function exportLoginLogoutExcel(Request $request): Response
    {
        return $this->stream($request, AuditLogApiService::KIND_LOGIN_LOGOUT, AuditLogApiService::FORMAT_EXCEL,
            'login-logout-logs.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function exportLoginLogoutPdf(Request $request): Response
    {
        return $this->stream($request, AuditLogApiService::KIND_LOGIN_LOGOUT, AuditLogApiService::FORMAT_PDF,
            'login-logout-logs.pdf', 'application/pdf');
    }

    private function stream(Request $request, string $kind, string $format, string $defaultFileName, string $defaultContentType): Response
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $filter = array_filter([
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $resp = $this->auditApi->export($kind, $format, $filter);

        return response($resp->body(), $resp->status())
            ->withHeaders([
                'Content-Type' => $resp->header('Content-Type') ?: $defaultContentType,
                'Content-Disposition' => $resp->header('Content-Disposition') ?: 'attachment; filename="'.$defaultFileName.'"',
            ]);
    }

    private function ensureAdmin(): void
    {
        $user = Session::get('user', []);
        $role = mb_strtolower(trim((string) ($user['role'] ?? $user['Role'] ?? $user['roleName'] ?? $user['RoleName'] ?? '')));
        abort_if($role !== 'admin', 403);
        abort_if((int) ($user['id'] ?? $user['Id'] ?? 0) <= 0, 401);
    }
}
