<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product',
    'sernum',
    'client_id',
    'date_sell',
    'invoice',
    'varanty_period',
    'service',
    'obsluzvane',
    'note',
    'motherboard',
    'processor',
    'ram',
    'psu',
    'hdd1',
    'hdd2',
    'dvd',
    'vga',
    'lan',
    'speackers',
    'printer',
    'monitor',
    'kbd',
    'mouse',
    'other',
    'iscomp',
    'motherboardsn',
    'processorsn',
    'ramsn',
    'psusn',
    'hdd1sn',
    'hdd2sn',
    'dvdsn',
    'vgasn',
    'lansn',
    'speackerssn',
    'printersn',
    'monitorsn',
    'kbdsn',
    'mousesn',
    'othersn',
])]
class Warranty extends Model
{
    protected $connection = 'service';

    protected $table = 'varanty';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_sell' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'client_id');
    }
}
