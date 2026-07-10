<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UserApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        /** @var User $actor */
        $actor = Auth::user();
        $manageableRole = app(UserPolicy::class)->manageableRole($actor);

        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'page' => 'integer|min:1',
            'sort_by' => 'nullable|string|in:id,name,email,status,created_at',
            'sort_desc' => 'in:0,1',
            'search' => 'nullable|string|max:255',
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $page = $validated['page'] ?? 1;
        $sortBy = $validated['sort_by'] ?? 'id';
        $sortOrder = ($validated['sort_desc'] ?? 1) ? 'desc' : 'asc';
        $filter = $validated['search'] ?? '';

        $query = User::query()
            ->role($manageableRole?->value)
            ->select(['id', 'name', 'email', 'status', 'created_at']);

        if ($filter !== '') {
            $query->where(function ($q) use ($filter) {
                $q->where('name', 'like', "%{$filter}%")
                    ->orWhere('email', 'like', "%{$filter}%");

                if (is_numeric($filter)) {
                    $q->orWhere('id', (int) $filter);
                }
            });
        }

        $users = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($users);
    }
}
