<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarrantyCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('service.contacts', 'id')],
            'date_sell' => ['required', 'date'],
            'service' => ['required', 'string', Rule::in(['в сервиз', 'при клиента'])],
            'obsluzvane' => ['required', 'string', Rule::in(['4-8', '8-16', '8-32'])],
            'product' => ['nullable', 'string', 'max:256'],
            'sernum' => ['nullable', 'string', 'max:128'],
            'invoice' => ['nullable', 'string', 'max:45'],
            'varanty_period' => ['nullable', 'string', 'max:128'],
            'note' => ['nullable', 'string'],
            'motherboard' => ['nullable', 'string', 'max:128'],
            'processor' => ['nullable', 'string', 'max:128'],
            'ram' => ['nullable', 'string', 'max:128'],
            'psu' => ['nullable', 'string', 'max:128'],
            'hdd1' => ['nullable', 'string', 'max:128'],
            'hdd2' => ['nullable', 'string', 'max:128'],
            'dvd' => ['nullable', 'string', 'max:128'],
            'vga' => ['nullable', 'string', 'max:128'],
            'lan' => ['nullable', 'string', 'max:128'],
            'speackers' => ['nullable', 'string', 'max:128'],
            'printer' => ['nullable', 'string', 'max:128'],
            'monitor' => ['nullable', 'string', 'max:128'],
            'kbd' => ['nullable', 'string', 'max:128'],
            'mouse' => ['nullable', 'string', 'max:128'],
            'other' => ['nullable', 'string', 'max:128'],
            'iscomp' => ['nullable', 'string', Rule::in(['Yes', 'No'])],
            'motherboardsn' => ['nullable', 'string', 'max:45'],
            'processorsn' => ['nullable', 'string', 'max:45'],
            'ramsn' => ['nullable', 'string', 'max:45'],
            'psusn' => ['nullable', 'string', 'max:45'],
            'hdd1sn' => ['nullable', 'string', 'max:45'],
            'hdd2sn' => ['nullable', 'string', 'max:45'],
            'dvdsn' => ['nullable', 'string', 'max:45'],
            'vgasn' => ['nullable', 'string', 'max:45'],
            'lansn' => ['nullable', 'string', 'max:45'],
            'speackerssn' => ['nullable', 'string', 'max:45'],
            'printersn' => ['nullable', 'string', 'max:45'],
            'monitorsn' => ['nullable', 'string', 'max:45'],
            'kbdsn' => ['nullable', 'string', 'max:45'],
            'mousesn' => ['nullable', 'string', 'max:45'],
            'othersn' => ['nullable', 'string', 'max:45'],
        ];
    }
}
