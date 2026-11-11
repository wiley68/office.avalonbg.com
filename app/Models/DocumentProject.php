<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DocumentProject extends Pivot
{
    protected $table = 'document_project';

    public $incrementing = true;

    protected static function booted(): void
    {
        static::creating(function (DocumentProject $pivot): void {
            $pivot->group_id = Project::query()->findOrFail($pivot->project_id)->group_id;
        });
    }
}
