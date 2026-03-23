<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Note::class);

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:name,created_at,updated_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $query = $request->user()->notes()->getQuery();

        if (! empty($validated['q'])) {
            $search = trim((string) $validated['q']);

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%");
            });
        }

        $sortColumn = $validated['sort'] ?? 'updated_at';
        $sortDirection = $validated['direction'] ?? 'desc';

        $query
            ->orderBy($sortColumn, $sortDirection)
            ->orderBy('id', 'desc');

        $perPage = (int) ($validated['per_page'] ?? 12);
        $notes = $query->paginate($perPage)->withQueryString();

        return NoteResource::collection($notes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreNoteRequest $request): NoteResource
    {
        $this->authorize('create', Note::class);

        $note = $request->user()->notes()->create($request->validated());

        return new NoteResource($note);
    }

    /**
     * Display the specified resource.
     */
    public function show(Note $note): NoteResource
    {
        $this->authorize('view', $note);

        return new NoteResource($note);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateNoteRequest $request, Note $note): NoteResource
    {
        $this->authorize('update', $note);

        $note->update($request->validated());

        return new NoteResource($note->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Note $note): Response
    {
        $this->authorize('delete', $note);

        $note->delete();

        return response()->noContent();
    }
}
