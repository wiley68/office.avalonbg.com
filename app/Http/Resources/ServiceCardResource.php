<?php

namespace App\Http\Resources;

use App\Models\Contact;
use App\Models\Member;
use App\Models\ServiceCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceCard
 */
class ServiceCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rakovoditel_id' => $this->rakovoditel_id,
            'datecard' => $this->datecard?->format('Y-m-d H:i:s'),
            'name' => $this->name,
            'special' => $this->special,
            'product' => $this->product,
            'varanty' => $this->varanty,
            'problem' => $this->problem,
            'serviseproblem' => $this->serviseproblem,
            'serviseproblemtechnik_id' => $this->serviseproblemtechnik_id,
            'dopclient' => $this->dopclient,
            'datepredavane' => $this->datepredavane?->format('Y-m-d H:i:s'),
            'saobshtilclient_id' => $this->saobshtilclient_id,
            'clientopisanie' => $this->clientopisanie,
            'etap' => $this->etap,
            'contact_label' => $this->whenLoaded('contact', fn () => $this->contactLabel($this->contact)),
            'rakovoditel_label' => $this->whenLoaded('rakovoditel', fn () => $this->memberLabel($this->rakovoditel)),
            'serviseproblemtechnik_label' => $this->whenLoaded('serviseproblemtechnik', fn () => $this->memberLabel($this->serviseproblemtechnik)),
            'saobshtilclient_label' => $this->whenLoaded('saobshtilclient', fn () => $this->memberLabel($this->saobshtilclient)),
            'sold_products' => ServiceCardProductResource::collection(
                $this->whenLoaded('soldProducts')
            ),
        ];
    }

    private function contactLabel(?Contact $contact): ?string
    {
        if ($contact === null) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            (string) $contact->name,
            (string) $contact->second_name,
            (string) $contact->last_name,
        ])));
        $parts = array_filter([(string) $contact->firm, $name !== '' ? $name : null]);
        $label = implode(' — ', $parts);

        return $label !== '' ? $label : '#'.$contact->id;
    }

    private function memberLabel(?Member $member): ?string
    {
        if ($member === null) {
            return null;
        }

        return $member->username !== '' ? $member->username : '#'.$member->id;
    }
}
