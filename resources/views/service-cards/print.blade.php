<!DOCTYPE html>
<html lang="bg">

<head>
    <meta charset="utf-8">
    <title>СК № {{ str_pad((string) $card->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page {
            margin: 12mm 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Calibri, 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.25;
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

        .sc-italic {
            font-style: italic;
        }

        .sc-bold {
            font-weight: 700;
        }

        .sc-right {
            text-align: right;
        }

        .sc-nb {
            margin-bottom: 10pt;
        }

        /* Външни таблици — двойна рамка */
        table.sc-frame {
            width: 100%;
            max-width: 640px;
            border-collapse: collapse;
            margin: 0 0 10pt;
            border: 2.25pt double #000;
        }

        table.sc-frame td {
            border: 2.25pt double #000;
            padding: 5pt 6pt;
            vertical-align: top;
        }

        /* Вътрешни таблици — сива мрежа като оригинала */
        table.sc-grid {
            width: 100%;
            max-width: 640px;
            border-collapse: collapse;
            margin: 0 0 10pt;
            border: 0.5pt solid #aeaaaa;
        }

        table.sc-grid td {
            border: 0.5pt solid #aeaaaa;
            padding: 5pt 6pt;
            vertical-align: top;
        }

        .sc-label {
            font-weight: normal;
        }

        .sc-spacer-block {
            min-height: 140pt;
        }

        .sc-divider {
            border: none;
            border-top: 1pt dotted #333;
            margin: 12pt 0;
            max-width: 640px;
        }

        .sc-sign {
            margin-top: 14pt;
            max-width: 640px;
        }

        .sc-sign-line {
            border-top: 1pt solid #333;
            width: 55%;
            margin-top: 28pt;
            padding-top: 2pt;
        }

        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .sc-noprint {
                display: none;
            }
        }
    </style>
</head>

<body>
    @php
        $paddedId = str_pad((string) $card->id, 6, '0', STR_PAD_LEFT);
        $dateAccept = $card->datecard?->format('d.m.Y') ?? '—';
        $specialBold = $card->special === 'Спешна поръчка';
        $warrantyBold = $card->varanty === 'Гаранционен';
        $clientName = trim(implode(' ', array_filter([$contact?->name, $contact?->second_name, $contact?->last_name])));
        $clientWithFirm = $clientName !== '' ? $clientName : '—';
        if ($contact?->firm) {
            $clientWithFirm .= ' (' . $contact->firm . ')';
        }
        $clientNameOnly = $clientName !== '' ? $clientName : '—';
        $cityName = $contact?->citi?->name ?? '—';
        $address = $contact?->address ?? '—';
        $phone = $contact?->gsm_1_m ?: $contact?->tel1 ?: '—';
        $email = $contact?->email ?? '—';
        $rakovoditelName = $card->rakovoditel?->username ?? '—';
    @endphp

    {{-- Горна лента: номер, дата, бележка версия --}}
    <table class="sc-frame" role="presentation">
        <tr>
            <td style="width:32%">
                <span class="sc-muted">СК № </span><span class="sc-bold">{{ $paddedId }}</span>
            </td>
            <td style="width:34%">
                <span class="sc-bold">{{ $dateAccept }}</span>
            </td>
            <td style="width:34%" class="sc-right sc-small sc-italic sc-muted">
                {{ config('app.name') }} · сервизна карта
            </td>
        </tr>
    </table>

    {{-- Приел / Клиент / Поръчка / контакти --}}
    <table class="sc-grid" role="presentation">
        <tr>
            <td style="width:11%" class="sc-label">Приел</td>
            <td style="width:37%" class="sc-italic">{{ $rakovoditelName }}</td>
            <td style="width:11%" class="sc-label">Клиент</td>
            <td style="width:41%" class="sc-italic">{{ $clientWithFirm }}</td>
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
            <td class="sc-italic">{{ $cityName }}</td>
        </tr>
        <tr>
            <td class="sc-label">Адрес</td>
            <td class="sc-italic">{{ $address }}</td>
        </tr>
        <tr>
            <td class="sc-label">Тел.</td>
            <td class="sc-italic">{{ $phone }}</td>
        </tr>
    </table>

    {{-- Продукт + гаранция; проблем при приемане --}}
    <table class="sc-grid" role="presentation">
        <tr>
            <td style="width:78%" class="sc-italic">{{ $card->product }}</td>
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

    {{-- Празно място за бележки / схеми --}}
    <table class="sc-grid" role="presentation">
        <tr>
            <td class="sc-spacer-block">&nbsp;</td>
        </tr>
    </table>

    <hr class="sc-divider">

    {{-- Квитанция (втори екземпляр) --}}
    <table class="sc-frame" role="presentation">
        <tr>
            <td style="width:38%">
                <span class="sc-small">Квитанция (за постъпване в сервиз)</span>
            </td>
            <td style="width:16%">
                <span class="sc-muted">СК № </span><span class="sc-bold">{{ $paddedId }}</span>
            </td>
            <td style="width:20%" class="sc-right">
                <span class="sc-bold">{{ $dateAccept }}</span>
            </td>
            <td style="width:26%" class="sc-right sc-small sc-italic sc-muted">
                Квитанция · {{ config('app.name') }}
            </td>
        </tr>
    </table>

    <table class="sc-grid" role="presentation">
        <tr>
            <td style="width:11%" class="sc-label">Клиент</td>
            <td style="width:37%" class="sc-italic">{{ $clientNameOnly }}</td>
            <td style="width:11%" class="sc-label">Приел</td>
            <td style="width:41%" class="sc-italic">{{ $rakovoditelName }}</td>
        </tr>
        <tr>
            <td rowspan="4" class="sc-label">Поръчка</td>
            <td rowspan="4" class="sc-italic">
                @if ($specialBold)
                    <span class="sc-bold">{{ $card->special }}</span>
                @else
                    {{ $card->special }}
                @endif
            </td>
            <td class="sc-label">Н.М.</td>
            <td class="sc-italic">{{ $cityName }}</td>
        </tr>
        <tr>
            <td class="sc-label">Адрес</td>
            <td class="sc-italic">{{ $address }}</td>
        </tr>
        <tr>
            <td class="sc-label">Тел.</td>
            <td class="sc-italic">{{ $phone }}</td>
        </tr>
        <tr>
            <td class="sc-label">Email</td>
            <td class="sc-italic">{{ $email }}</td>
        </tr>
    </table>

    <table class="sc-grid" role="presentation">
        <tr>
            <td style="width:78%" class="sc-italic">{{ $card->product }}</td>
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

    <table class="sc-sign" role="presentation" style="width:100%;max-width:640px;border:none;">
        <tr>
            <td style="width:50%;border:none;">&nbsp;</td>
            <td style="width:50%;border:none;vertical-align:bottom;">
                <p class="sc-label" style="margin:0 0 4pt;">Приел за сервиза</p>
                <p class="sc-italic" style="margin:0 0 8pt;">/ {{ $rakovoditelName }} /</p>
                <div class="sc-sign-line">&nbsp;</div>
            </td>
        </tr>
    </table>

</body>

</html>