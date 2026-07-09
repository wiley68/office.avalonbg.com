<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\LogExportDateRange;
use App\Support\Translations;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AuditLogsXlsxExportService
{
    public function writeToFile(string $dateFrom, string $dateTo, string $absolutePath): int
    {
        $rows = $this->buildRows($dateFrom, $dateTo);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Translations::get('audit_logs.export.sheet_title'));

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
            Translations::get('audit_logs.export.sheet_columns.id'),
            Translations::get('audit_logs.export.sheet_columns.occurred_at'),
            Translations::get('audit_logs.export.sheet_columns.event_type'),
            Translations::get('audit_logs.export.sheet_columns.event_source'),
            Translations::get('audit_logs.export.sheet_columns.is_success'),
            Translations::get('audit_logs.export.sheet_columns.user_name'),
            Translations::get('audit_logs.export.sheet_columns.user_email'),
            Translations::get('audit_logs.export.sheet_columns.field'),
            Translations::get('audit_logs.export.sheet_columns.initial_value'),
            Translations::get('audit_logs.export.sheet_columns.final_value'),
            Translations::get('audit_logs.export.sheet_columns.value'),
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
                        $log->is_success
                            ? Translations::get('audit_logs.success')
                            : Translations::get('audit_logs.failure'),
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
                        $log->is_success
                            ? Translations::get('audit_logs.success')
                            : Translations::get('audit_logs.failure'),
                        $log->user_name,
                        $log->user_email,
                        $this->fieldLabel($field),
                        $this->detailValue($field, $detail['начална_стойност'] ?? null),
                        $this->detailValue($field, $detail['крайна_стойност'] ?? null),
                        $this->detailValue($field, $detail['стойност'] ?? null),
                    ];
                });
            })
            ->values()
            ->all();

        return array_merge([$headings], $dataRows);
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'имейл' => Translations::get('audit_logs.fields.email'),
            'причина' => Translations::get('audit_logs.fields.reason'),
            default => $field,
        };
    }

    private function detailValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($field === 'причина' && is_string($value)) {
            return $this->reasonLabel($value);
        }

        return (string) $value;
    }

    private function reasonLabel(string $value): string
    {
        return match ($value) {
            'invalid_credentials', 'невалидни данни за вход' => Translations::get('audit_logs.reasons.invalid_credentials'),
            'invalid_mfa_code', 'невалиден MFA код' => Translations::get('audit_logs.reasons.invalid_mfa_code'),
            default => $value,
        };
    }
}
