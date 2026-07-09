<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit-logs:prune';

    protected $description = 'Изтрива одит записи по-стари от конфигурирания период на съхранение';

    public function handle(): int
    {
        $years = config('retention.audit_logs_years');
        $threshold = now()->subYears($years);

        $deleted = AuditLog::query()
            ->where('occurred_at', '<', $threshold)
            ->delete();

        $this->info("Изтрити {$deleted} одит записа по-стари от {$years} година(и).");

        return self::SUCCESS;
    }
}
