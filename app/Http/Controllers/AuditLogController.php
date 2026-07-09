<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyAuditLogsRequest;
use App\Http\Requests\LogExportRequest;
use App\Models\AuditLog;
use App\Services\EncryptedAuditLogExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditLogController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        return Inertia::render('audit-logs/Index');
    }

    public function export(LogExportRequest $request, EncryptedAuditLogExporter $exporter): BinaryFileResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $validated = $request->validated();

        return $exporter->download(
            $validated['date_from'],
            $validated['date_to'],
            $validated['password'],
        );
    }

    public function destroy(AuditLog $auditLog): RedirectResponse
    {
        Gate::authorize('delete', $auditLog);

        $auditLog->delete();

        return back();
    }

    public function destroyBulk(DestroyAuditLogsRequest $request): RedirectResponse
    {
        Gate::authorize('deleteAny', AuditLog::class);

        AuditLog::query()
            ->whereIn('id', $request->validated('ids'))
            ->delete();

        return back();
    }
}
