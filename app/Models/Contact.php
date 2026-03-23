<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'citi_id',
    'eik',
    'info',
    'name',
    'second_name',
    'last_name',
    'dlaznosti_id',
    'gsm_1_m',
    'gsm_2_g',
    'gsm_3_v',
    'tel1',
    'tel2',
    'fax',
    'email',
    'web',
    'address',
    'b_phone',
    'b_email',
    'b_im',
    'im',
    'note',
    'firm',
])]
class Contact extends Model
{
    protected $connection = 'service';

    protected $table = 'contacts';

    public $timestamps = false;

    /**
     * @return BelongsTo<Citi, $this>
     */
    public function citi(): BelongsTo
    {
        return $this->belongsTo(Citi::class, 'citi_id');
    }

    /**
     * @return BelongsTo<Dlazhnost, $this>
     */
    public function dlazhnost(): BelongsTo
    {
        return $this->belongsTo(Dlazhnost::class, 'dlaznosti_id');
    }
}
