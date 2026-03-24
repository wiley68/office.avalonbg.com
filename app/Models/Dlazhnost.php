<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class Dlazhnost extends Model
{
    protected $connection = 'service';

    protected $table = 'dlaznosti';

    public $timestamps = false;
}
