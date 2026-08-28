<?php

namespace App\Services;

use App\Enums\ProfitTypeKind;
use App\Models\ProfitEntry;
use App\Models\User;
use App\Support\Translations;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProfitsExportDataBuilder
{
    /**
     * @return array{
     *     date_from: string,
     *     date_to: string,
     *     income: Collection<int, ProfitEntry>,
     *     expense: Collection<int, ProfitEntry>,
     *     totals: array{income: string, expense: string, result: string}
     * }
     */
    public function build(User $user, string $dateFrom, string $dateTo): array
    {
        $from = Carbon::parse($dateFrom)->toDateString();
        $to = Carbon::parse($dateTo)->toDateString();

        $entries = ProfitEntry::query()
            ->with(['profitType:id,name,kind'])
            ->where('user_id', $user->id)
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $income = $entries
            ->filter(fn (ProfitEntry $entry): bool => $entry->profitType?->kind === ProfitTypeKind::Income)
            ->values();

        $expense = $entries
            ->filter(fn (ProfitEntry $entry): bool => $entry->profitType?->kind === ProfitTypeKind::Expense)
            ->values();

        $incomeTotal = (float) $income->sum('amount');
        $expenseTotal = (float) $expense->sum('amount');

        return [
            'date_from' => $from,
            'date_to' => $to,
            'income' => $income,
            'expense' => $expense,
            'totals' => [
                'income' => number_format($incomeTotal, 2, '.', ''),
                'expense' => number_format($expenseTotal, 2, '.', ''),
                'result' => number_format($incomeTotal - $expenseTotal, 2, '.', ''),
            ],
        ];
    }

    /**
     * @return list<list<int|string|null>>
     */
    public function sheetRows(User $user, string $dateFrom, string $dateTo): array
    {
        $data = $this->build($user, $dateFrom, $dateTo);

        $headings = [
            Translations::get('profits.export.sheet_columns.date'),
            Translations::get('profits.export.sheet_columns.document_number'),
            Translations::get('profits.export.sheet_columns.description'),
            Translations::get('profits.export.sheet_columns.kind'),
            Translations::get('profits.export.sheet_columns.type'),
            Translations::get('profits.export.sheet_columns.amount'),
        ];

        $mapRows = static function (Collection $entries, ProfitTypeKind $kind): array {
            return $entries->map(static function (ProfitEntry $entry) use ($kind): array {
                return [
                    $entry->date?->format('Y-m-d'),
                    $entry->document_number,
                    $entry->description,
                    Translations::get('profits.kinds.'.$kind->value),
                    $entry->profitType?->name,
                    (string) $entry->amount,
                ];
            })->all();
        };

        $rows = array_merge(
            [$headings],
            $mapRows($data['income'], ProfitTypeKind::Income),
            $mapRows($data['expense'], ProfitTypeKind::Expense),
        );

        $rows[] = [
            Translations::get('profits.summary.income'),
            '',
            '',
            '',
            '',
            $data['totals']['income'],
        ];
        $rows[] = [
            Translations::get('profits.summary.expense'),
            '',
            '',
            '',
            '',
            $data['totals']['expense'],
        ];
        $rows[] = [
            Translations::get('profits.summary.result'),
            '',
            '',
            '',
            '',
            $data['totals']['result'],
        ];

        return $rows;
    }
}
