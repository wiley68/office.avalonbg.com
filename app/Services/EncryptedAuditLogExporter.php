<?php

namespace App\Services;

use App\Support\EncryptedSevenZipArchive;
use App\Support\LogExportFilename;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EncryptedAuditLogExporter
{
    public function __construct(
        private readonly AuditLogsXlsxExportService $exportService,
        private readonly EncryptedSevenZipArchive $archive,
    ) {}

    public function download(string $dateFrom, string $dateTo, string $password): BinaryFileResponse
    {
        $xlsxFilename = LogExportFilename::auditLogs($dateFrom, $dateTo);
        $workDir = $this->createWorkDirectory();
        $xlsxPath = $workDir.'/'.$xlsxFilename;

        $this->exportService->writeToFile($dateFrom, $dateTo, $xlsxPath);

        if (! is_file($xlsxPath)) {
            $this->removeDirectory($workDir);

            throw new \RuntimeException('Експортът не беше генериран успешно.');
        }

        $archiveFilename = LogExportFilename::archiveFromXlsx($xlsxFilename);
        $archivePath = $workDir.'/'.$archiveFilename;

        try {
            $this->archive->create($xlsxPath, $archivePath, $password);
        } catch (\Throwable $exception) {
            $this->removeDirectory($workDir);

            throw $exception;
        }

        unlink($xlsxPath);

        register_shutdown_function(fn () => $this->removeDirectory($workDir));

        return response()->download($archivePath, $archiveFilename, [
            'Content-Type' => 'application/x-7z-compressed',
        ])->deleteFileAfterSend(true);
    }

    private function createWorkDirectory(): string
    {
        $workDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .'office-export-'
            .Str::uuid()->toString();

        if (! @mkdir($workDir, 0775, true) && ! is_dir($workDir)) {
            throw new \RuntimeException('Неуспешно създаване на временна директория за експорт.');
        }

        return $workDir;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
