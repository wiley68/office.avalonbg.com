<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 */
class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'citi_id' => $this->citi_id,
            'citi_name' => $this->whenLoaded('citi', fn () => $this->citi?->name),
            'eik' => $this->eik,
            'info' => $this->info,
            'name' => $this->name,
            'second_name' => $this->second_name,
            'last_name' => $this->last_name,
            'dlaznosti_id' => $this->dlaznosti_id,
            'dlazhnost_name' => $this->whenLoaded('dlazhnost', fn () => $this->dlazhnost?->name),
            'gsm_1_m' => $this->gsm_1_m,
            'gsm_2_g' => $this->gsm_2_g,
            'gsm_3_v' => $this->gsm_3_v,
            'tel1' => $this->tel1,
            'tel2' => $this->tel2,
            'fax' => $this->fax,
            'email' => $this->email,
            'web' => $this->web,
            'address' => $this->address,
            'b_phone' => $this->b_phone,
            'b_email' => $this->b_email,
            'b_im' => $this->b_im,
            'im' => $this->im,
            'note' => $this->note,
            'firm' => $this->firm,
        ];
    }
}
