<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Document::class);

        return Inertia::render('documents/Index');
    }

    public function store(
        StoreDocumentRequest $request,
        DocumentStorageService $documentStorageService,
    ): RedirectResponse {
        $this->authorize('create', Document::class);

        /** @var User $user */
        $user = Auth::user();

        $documentStorageService->store(
            $user,
            $request->file('file'),
            $request->validated('description'),
        );

        return back();
    }

    public function update(
        UpdateDocumentRequest $request,
        Document $document,
    ): RedirectResponse {
        $this->authorize('update', $document);

        $document->update($request->validated());

        return back();
    }

    public function destroy(
        Document $document,
        DocumentStorageService $documentStorageService,
    ): RedirectResponse {
        $this->authorize('delete', $document);

        $documentStorageService->delete($document);

        return back();
    }
}
