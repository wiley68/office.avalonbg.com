<?php

namespace App\Http\Controllers;

use App\Models\Warranty;
use Illuminate\Contracts\View\View;

class WarrantyCardPrintController extends Controller
{
    public function __invoke(int $warranty): View
    {
        $card = Warranty::query()
            ->with('contact')
            ->findOrFail($warranty);

        $contact = $card->contact;
        $fullName = trim(implode(' ', array_filter([
            $contact?->name,
            $contact?->second_name,
            $contact?->last_name,
        ])));

        return view('warranties.print', [
            'card' => $card,
            'contact' => $contact,
            'fullName' => $fullName,
            'serviceInShop' => $card->service === 'в сервиз',
            'serviceAtClient' => $card->service === 'при клиента',
            'response48' => $card->obsluzvane === '4-8',
            'response816' => $card->obsluzvane === '8-16',
            'response832' => $card->obsluzvane === '8-32',
        ]);
    }
}
