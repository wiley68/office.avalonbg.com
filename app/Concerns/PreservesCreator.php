<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

trait PreservesCreator
{
    protected static function bootPreservesCreator(): void
    {
        static::updating(function (Model $model): void {
            if ($model->isDirty('user_id')) {
                throw new LogicException('Creator attribution cannot be changed.');
            }
        });
    }
}
