<?php

namespace App\Support;

class ProfitExportFilename
{
    public static function make(string $dateFrom, string $dateTo, string $extension): string
    {
        return sprintf(
            'profits_%s_%s_%s.%s',
            $dateFrom,
            $dateTo,
            now()->format('H-i-s'),
            ltrim($extension, '.'),
        );
    }
}
