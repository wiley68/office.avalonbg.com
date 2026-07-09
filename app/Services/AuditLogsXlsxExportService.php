<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\LogExportDateRange;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AuditLogsXlsxExportService
{
    /**
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'имейл' => 'Имейл',
        'причина' => 'Причина',
    ];

    public function writeToFile(string $dateFrom, string $dateTo, string $absolutePath): int
    {
        $rows = $this->buildRows($dateFrom, $dateTo);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Одит');

        $sheet->fromArray($rows, null, 'A1');
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A:K')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);

        return max(0, count($rows) - 1);
    }

    /**
     * @return list<list<int|string|null>>
     */
    private function buildRows(string $dateFrom, string $dateTo): array
    {
        $headings = [
            '№',
            'Дата',
            'Събитие',
            'Източник',
            'Резултат',
            'Потребител',
            'Имейл',
            'Поле',
            'Начална стойност',
            'Крайна стойност',
            'Стойност',
        ];

        $dataRows = LogExportDateRange::apply(
            AuditLog::query(),
            $dateFrom,
            $dateTo,
        )
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get()
            ->flatMap(function (AuditLog $log): Collection {
                $details = json_decode($log->description ?? '[]', true);

                if (! is_array($details) || $details === []) {
                    return collect([[
                        $log->id,
                        $log->occurred_at?->format('d.m.Y H:i:s'),
                        $log->event_type->label(),
                        $log->event_source->label(),
                        $log->is_success ? 'Успех' : 'Неуспех',
                        $log->user_name,
                        $log->user_email,
                        '—',
                        '—',
                        '—',
                        '—',
                    ]]);
                }

                return collect($details)->map(function (array $detail) use ($log): array {
                    $field = $detail['поле'] ?? '';

                    return [
                        $log->id,
                        $log->occurred_at?->format('d.m.Y H:i:s'),
                        $log->event_type->label(),
                        $log->event_source->label(),
                        $log->is_success ? 'Успех' : 'Неуспех',
                        $log->user_name,
                        $log->user_email,
                        self::FIELD_LABELS[$field] ?? $field,
                        $detail['начална_стойност'] ?? '—',
                        $detail['крайна_стойност'] ?? '—',
                        $detail['стойност'] ?? '—',
                    ];
                });
            })
            ->values()
            ->all();

        return array_merge([$headings], $dataRows);
    }
}
