<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProfitTypeKind;
use App\Http\Controllers\Controller;
use App\Models\ProfitType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProfitTypeApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProfitType::class);

        $validated = $request->validate([
            'kind' => ['nullable', Rule::enum(ProfitTypeKind::class)],
        ]);

        $query = ProfitType::query()
            ->select(['id', 'name', 'kind'])
            ->orderBy('name');

        if (isset($validated['kind'])) {
            $query->where('kind', $validated['kind']);
        }

        return response()->json([
            'data' => $query->get()->map(static fn (ProfitType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'kind' => $type->kind->value,
            ]),
        ]);
    }
}
