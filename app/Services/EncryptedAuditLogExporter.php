<?php

namespace App\Services;

use App\Support\EncryptedSevenZipArchive;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EncryptedAuditLogExporter
{
    public function __construct(
        private readonly AuditLogsXlsxExportService $exportService,
        private readonly EncryptedSevenZipArchive $archive,
    ) {}

    public function download(string $dateFrom, string $dateTo, string $xlsxFilename, string $password): BinaryFileResponse
    {
        $relativeDir = 'temp/exports/'.Str::uuid()->toString();
        $safeXlsxFilename = basename($xlsxFilename);
        $relativePath = $relativeDir.'/'.$safeXlsxFilename;
        $workDir = Storage::disk('local')->path($relativeDir);

        Storage::disk('local')->makeDirectory($relativeDir);

        $xlsxPath = Storage::disk('local')->path($relativePath);

        $this->exportService->writeToFile($dateFrom, $dateTo, $xlsxPath);

        if (! is_file($xlsxPath)) {
            $this->removeDirectory($workDir);

            throw new \RuntimeException('Експортът не беше генериран успешно.');
        }

        $archiveFilename = pathinfo($safeXlsxFilename, PATHINFO_FILENAME).'.7z';
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
