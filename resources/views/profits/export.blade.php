<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        .meta { margin-bottom: 18px; color: #444; }
        h2 { font-size: 14px; margin: 18px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f3f3; }
        td.num, th.num { text-align: right; }
        .summary { margin-top: 16px; width: 50%; }
        .empty { color: #777; margin: 0 0 8px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        {{ $labels['period'] }}: {{ $dateFrom }} – {{ $dateTo }}
    </div>

    <h2>{{ $labels['income'] }}</h2>
    @if ($income->isEmpty())
        <p class="empty">{{ $labels['emptyIncome'] }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ $labels['date'] }}</th>
                    <th>{{ $labels['documentNumber'] }}</th>
                    <th>{{ $labels['type'] }}</th>
                    <th class="num">{{ $labels['amount'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($income as $entry)
                    <tr>
                        <td>{{ $entry->date?->format('Y-m-d') }}</td>
                        <td>{{ $entry->document_number ?: '—' }}</td>
                        <td>{{ $entry->profitType?->name }}</td>
                        <td class="num">{{ number_format((float) $entry->amount, 2, '.', '') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>{{ $labels['expense'] }}</h2>
    @if ($expense->isEmpty())
        <p class="empty">{{ $labels['emptyExpense'] }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ $labels['date'] }}</th>
                    <th>{{ $labels['documentNumber'] }}</th>
                    <th>{{ $labels['type'] }}</th>
                    <th class="num">{{ $labels['amount'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($expense as $entry)
                    <tr>
                        <td>{{ $entry->date?->format('Y-m-d') }}</td>
                        <td>{{ $entry->document_number ?: '—' }}</td>
                        <td>{{ $entry->profitType?->name }}</td>
                        <td class="num">{{ number_format((float) $entry->amount, 2, '.', '') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="summary">
        <tr>
            <td>{{ $labels['totalIncome'] }}</td>
            <td class="num">{{ $totals['income'] }}</td>
        </tr>
        <tr>
            <td>{{ $labels['totalExpense'] }}</td>
            <td class="num">{{ $totals['expense'] }}</td>
        </tr>
        <tr>
            <td><strong>{{ $labels['result'] }}</strong></td>
            <td class="num"><strong>{{ $totals['result'] }}</strong></td>
        </tr>
    </table>
</body>
</html>
