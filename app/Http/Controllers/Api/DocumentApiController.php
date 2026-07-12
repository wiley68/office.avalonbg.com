<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DocumentApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,original_name,size_bytes,created_at',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $page = $validated['page'] ?? 1;
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = ($validated['sort_desc'] ?? 1) ? 'desc' : 'asc';
        $filter = $validated['search'] ?? '';

        $query = Document::query()
            ->where('user_id', $user->id)
            ->select([
                'id',
                'original_name',
                'mime_type',
                'size_bytes',
                'description',
                'created_at',
            ]);

        if ($filter !== '') {
            $query->where(function ($q) use ($filter) {
                $q->where('original_name', 'like', "%{$filter}%")
                    ->orWhere('description', 'like', "%{$filter}%");

                if (is_numeric($filter)) {
                    $q->orWhere('id', (int) $filter);
                }
            });
        }

        $documents = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($documents);
    }
}
