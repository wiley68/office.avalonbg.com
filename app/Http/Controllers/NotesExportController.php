<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Services\NotesXlsxExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class NotesExportController extends Controller
{
    public function __invoke(Request $request, NotesXlsxExportService $exportService): JsonResponse
    {
        $this->authorize('viewAny', Note::class);

        try {
            $result = $exportService->createPendingDownload($request->user());
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : 'Неуспешно генериране на Excel файл.',
            ], 500);
        }

        return response()->json($result);
    }
}
