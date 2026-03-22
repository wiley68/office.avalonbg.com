<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Services\AdminNotesXlsxExportService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataExportController extends Controller
{
    public function index(): Response
    {
        $this->authorize('exportAll', Note::class);

        return Inertia::render('admin/DataExport', [
            'tables' => [
                [
                    'key' => 'notes',
                    'label' => 'notes',
                    'row_count' => Note::query()->count(),
                ],
            ],
        ]);
    }

    public function notes(AdminNotesXlsxExportService $exportService): BinaryFileResponse
    {
        $this->authorize('exportAll', Note::class);

        $tmpBase = tempnam(sys_get_temp_dir(), 'admin_notes_xlsx_');
        if ($tmpBase === false) {
            abort(500, 'Не може да се подготви временен файл за експорт.');
        }

        unlink($tmpBase);
        $absolutePath = $tmpBase.'.xlsx';

        $exportService->writeAllNotesToPath($absolutePath);

        $filename = 'belazhki-vsichki-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->download($absolutePath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
