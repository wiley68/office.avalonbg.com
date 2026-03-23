<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Citi extends Model
{
    protected $connection = 'service';

    protected $table = 'citi';

    public $timestamps = false;
}
