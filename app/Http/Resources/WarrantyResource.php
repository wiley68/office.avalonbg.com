<?php

namespace App\Http\Resources;

use App\Models\Contact;
use App\Models\Warranty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Warranty
 */
class WarrantyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->product,
            'sernum' => $this->sernum,
            'client_id' => $this->client_id,
            'date_sell' => $this->date_sell?->format('Y-m-d H:i:s'),
            'invoice' => $this->invoice,
            'varanty_period' => $this->varanty_period,
            'service' => $this->service,
            'obsluzvane' => $this->obsluzvane,
            'note' => $this->note,
            'motherboard' => $this->motherboard,
            'processor' => $this->processor,
            'ram' => $this->ram,
            'psu' => $this->psu,
            'hdd1' => $this->hdd1,
            'hdd2' => $this->hdd2,
            'dvd' => $this->dvd,
            'vga' => $this->vga,
            'lan' => $this->lan,
            'speackers' => $this->speackers,
            'printer' => $this->printer,
            'monitor' => $this->monitor,
            'kbd' => $this->kbd,
            'mouse' => $this->mouse,
            'other' => $this->other,
            'iscomp' => $this->iscomp,
            'motherboardsn' => $this->motherboardsn,
            'processorsn' => $this->processorsn,
            'ramsn' => $this->ramsn,
            'psusn' => $this->psusn,
            'hdd1sn' => $this->hdd1sn,
            'hdd2sn' => $this->hdd2sn,
            'dvdsn' => $this->dvdsn,
            'vgasn' => $this->vgasn,
            'lansn' => $this->lansn,
            'speackerssn' => $this->speackerssn,
            'printersn' => $this->printersn,
            'monitorsn' => $this->monitorsn,
            'kbdsn' => $this->kbdsn,
            'mousesn' => $this->mousesn,
            'othersn' => $this->othersn,
            'client_label' => $this->whenLoaded('contact', fn () => $this->clientLabel($this->contact)),
        ];
    }

    private function clientLabel(?Contact $contact): ?string
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
}
