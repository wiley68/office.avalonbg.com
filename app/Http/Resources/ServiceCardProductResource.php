<?php

namespace App\Http\Resources;

use App\Models\ServiceCardProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceCardProduct
 */
class ServiceCardProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (string) $this->price,
            'project_id' => $this->project_id,
            'vat' => $this->vat,
            'broi' => $this->broi,
            'ed_cena' => (string) $this->ed_cena,
        ];
    }
}
