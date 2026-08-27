<?php

namespace App\Services;

use App\Models\User;
use App\Support\Translations;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProfitsXlsxExportService
{
    public function __construct(
        private readonly ProfitsExportDataBuilder $dataBuilder,
    ) {}

    public function writeToFile(User $user, string $dateFrom, string $dateTo, string $absolutePath): int
    {
        $rows = $this->dataBuilder->sheetRows($user, $dateFrom, $dateTo);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Translations::get('profits.export.sheet_title'));
        $sheet->fromArray($rows, null, 'A1');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A:E')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        $summaryStart = max(2, count($rows) - 2);

        foreach (range($summaryStart, count($rows)) as $rowNumber) {
            $sheet->getStyle('A'.$rowNumber.':E'.$rowNumber)->getFont()->setBold(true);
        }

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);

        return max(0, count($rows) - 4);
    }
}
