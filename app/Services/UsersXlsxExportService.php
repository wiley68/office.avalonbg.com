<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\Translations;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UsersXlsxExportService
{
    public function writeToFile(User $actor, string $absolutePath): int
    {
        $rows = $this->buildRows($actor);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Translations::get('users.export.sheet_title'));

        $sheet->fromArray($rows, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A:F')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);

        return max(0, count($rows) - 1);
    }

    /**
     * @return list<list<int|string|null>>
     */
    public function buildRows(User $actor): array
    {
        $manageableRole = app(UserPolicy::class)->manageableRole($actor);

        $headings = [
            Translations::get('users.export.sheet_columns.id'),
            Translations::get('users.export.sheet_columns.name'),
            Translations::get('users.export.sheet_columns.email'),
            Translations::get('users.export.sheet_columns.role'),
            Translations::get('users.export.sheet_columns.status'),
            Translations::get('users.export.sheet_columns.created_at'),
        ];

        if ($manageableRole === null) {
            return [$headings];
        }

        $dataRows = User::query()
            ->role($manageableRole->value)
            ->select(['id', 'name', 'email', 'status', 'created_at'])
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => $this->mapUser($user, $manageableRole))
            ->values()
            ->all();

        return array_merge([$headings], $dataRows);
    }

    /**
     * @return list<int|string>
     */
    private function mapUser(User $user, UserRole $manageableRole): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $manageableRole->getLabel(),
            $user->isActive()
                ? Translations::get('users.fields.status_active')
                : Translations::get('users.fields.status_inactive'),
            $user->created_at?->format('d.m.Y H:i') ?? '',
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function exportableUsers(User $actor): Collection
    {
        $manageableRole = app(UserPolicy::class)->manageableRole($actor);

        if ($manageableRole === null) {
            return collect();
        }

        return User::query()
            ->role($manageableRole->value)
            ->select(['id', 'name', 'email', 'status', 'created_at'])
            ->orderBy('id')
            ->get();
    }
}
