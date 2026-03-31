<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'price',
    'project_id',
    'vat',
    'broi',
    'ed_cena',
])]
class ServiceCardProduct extends Model
{
    protected $connection = 'service';

    protected $table = 'ceni';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'ed_cena' => 'decimal:2',
            'broi' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ServiceCard, $this>
     */
    public function serviceCard(): BelongsTo
    {
        return $this->belongsTo(ServiceCard::class, 'project_id');
    }
}
