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

        $notes = $request->user()
            ->notes()
            ->latest()
            ->get();

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
