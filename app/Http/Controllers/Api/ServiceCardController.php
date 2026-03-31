<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceCardRequest;
use App\Http\Requests\UpdateServiceCardRequest;
use App\Http\Resources\ServiceCardResource;
use App\Models\Member;
use App\Models\ServiceCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServiceCardController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:id,product,datecard,datepredavane,etap'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $query = ServiceCard::query()
            ->with(['contact', 'rakovoditel', 'serviseproblemtechnik', 'saobshtilclient']);

        if (! empty($validated['q'])) {
            $search = trim((string) $validated['q']);
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('product', 'like', "%{$search}%")
                    ->orWhere('problem', 'like', "%{$search}%")
                    ->orWhere('serviseproblem', 'like', "%{$search}%")
                    ->orWhere('clientopisanie', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($q) use ($search): void {
                        $q
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('second_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('firm', 'like', "%{$search}%");
                    })
                    ->orWhereHas('rakovoditel', fn ($q) => $q->where('username', 'like', "%{$search}%"))
                    ->orWhereHas('serviseproblemtechnik', fn ($q) => $q->where('username', 'like', "%{$search}%"))
                    ->orWhereHas('saobshtilclient', fn ($q) => $q->where('username', 'like', "%{$search}%"));
            });
        }

        $sortColumn = $validated['sort'] ?? 'id';
        $sortDirection = $validated['direction'] ?? 'desc';

        $query->orderBy($sortColumn, $sortDirection)->orderBy('id', 'desc');

        $perPage = (int) ($validated['per_page'] ?? 20);

        return ServiceCardResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    public function store(StoreServiceCardRequest $request): ServiceCardResource
    {
        $row = ServiceCard::query()->create($request->validated());

        return new ServiceCardResource(
            $row->load(['contact', 'rakovoditel', 'serviseproblemtechnik', 'saobshtilclient'])
        );
    }

    public function show(int $service_card): ServiceCardResource
    {
        $row = ServiceCard::query()
            ->with(['contact', 'rakovoditel', 'serviseproblemtechnik', 'saobshtilclient'])
            ->findOrFail($service_card);

        return new ServiceCardResource($row);
    }

    public function update(UpdateServiceCardRequest $request, int $service_card): ServiceCardResource
    {
        $row = ServiceCard::query()->findOrFail($service_card);
        $row->update($request->validated());

        return new ServiceCardResource(
            $row->fresh()->load(['contact', 'rakovoditel', 'serviseproblemtechnik', 'saobshtilclient'])
        );
    }

    public function destroy(int $service_card): Response
    {
        $row = ServiceCard::query()->findOrFail($service_card);
        $row->delete();

        return response()->noContent();
    }

    public function lookups(): Response
    {
        $members = Member::query()
            ->orderBy('username')
            ->get(['id', 'username']);

        return response([
            'members' => $members,
        ]);
    }
}
