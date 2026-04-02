<!DOCTYPE html>
<html lang="bg">

<head>
    <meta charset="utf-8">
    <title>Издаване СК № {{ str_pad((string) $card->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page {
            margin: 12mm 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Calibri, 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #111;
            margin: 0;
            padding: 0;
        }

        .sc-muted {
            color: #555;
        }

        .sc-small {
            font-size: 8pt;
        }

        .sc-9 {
            font-size: 9pt;
        }

        .sc-italic {
            font-style: italic;
        }

        .sc-bold {
            font-weight: 700;
        }

        .sc-right {
            text-align: right;
        }

        .sc-center {
            text-align: center;
        }

        table.sc-letterhead {
            width: 100%;
            max-width: 640px;
            border-collapse: collapse;
            margin: 0 0 10pt;
            border-bottom: 1.5pt solid #000;
        }

        table.sc-letterhead td {
            padding: 4pt 6pt;
            vertical-align: top;
            border: none;
        }

        .sc-letterhead-logo {
            width: 34%;
            text-align: center;
        }

        .sc-letterhead-logo img {
            max-width: 200px;
            max-height: 50px;
            height: auto;
        }

        .sc-logo-ph {
            display: inline-block;
            min-width: 160px;
            min-height: 44px;
            line-height: 44px;
            border: 1pt dashed #ccc;
            color: #999;
            font-size: 9pt;
        }

        table.sc-frame {
            width: 100%;
            max-width: 640px;
            border-collapse: collapse;
            margin: 0 0 8pt;
            border: 2.25pt double #000;
        }

        table.sc-frame td {
            border: 2.25pt double #000;
            padding: 5pt 6pt;
            vertical-align: top;
        }

        table.sc-grid {
            width: 100%;
            max-width: 640px;
            border-collapse: collapse;
            margin: 0 0 8pt;
            border: 0.5pt solid #aeaaaa;
        }

        table.sc-grid td {
            border: 0.5pt solid #aeaaaa;
            padding: 4pt 6pt;
            vertical-align: top;
        }

        table.sc-products {
            width: 100%;
            max-width: 700px;
            border-collapse: collapse;
            margin: 0 0 8pt;
            border: 0.5pt solid #aeaaaa;
        }

        table.sc-products th,
        table.sc-products td {
            border: 0.5pt solid #aeaaaa;
            padding: 4pt 6pt;
            vertical-align: top;
            font-size: 10pt;
        }

        table.sc-products th {
            font-weight: 700;
        }

        .sc-label {
            font-weight: normal;
        }

        .sc-legal {
            margin: 10pt 0;
            max-width: 640px;
        }

        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    @php
        $paddedId = str_pad((string) $card->id, 6, '0', STR_PAD_LEFT);
        $datecard = $card->datecard?->format('d.m.Y') ?? '—';
        $datepredavane = $card->datepredavane?->format('d.m.Y') ?? '—';
        $specialBold = $card->special === 'Спешна поръчка';
        $warrantyBold = $card->varanty === 'Гаранционен';
        $logoUrl = isset($letterhead['logo_url']) ? trim((string) $letterhead['logo_url']) : '';
        $vatSuffix = static function (string $vat): string {
            return $vat === 'Yes' ? ' с ДДС' : ' без ДДС';
        };
    @endphp

    <table class="sc-letterhead" role="presentation">
        <tr>
            <td class="sc-letterhead-logo">
                @if ($logoUrl !== '')
                    <img src="{{ $logoUrl }}" alt="">
                @else
                    <span class="sc-logo-ph">Лого</span>
                @endif
            </td>
            <td rowspan="2">
                <p class="sc-center sc-bold" style="margin:0 0 4pt;font-size:11pt;">
                    {{ $letterhead['company_name'] ?? 'Авалон ООД' }}
                </p>
                <p class="sc-9" style="margin:0;">
                    {!! nl2br(e($letterhead['address_lines'] ?? '')) !!}
                </p>
                <p class="sc-center sc-muted" style="margin:6pt 0 0;font-size:11pt;">
                    {{ $letterhead['website'] ?? '' }}
                </p>
            </td>
        </tr>
        <tr>
            <td class="sc-center sc-small sc-italic sc-muted">
                {{ $letterhead['tagline'] ?? '' }}
            </td>
        </tr>
    </table>

    <table class="sc-frame" role="presentation">
        <tr>
            <td style="width:32%">
                <span class="sc-muted">СК № </span><span class="sc-bold">{{ $paddedId }}</span>
            </td>
            <td style="width:34%">
                <span class="sc-bold">{{ $datecard }}</span>
            </td>
            <td style="width:34%" class="sc-right sc-small sc-italic sc-muted">
                {{ config('app.name') }} · издаване
            </td>
        </tr>
    </table>

    <table class="sc-grid" role="presentation">
        <tr>
            <td style="width:11%" class="sc-label">Приел</td>
            <td style="width:37%" class="sc-italic">{{ $card->rakovoditel?->username ?? '—' }}</td>
            <td style="width:11%" class="sc-label">Клиент</td>
            <td style="width:41%" class="sc-italic">{{ $clientLine }}</td>
        </tr>
        <tr>
            <td rowspan="3" class="sc-label">Поръчка</td>
            <td rowspan="3" class="sc-italic">
                @if ($specialBold)
                    <span class="sc-bold">{{ $card->special }}</span>
                @else
                    {{ $card->special }}
                @endif
            </td>
            <td class="sc-label">гр./с.</td>
            <td class="sc-italic">{{ $contact?->citi?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="sc-label">Адрес</td>
            <td class="sc-italic">{{ $contact?->address ?? '—' }}</td>
        </tr>
        <tr>
            <td class="sc-label">Тел.</td>
            <td class="sc-italic">{{ $contact?->gsm_1_m ?: $contact?->tel1 ?: '—' }}</td>
        </tr>
    </table>

    <table class="sc-grid" role="presentation">
        <tr>
            <td style="width:78%" class="sc-bold sc-italic">{{ $card->product }}</td>
            <td style="width:22%">
                @if ($warrantyBold)
                    <span class="sc-bold sc-italic">{{ $card->varanty }}</span>
                @else
                    <span class="sc-italic">{{ $card->varanty }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding:6pt 8pt;">
                @if (filled($card->problem))
                    {!! nl2br(e($card->problem)) !!}
                @else
                    <span class="sc-muted">—</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="sc-grid" role="presentation">
        <tr>
            <td style="width:50%" class="sc-bold">Установени проблеми</td>
            <td style="width:50%" class="sc-italic">{{ $card->serviseproblemtechnik?->username ?? '—' }}</td>
        </tr>
        <tr>
            <td colspan="2">
                @if (filled($card->serviseproblem))
                    {!! nl2br(e($card->serviseproblem)) !!}
                @else
                    <span class="sc-muted">—</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="sc-products" role="presentation">
        <thead>
            <tr>
                <th>Извършени услуги / Продадени компоненти</th>
                <th style="width:14%">Ед. цена</th>
                <th style="width:10%">Брой</th>
                <th style="width:18%">Обща цена</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($soldProducts as $line)
                <tr>
                    <td class="sc-italic">{{ $line->name }}</td>
                    <td class="tabular-nums">{{ number_format((float) $line->ed_cena, 2, '.', '') }}</td>
                    <td class="sc-italic sc-center">{{ $line->broi }}</td>
                    <td class="tabular-nums">
                        {{ number_format((float) $line->price, 2, '.', '') }}{{ $vatSuffix($line->vat) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="sc-muted sc-italic">Няма записани продажби.</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3" class="sc-right sc-bold">Обща цена (евро)</td>
                <td class="sc-bold tabular-nums">{{ number_format($soldTotal, 2, '.', '') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="sc-grid" role="presentation">
        <tr>
            <td style="width:62%" class="sc-bold">Информация за клиента</td>
            <td style="width:38%">
                <span>Дата на предаване: </span><span class="sc-italic">{{ $datepredavane }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                @if (filled($card->dopclient))
                    {!! nl2br(e($card->dopclient)) !!}
                @else
                    <span class="sc-muted">—</span>
                @endif
            </td>
        </tr>
    </table>

    <p class="sc-legal sc-bold sc-italic">
        Продукта е предаден на клиента от сервиза в напълно работоспособен вид. Клиента няма възражения по
        извършените от сервиза услуги.
    </p>

    <p style="margin:8pt 0 2pt;">Приел:</p>
    <p class="sc-italic">/{{ $clientSignName }}/</p>
    <p class="sc-muted">…………………………..…………..</p>
</body>

</html>