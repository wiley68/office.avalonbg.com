<?php

namespace App\Support;

class LogExportFilename
{
    public static function auditLogs(string $dateFrom, string $dateTo): string
    {
        return 'audit_logs_'.$dateFrom.'_'.$dateTo.'_'.now()->format('H-i-s').'.xlsx';
    }

    public static function archiveFromXlsx(string $xlsxFilename): string
    {
        return pathinfo(basename($xlsxFilename), PATHINFO_FILENAME).'.7z';
    }
}
