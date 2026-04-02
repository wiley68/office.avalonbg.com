<?php

namespace App\Http\Controllers;

use App\Models\ServiceCard;
use Illuminate\Contracts\View\View;

class ServiceCardPrintReleaseController extends Controller
{
    public const RELEASE_ETAP = 'Приключен ремонт';

    public function __invoke(int $serviceCard): View
    {
        $card = ServiceCard::query()
            ->with(['contact.citi', 'rakovoditel', 'serviseproblemtechnik', 'soldProducts'])
            ->findOrFail($serviceCard);

        if ($card->etap !== self::RELEASE_ETAP) {
            abort(403, 'Печат при издаване е достъпен само при етап „'.self::RELEASE_ETAP.'".');
        }

        $contact = $card->contact;
        $clientLine = trim(implode(' ', array_filter([
            $contact?->name,
            $contact?->second_name,
            $contact?->last_name,
            $contact?->firm,
        ])));
        if ($clientLine === '') {
            $clientLine = '—';
        }

        $clientSignName = trim(implode(' ', array_filter([
            $contact?->name,
            $contact?->second_name,
            $contact?->last_name,
        ])));
        if ($clientSignName === '') {
            $clientSignName = '—';
        }

        $soldProducts = $card->soldProducts->sortBy('id')->values();
        $soldTotal = $soldProducts->sum(fn ($p): float => (float) $p->price);

        return view('service-cards.print-release', [
            'card' => $card,
            'contact' => $contact,
            'clientLine' => $clientLine,
            'clientSignName' => $clientSignName,
            'soldProducts' => $soldProducts,
            'soldTotal' => $soldTotal,
            'letterhead' => config('app.service_card_letterhead', []),
        ]);
    }
}
