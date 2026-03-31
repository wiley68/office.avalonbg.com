<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceCardProductRequest;
use App\Http\Requests\UpdateServiceCardProductRequest;
use App\Http\Resources\ServiceCardProductResource;
use App\Models\ServiceCard;
use App\Models\ServiceCardProduct;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServiceCardProductController extends Controller
{
    public function index(int $service_card): AnonymousResourceCollection
    {
        $card = ServiceCard::query()->findOrFail($service_card);
        $rows = $card->soldProducts()->orderBy('id')->get();

        return ServiceCardProductResource::collection($rows);
    }

    public function store(StoreServiceCardProductRequest $request, int $service_card): ServiceCardProductResource
    {
        ServiceCard::query()->findOrFail($service_card);

        $row = ServiceCardProduct::query()->create(
            array_merge($request->validated(), ['project_id' => $service_card]),
        );

        return new ServiceCardProductResource($row);
    }

    public function update(
        UpdateServiceCardProductRequest $request,
        int $service_card,
        int $service_card_product
    ): ServiceCardProductResource {
        ServiceCard::query()->findOrFail($service_card);

        $row = ServiceCardProduct::query()
            ->where('project_id', $service_card)
            ->findOrFail($service_card_product);

        $row->update($request->validated());

        return new ServiceCardProductResource($row->fresh());
    }

    public function destroy(int $service_card, int $service_card_product): Response
    {
        ServiceCard::query()->findOrFail($service_card);

        $row = ServiceCardProduct::query()
            ->where('project_id', $service_card)
            ->findOrFail($service_card_product);

        $row->delete();

        return response()->noContent();
    }
}
