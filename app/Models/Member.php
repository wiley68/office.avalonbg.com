<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $connection = 'service';

    protected $table = 'members';

    public $timestamps = false;
}
