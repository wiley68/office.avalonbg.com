<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        Document $document,
        DocumentStorageService $documentStorageService,
    ): StreamedResponse {
        $this->authorize('download', $document);

        return $documentStorageService->download(
            $document,
            $request->boolean('download'),
        );
    }
}
