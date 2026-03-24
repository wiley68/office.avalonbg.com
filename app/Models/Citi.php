<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'postalcod'])]
class Citi extends Model
{
    protected $connection = 'service';

    protected $table = 'citi';

    public $timestamps = false;
}
