<?php

namespace App\Http\Controllers;

use App\Models\ServiceCard;
use Illuminate\Contracts\View\View;

class ServiceCardPrintController extends Controller
{
    public function __invoke(int $serviceCard): View
    {
        $card = ServiceCard::query()
            ->with(['contact', 'rakovoditel', 'serviseproblemtechnik', 'saobshtilclient'])
            ->findOrFail($serviceCard);

        $contact = $card->contact;
        $fullName = trim(implode(' ', array_filter([
            $contact?->name,
            $contact?->second_name,
            $contact?->last_name,
        ])));

        return view('service-cards.print', [
            'card' => $card,
            'contact' => $contact,
            'fullName' => $fullName,
        ]);
    }
}
