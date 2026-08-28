<?php

namespace App\Services;

use App\Models\User;
use App\Support\Translations;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        $directory = storage_path('app/temp');
        File::ensureDirectoryExists($directory);

        $absolutePath = $directory . DIRECTORY_SEPARATOR . $filename;

        $this->writeToFile($user, $dateFrom, $dateTo, $absolutePath);

        return response()
            ->download($absolutePath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function writeToFile(User $user, string $dateFrom, string $dateTo, string $absolutePath): int
    {
        $rows = $this->dataBuilder->sheetRows($user, $dateFrom, $dateTo);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Translations::get('profits.export.sheet_title'));
        $sheet->fromArray($rows, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A:F')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        $summaryStart = max(2, count($rows) - 2);

        foreach (range($summaryStart, count($rows)) as $rowNumber) {
            $sheet->getStyle('A' . $rowNumber . ':F' . $rowNumber)->getFont()->setBold(true);
        }

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);

        return max(0, count($rows) - 4);
    }
}
