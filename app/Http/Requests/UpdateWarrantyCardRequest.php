<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarrantyCardRequest extends FormRequest
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
            'client_id' => ['sometimes', 'required', 'integer', Rule::exists('service.contacts', 'id')],
            'date_sell' => ['sometimes', 'required', 'date'],
            'service' => ['sometimes', 'required', 'string', Rule::in(['в сервиз', 'при клиента'])],
            'obsluzvane' => ['sometimes', 'required', 'string', Rule::in(['4-8', '8-16', '8-32'])],
            'product' => ['sometimes', 'nullable', 'string', 'max:256'],
            'sernum' => ['sometimes', 'nullable', 'string', 'max:128'],
            'invoice' => ['sometimes', 'nullable', 'string', 'max:45'],
            'varanty_period' => ['sometimes', 'nullable', 'string', 'max:128'],
            'note' => ['sometimes', 'nullable', 'string'],
            'motherboard' => ['sometimes', 'nullable', 'string', 'max:128'],
            'processor' => ['sometimes', 'nullable', 'string', 'max:128'],
            'ram' => ['sometimes', 'nullable', 'string', 'max:128'],
            'psu' => ['sometimes', 'nullable', 'string', 'max:128'],
            'hdd1' => ['sometimes', 'nullable', 'string', 'max:128'],
            'hdd2' => ['sometimes', 'nullable', 'string', 'max:128'],
            'dvd' => ['sometimes', 'nullable', 'string', 'max:128'],
            'vga' => ['sometimes', 'nullable', 'string', 'max:128'],
            'lan' => ['sometimes', 'nullable', 'string', 'max:128'],
            'speackers' => ['sometimes', 'nullable', 'string', 'max:128'],
            'printer' => ['sometimes', 'nullable', 'string', 'max:128'],
            'monitor' => ['sometimes', 'nullable', 'string', 'max:128'],
            'kbd' => ['sometimes', 'nullable', 'string', 'max:128'],
            'mouse' => ['sometimes', 'nullable', 'string', 'max:128'],
            'other' => ['sometimes', 'nullable', 'string', 'max:128'],
            'iscomp' => ['sometimes', 'nullable', 'string', Rule::in(['Yes', 'No'])],
            'motherboardsn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'processorsn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'ramsn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'psusn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'hdd1sn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'hdd2sn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'dvdsn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'vgasn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'lansn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'speackerssn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'printersn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'monitorsn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'kbdsn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'mousesn' => ['sometimes', 'nullable', 'string', 'max:45'],
            'othersn' => ['sometimes', 'nullable', 'string', 'max:45'],
        ];
    }
}
