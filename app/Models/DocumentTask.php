<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DocumentTask extends Pivot
{
    protected $table = 'document_task';

    public $incrementing = true;

    protected static function booted(): void
    {
        static::creating(function (DocumentTask $pivot): void {
            $pivot->group_id = Task::query()->findOrFail($pivot->task_id)->group_id;
        });
    }
}
