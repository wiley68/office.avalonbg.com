<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NotesXlsxExportService
{
    /**
     * Подготвя еднократно изтегляне на XLSX с всички бележки на потребителя.
     *
     * @return array{
     *     ok: true,
     *     note_count: int,
     *     download_url: string,
     *     filename: string,
     * }
     */
    public function createPendingDownload(User $user): array
    {
        $token = (string) Str::uuid();

        $tmpBase = tempnam(sys_get_temp_dir(), 'notes_xlsx_');
        if ($tmpBase === false) {
            throw new \RuntimeException('Не може да се подготви временен файл за експорт (temp директория).');
        }

        unlink($tmpBase);
        $absolutePath = $tmpBase.'.xlsx';

        $count = $this->writeExportFile($user, $absolutePath);

        Cache::put(
            'notes_export:'.$token,
            [
                'user_id' => $user->id,
                'path' => $absolutePath,
            ],
            now()->addMinutes(30),
        );

        $downloadUrl = route('dashboard.notes.export.download', ['token' => $token], true);
        $filename = 'belazhki-'.now()->format('Y-m-d-His').'.xlsx';

        return [
            'ok' => true,
            'note_count' => $count,
            'download_url' => $downloadUrl,
            'filename' => $filename,
        ];
    }

    /**
     * Записва XLSX с всички бележки на потребителя.
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
