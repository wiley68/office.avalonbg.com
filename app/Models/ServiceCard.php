<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'rakovoditel_id',
    'datecard',
    'name',
    'special',
    'product',
    'varanty',
    'problem',
    'serviseproblem',
    'serviseproblemtechnik_id',
    'dopclient',
    'datepredavane',
    'saobshtilclient_id',
    'clientopisanie',
    'etap',
])]
class ServiceCard extends Model
{
    protected $connection = 'service';

    protected $table = 'projects';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'datecard' => 'datetime',
            'datepredavane' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'name');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function rakovoditel(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'rakovoditel_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function serviseproblemtechnik(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'serviseproblemtechnik_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function saobshtilclient(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'saobshtilclient_id');
    }

    /**
     * @return HasMany<ServiceCardProduct, $this>
     */
    public function soldProducts(): HasMany
    {
        return $this->hasMany(ServiceCardProduct::class, 'project_id');
    }
}
