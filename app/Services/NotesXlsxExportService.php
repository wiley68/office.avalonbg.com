<?php

namespace App\Services;

use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NotesXlsxExportService
{
    /**
     * Записва XLSX с всички бележки на потребителя. Полето `note` се записва като обикновен текст (моделът декриптира).
     *
     * @return int Брой редове с данни (без заглавния ред)
     */
    public function writeExportFile(User $user, string $absolutePath): int
    {
        $notes = $user->notes()->orderBy('id')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Бележки');

        $rows = [
            ['ID', 'Име', 'Кратко описание', 'Бележка', 'Създадена', 'Обновена'],
        ];

        foreach ($notes as $note) {
            $rows[] = [
                $note->id,
                $note->name,
                $note->description,
                $note->note,
                $note->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                $note->updated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            ];
        }

        $sheet->fromArray($rows, null, 'A1', true);

        $lastRow = max(1, $sheet->getHighestRow());
        if ($lastRow >= 2) {
            $sheet->getStyle('D2:D'.$lastRow)->getAlignment()->setWrapText(true);
            $sheet->getStyle('D2:D'.$lastRow)->getAlignment()->setVertical(
                Alignment::VERTICAL_TOP,
            );
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);

        return $notes->count();
    }
}
