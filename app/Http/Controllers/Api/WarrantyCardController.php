<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarrantyCardRequest;
use App\Http\Requests\UpdateWarrantyCardRequest;
use App\Http\Resources\WarrantyResource;
use App\Models\Warranty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WarrantyCardController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:id,product,date_sell'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Warranty::query()->with('contact');

        if (! empty($validated['q'])) {
            $search = trim((string) $validated['q']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('product', 'like', "%{$search}%")
                    ->orWhere('sernum', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($q) use ($search): void {
                        $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('second_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('firm', 'like', "%{$search}%");
                    });
            });
        }

        $sortColumn = $validated['sort'] ?? 'id';
        $sortDirection = $validated['direction'] ?? 'desc';

        $query->orderBy($sortColumn, $sortDirection)->orderBy('id', 'desc');

        $perPage = (int) ($validated['per_page'] ?? 20);

        return WarrantyResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    public function store(StoreWarrantyCardRequest $request): WarrantyResource
    {
        $row = Warranty::query()->create($request->validated());

        return new WarrantyResource($row->load('contact'));
    }

    public function show(int $warranty_card): WarrantyResource
    {
        $row = Warranty::query()->with('contact')->findOrFail($warranty_card);

        return new WarrantyResource($row);
    }

    public function update(UpdateWarrantyCardRequest $request, int $warranty_card): WarrantyResource
    {
        $row = Warranty::query()->findOrFail($warranty_card);
        $row->update($request->validated());

        return new WarrantyResource($row->fresh()->load('contact'));
    }

    public function destroy(int $warranty_card): Response
    {
        $row = Warranty::query()->findOrFail($warranty_card);
        $row->delete();

        return response()->noContent();
    }
}
