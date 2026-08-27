<?php

namespace App\Services;

use App\Models\User;
use App\Support\Translations;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ProfitsPdfExportService
{
    public function __construct(
        private readonly ProfitsExportDataBuilder $dataBuilder,
    ) {}

    public function download(User $user, string $dateFrom, string $dateTo, string $filename): Response
    {
        $data = $this->dataBuilder->build($user, $dateFrom, $dateTo);

        $pdf = Pdf::loadView('profits.export', [
            'title' => Translations::get('profits.export.pdf_title'),
            'dateFrom' => $data['date_from'],
            'dateTo' => $data['date_to'],
            'income' => $data['income'],
            'expense' => $data['expense'],
            'totals' => $data['totals'],
            'labels' => [
                'period' => Translations::get('profits.summary.period'),
                'income' => Translations::get('profits.income'),
                'expense' => Translations::get('profits.expense'),
                'totalIncome' => Translations::get('profits.summary.income'),
                'totalExpense' => Translations::get('profits.summary.expense'),
                'result' => Translations::get('profits.summary.result'),
                'date' => Translations::get('profits.columns.date'),
                'documentNumber' => Translations::get('profits.columns.document_number'),
                'type' => Translations::get('profits.columns.type'),
                'amount' => Translations::get('profits.columns.amount'),
                'emptyIncome' => Translations::get('profits.empty_income'),
                'emptyExpense' => Translations::get('profits.empty_expense'),
            ],
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
