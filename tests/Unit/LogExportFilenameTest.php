<?php

use App\Support\LogExportFilename;

test('audit log export filename matches uni format', function () {
    $filename = LogExportFilename::auditLogs('2026-06-09', '2026-07-09');

    expect($filename)->toMatch('/^audit_logs_2026-06-09_2026-07-09_\d{2}-\d{2}-\d{2}\.xlsx$/')
        ->and(LogExportFilename::archiveFromXlsx($filename))
        ->toMatch('/^audit_logs_2026-06-09_2026-07-09_\d{2}-\d{2}-\d{2}\.7z$/');
});
