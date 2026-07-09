<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

class EncryptedSevenZipArchive
{
    public function create(string $sourceFilePath, string $archivePath, string $password): void
    {
        if (! is_file($sourceFilePath)) {
            throw new RuntimeException('Изходният файл за архивиране не съществува.');
        }

        $binary = $this->resolveBinary();

        $archiveDirectory = dirname($archivePath);
        if (! is_dir($archiveDirectory) && ! mkdir($archiveDirectory, 0700, true) && ! is_dir($archiveDirectory)) {
            throw new RuntimeException('Неуспешно създаване на временна директория за архива.');
        }

        $process = new Process([
            $binary,
            'a',
            '-t7z',
            '-mhe=on',
            '-mx=5',
            '-p'.$password,
            $archivePath,
            $sourceFilePath,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Неуспешно създаване на криптиран 7z архив: '.trim($process->getErrorOutput() ?: $process->getOutput()),
            );
        }

        if (! is_file($archivePath)) {
            throw new RuntimeException('Криптираният архив не беше създаден.');
        }
    }

    public function isAvailable(): bool
    {
        try {
            $this->resolveBinary();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function resolveBinary(): string
    {
        $configured = (string) config('exports.seven_zip_binary', '7z');
        $candidates = array_values(array_unique([
            $configured,
            '7z',
            '7za',
            '/usr/bin/7z',
            '/usr/bin/7za',
        ]));

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }

            $resolved = trim((string) shell_exec('command -v '.escapeshellarg($candidate).' 2>/dev/null'));
            if ($resolved !== '' && is_executable($resolved)) {
                return $resolved;
            }
        }

        throw new RuntimeException(
            '7z не е наличен на сървъра. Инсталирайте p7zip-full и задайте SEVEN_ZIP_BINARY в .env.',
        );
    }
}
