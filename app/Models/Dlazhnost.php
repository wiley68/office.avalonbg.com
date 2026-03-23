<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dlazhnost extends Model
{
    protected $connection = 'service';

    protected $table = 'dlaznosti';

    public $timestamps = false;
}
