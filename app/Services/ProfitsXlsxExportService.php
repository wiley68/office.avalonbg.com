<?php

namespace App\Services;

use App\Models\User;
use App\Support\Translations;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProfitsXlsxExportService
{
    public function __construct(
        private readonly ProfitsExportDataBuilder $dataBuilder,
    ) {}

    public function download(
        User $user,
        string $dateFrom,
        string $dateTo,
        string $filename,
    ): BinaryFileResponse {
        $directory = storage_path('app/private/exports');
        File::ensureDirectoryExists($directory, 0700);
        $absolutePath = tempnam($directory, 'profits_');

        if ($absolutePath === false) {
            throw new RuntimeException('Unable to create export file.');
        }

        try {
            if (dirname($absolutePath) !== $directory) {
                throw new RuntimeException('Unable to create private export file.');
            }
            $this->writeToFile($user, $dateFrom, $dateTo, $absolutePath);
            clearstatcache(true, $absolutePath);

            return response()->download($absolutePath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ])->setPrivate()->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            File::delete($absolutePath);
            throw $exception;
        }
    }

    public function writeToFile(User $user, string $dateFrom, string $dateTo, string $absolutePath): int
    {
        $rows = $this->dataBuilder->sheetRows($user, $dateFrom, $dateTo);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder((new DefaultValueBinder)->setPreserveCr(true));
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Translations::get('profits.export.sheet_title'));
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $sheet->setCellValueExplicit(
                    [$columnIndex + 1, $rowIndex + 1],
                    $value,
                    $rowIndex > 0 && $columnIndex === 5 ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING,
                );
            }
        }
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A:F')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        $summaryStart = max(2, count($rows) - 2);

        foreach (range($summaryStart, count($rows)) as $rowNumber) {
            $sheet->getStyle('A'.$rowNumber.':F'.$rowNumber)->getFont()->setBold(true);
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        try {
            $writer->save($absolutePath);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return max(0, count($rows) - 4);
    }
}
