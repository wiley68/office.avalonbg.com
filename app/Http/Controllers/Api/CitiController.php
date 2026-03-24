<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCitiRequest;
use App\Http\Requests\UpdateCitiRequest;
use App\Models\Citi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CitiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = Citi::query()->orderBy('name');

        if (! empty($validated['q'])) {
            $search = trim((string) $validated['q']);
            $query->where('name', 'like', "%{$search}%");
        }

        $limit = (int) ($validated['limit'] ?? 100);

        return response()->json([
            'data' => $query->limit($limit)->get(['id', 'name', 'postalcod']),
        ]);
    }

    public function store(StoreCitiRequest $request): JsonResponse
    {
        $row = Citi::query()->create($request->validated());

        return response()->json([
            'data' => $row->only(['id', 'name', 'postalcod']),
        ], 201);
    }

    public function update(UpdateCitiRequest $request, int $citi): JsonResponse
    {
        $row = Citi::query()->findOrFail($citi);
        $row->update($request->validated());

        return response()->json([
            'data' => $row->fresh()->only(['id', 'name', 'postalcod']),
        ]);
    }

    public function destroy(int $citi): Response
    {
        Citi::query()->findOrFail($citi)->delete();

        return response()->noContent();
    }
}
