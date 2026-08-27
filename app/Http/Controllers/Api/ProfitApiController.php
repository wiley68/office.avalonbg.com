<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProfitTypeKind;
use App\Http\Controllers\Controller;
use App\Models\ProfitEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProfitApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProfitEntry::class);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        try {
            $monthStart = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'month' => ['The month must be a valid YYYY-MM value.'],
            ]);
        }

        $monthEnd = $monthStart->copy()->endOfMonth();

        $entries = ProfitEntry::query()
            ->with(['profitType:id,name,kind'])
            ->where('user_id', $user->id)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $mapEntry = static function (ProfitEntry $entry): array {
            return [
                'id' => $entry->id,
                'date' => $entry->date?->format('Y-m-d'),
                'document_number' => $entry->document_number,
                'amount' => (string) $entry->amount,
                'profit_type_id' => $entry->profit_type_id,
                'type' => [
                    'id' => $entry->profitType?->id,
                    'name' => $entry->profitType?->name,
                    'kind' => $entry->profitType?->kind?->value,
                ],
            ];
        };

        $income = $entries
            ->filter(fn (ProfitEntry $entry): bool => $entry->profitType?->kind === ProfitTypeKind::Income)
            ->values()
            ->map($mapEntry);

        $expense = $entries
            ->filter(fn (ProfitEntry $entry): bool => $entry->profitType?->kind === ProfitTypeKind::Expense)
            ->values()
            ->map($mapEntry);

        $incomeTotal = $income->sum(fn (array $row): float => (float) $row['amount']);
        $expenseTotal = $expense->sum(fn (array $row): float => (float) $row['amount']);

        return response()->json([
            'month' => $validated['month'],
            'period' => [
                'from' => $monthStart->toDateString(),
                'to' => $monthEnd->toDateString(),
            ],
            'income' => $income,
            'expense' => $expense,
            'totals' => [
                'income' => number_format($incomeTotal, 2, '.', ''),
                'expense' => number_format($expenseTotal, 2, '.', ''),
                'result' => number_format($incomeTotal - $expenseTotal, 2, '.', ''),
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProfitEntry::class);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100', 'required_without_all:date_from,date_to'],
            'date_from' => ['nullable', 'date', 'required_with:date_to', 'required_without:year'],
            'date_to' => ['nullable', 'date', 'required_with:date_from', 'required_without:year', 'after_or_equal:date_from'],
        ]);

        if (isset($validated['year'])) {
            $periodStart = Carbon::create((int) $validated['year'], 1, 1)->startOfDay();
            $periodEnd = $periodStart->copy()->endOfYear();
        } else {
            $periodStart = Carbon::parse($validated['date_from'])->startOfMonth()->startOfDay();
            $periodEnd = Carbon::parse($validated['date_to'])->endOfMonth()->endOfDay();
        }

        $entries = ProfitEntry::query()
            ->with(['profitType:id,name,kind'])
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->get(['id', 'date', 'amount', 'profit_type_id']);

        /** @var array<string, array{income: float, expense: float}> $byMonth */
        $byMonth = [];

        foreach ($entries as $entry) {
            $monthKey = $entry->date?->format('Y-m');

            if ($monthKey === null) {
                continue;
            }

            $byMonth[$monthKey] ??= [
                'income' => 0.0,
                'expense' => 0.0,
            ];

            $amount = (float) $entry->amount;

            if ($entry->profitType?->kind === ProfitTypeKind::Income) {
                $byMonth[$monthKey]['income'] += $amount;
            } else {
                $byMonth[$monthKey]['expense'] += $amount;
            }
        }

        $months = [];
        $incomeTotal = 0.0;
        $expenseTotal = 0.0;

        $cursor = $periodStart->copy()->startOfMonth();
        $endMonth = $periodEnd->copy()->startOfMonth();

        while ($cursor->lte($endMonth)) {
            $monthKey = $cursor->format('Y-m');
            $income = $byMonth[$monthKey]['income'] ?? 0.0;
            $expense = $byMonth[$monthKey]['expense'] ?? 0.0;
            $result = $income - $expense;

            $months[] = [
                'month' => $monthKey,
                'income' => number_format($income, 2, '.', ''),
                'expense' => number_format($expense, 2, '.', ''),
                'result' => number_format($result, 2, '.', ''),
            ];

            $incomeTotal += $income;
            $expenseTotal += $expense;
            $cursor->addMonth();
        }

        return response()->json([
            'period' => [
                'from' => $periodStart->toDateString(),
                'to' => $periodEnd->toDateString(),
            ],
            'months' => $months,
            'totals' => [
                'income' => number_format($incomeTotal, 2, '.', ''),
                'expense' => number_format($expenseTotal, 2, '.', ''),
                'result' => number_format($incomeTotal - $expenseTotal, 2, '.', ''),
            ],
        ]);
    }
}
