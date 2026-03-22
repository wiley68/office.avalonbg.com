<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NotesExportDownloadController extends Controller
{
    /**
     * Еднократно изтегляне на XLSX, генериран от ExportNotesToXlsxTool.
     */
    public function __invoke(Request $request, string $token): BinaryFileResponse
    {
        if (! Str::isUuid($token)) {
            abort(404);
        }

        $cacheKey = 'notes_export:'.$token;
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
            abort(404);
        }

        if (is_array($cached)) {
            $userId = (int) ($cached['user_id'] ?? 0);
            $path = $cached['path'] ?? null;
        } else {
            $userId = (int) $cached;
            $path = storage_path('app/private/notes-exports/'.$token.'.xlsx');
        }

        if ($userId !== (int) $request->user()->id || ! is_string($path) || $path === '') {
            abort(404);
        }

        if (! is_file($path)) {
            Cache::forget($cacheKey);
            abort(404);
        }

        Cache::forget($cacheKey);

        $filename = 'belazhki-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
