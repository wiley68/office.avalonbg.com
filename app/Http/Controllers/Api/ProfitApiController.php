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
}
