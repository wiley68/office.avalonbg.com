<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDlazhnostRequest;
use App\Http\Requests\UpdateDlazhnostRequest;
use App\Models\Dlazhnost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DlazhnostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = Dlazhnost::query()->orderBy('name');

        if (! empty($validated['q'])) {
            $search = trim((string) $validated['q']);
            $query->where('name', 'like', "%{$search}%");
        }

        $limit = (int) ($validated['limit'] ?? 100);

        return response()->json([
            'data' => $query->limit($limit)->get(['id', 'name']),
        ]);
    }

    public function store(StoreDlazhnostRequest $request): JsonResponse
    {
        $row = Dlazhnost::query()->create($request->validated());

        return response()->json([
            'data' => $row->only(['id', 'name']),
        ], 201);
    }

    public function update(UpdateDlazhnostRequest $request, int $dlazhnosti): JsonResponse
    {
        $row = Dlazhnost::query()->findOrFail($dlazhnosti);
        $row->update($request->validated());

        return response()->json([
            'data' => $row->fresh()->only(['id', 'name']),
        ]);
    }

    public function destroy(int $dlazhnosti): Response
    {
        Dlazhnost::query()->findOrFail($dlazhnosti)->delete();

        return response()->noContent();
    }
}
