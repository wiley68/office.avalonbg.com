<?php

namespace App\Services;

use App\Models\Note;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AdminNotesXlsxExportService
{
    /**
     * Пълен експорт на всички бележки за всички потребители (административен).
     *
     * @return int Брой редове с данни (без заглавния ред)
     */
    public function writeAllNotesToPath(string $absolutePath): int
    {
        $notes = Note::query()
            ->with(['user:id,name'])
            ->orderBy('user_id')
            ->orderBy('id')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Бележки');

        $rows = [
            [
                'Потребител ИД',
                'Потребител',
                'ID',
                'Име',
                'Кратко описание',
                'Бележка',
                'Създадена',
                'Обновена',
            ],
        ];

        foreach ($notes as $note) {
            $rows[] = [
                $note->user_id,
                $note->user?->name ?? '',
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
            $sheet->getStyle('F2:F'.$lastRow)->getAlignment()->setWrapText(true);
            $sheet->getStyle('F2:F'.$lastRow)->getAlignment()->setVertical(
                Alignment::VERTICAL_TOP,
            );
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);

        return $notes->count();
    }
}
