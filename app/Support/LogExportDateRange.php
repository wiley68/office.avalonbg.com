<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LogExportDateRange
{
    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function apply(Builder $query, string $dateFrom, string $dateTo): Builder
    {
        return $query
            ->where('occurred_at', '>=', Carbon::parse($dateFrom)->startOfDay())
            ->where('occurred_at', '<=', Carbon::parse($dateTo)->endOfDay());
    }
}
