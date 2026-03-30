<!DOCTYPE html>
<html lang="bg">

<head>
    <meta charset="utf-8">
    <title>Гаранционна карта #{{ $card->id }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 1.2cm;
        }

        body {
            margin: 0;
            color: #000;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .layout {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .layout td {
            vertical-align: top;
        }

        .left {
            width: 48%;
            padding-right: 14px;
        }

        .right {
            width: 52%;
            padding-left: 14px;
        }

        .small {
            font-size: 11px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin: 0 0 10px;
        }

        .sub-title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin: 18px 0 10px;
        }

        .muted-line {
            border-top: 1px solid #000;
            margin-top: 14px;
            padding-top: 4px;
            font-size: 10px;
        }

        .head-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .head-table td {
            border: 1px solid #000;
            padding: 6px;
        }

        .company-name {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin: 0;
        }

        .card-header {
            text-align: center;
            margin: 20px 0 14px;
            font-size: 15px;
            font-weight: bold;
        }

        .card-header span {
            font-size: 16px;
        }

        .field-block {
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .label {
            font-weight: bold;
        }

        .conditions-title {
            text-align: center;
            margin: 20px 0 12px;
            text-decoration: underline;
            font-weight: bold;
        }

        .tick {
            font-weight: bold;
            margin: 0 6px;
        }

        .warn {
            background: #f2f2f2;
            padding: 4px 6px;
        }

        .center {
            text-align: center;
        }

        .right-align {
            text-align: right;
        }

        p {
            margin: 0 0 8px;
        }
    </style>
</head>

<body>
    <table class="layout">
        <tr>
            <td class="left">
                <p class="title">ФИРМАТА ПРЕПОРЪЧВА ДА СЕ СПАЗВАТ СЛЕДНИТЕ ПРАВИЛА ЗА ТРАНСПОРТ, СЪХРАНЕНИЕ И
                    ИНСТАЛИРАНЕ</p>
                <p><strong>1. Транспорт</strong></p>
                <p>При транспортиране на устройството да бъдат извършени всички предвидени в неговото описание механични
                    укрепвания.</p>
                <p><strong>2. Съхранение</strong></p>
                <p>Устройствата да се съхраняват при условия: температура от +10 до +35°C; относителна влажност до 80%
                    при 30°C; отсъствие на агресивни примеси.</p>
                <p><strong>3. Инсталиране</strong></p>
                <p>3.1. Инсталиране на разстояние от стената не по-малко от 10 cm.</p>
                <p>3.2. Включването след транспорт/съхранение се извършва след минимум 2 часа престой за аклиматизация.
                </p>

                <p class="sub-title">ИНСТРУКЦИЯ ЗА ЕКСПЛОАТАЦИЯ</p>
                <p>1. Всички захранващи кабели трябва да са тип шуко и да се включват в контакт тип шуко.</p>
                <p class="warn"><strong>Внимание!</strong> Напрежението на електрическата мрежа трябва да бъде 220V ±10%
                    с честота 50Hz ±1Hz.</p>
                <p>2. Забранява се включване/изключване на интерфейсни кабели при включено захранване.</p>
                <p>3. Забранява се поставяне на предмети върху устройството.</p>
                <p>4. Забранява се устройството да се покрива, когато е включено.</p>
                <p>5. При нарушаване на целостта на корпуса гаранцията отпада.</p>
                <p>6. При нарушаване/повреда на термолепенки гаранцията отпада.</p>

                <p class="center" style="margin-top: 16px;"><strong>ЗА ПОВРЕДИ, ПРИЧИНЕНИ ОТ НЕИЗПЪЛНЕНИЕ НА ГОРНИТЕ
                        УСЛОВИЯ, АВАЛОН НЕ НОСИ ОТГОВОРНОСТ!</strong></p>
                <p class="muted-line">Горна Оряховица, ул. „Патриарх Евтимий“ 27, тел: (0619) 22218, e-mail:
                    home@avalonbg.com</p>
                <p class="center" style="margin-top: 16px;"><strong>Благодарим Ви, че работим за Вас!</strong></p>
            </td>
            <td class="right">
                <table class="head-table">
                    <tr>
                        <td style="width: 42%;">
                            <p class="center" style="margin: 10px 0;">
                                <img src="{{ asset('images/varanty_card_logo.jpg') }}" alt="Авалон"
                                    style="display: inline-block; width: 200px; height: 50px; object-fit: contain;">
                            </p>
                        </td>
                        <td rowspan="2">
                            <p class="company-name">Авалон ООД</p>
                            <p class="small"><strong>Горна Оряховица</strong>, ул. Патр. Евтимий №27<br>Тел./Факс:
                                (0619) 22218<br>e-mail: home@avalonbg.com</p>
                            <p class="center">www.avalonbg.com</p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <p class="center small"><em>Computer systems &amp; Consulting service</em></p>
                        </td>
                    </tr>
                </table>

                <p class="card-header">
                    Гаранционна карта №
                    <span>{{ str_pad((string) $card->id, 6, '0', STR_PAD_LEFT) }}</span>/{{ $card->date_sell?->format('Y') }}г.
                </p>

                <div class="field-block">
                    <div><span class="label">Устройство:</span> {{ $card->product ?? '—' }}</div>
                    <div><span class="label">Сериен No на устройството:</span> {{ $card->sernum ?? '—' }}</div>
                    <div>
                        <span class="label">Клиент:</span>
                        {{ $fullName !== '' ? $fullName : '—' }}
                        @if(!empty($contact?->firm))
                            - {{ $contact->firm }}
                        @endif
                    </div>
                    <div><span class="label">Дата на продажба:</span> {{ $card->date_sell?->format('d.m.Y') ?? '—' }}
                    </div>
                    <div><span class="label">Договор No (Фактура/Проформа):</span> {{ $card->invoice ?? '—' }}</div>
                </div>

                <p class="conditions-title">ГАРАНЦИОННИ УСЛОВИЯ</p>
                <p>Гаранционният срок на устройството <strong>{{ $card->varanty_period ?? 'не е посочен' }}</strong> и
                    започва да тече от датата на продажба.</p>
                <p>
                    Място на сервизиране:
                    <span class="tick">{{ $serviceInShop ? '✔' : '□' }}</span> в сервиз
                    <span class="tick">{{ $serviceAtClient ? '✔' : '□' }}</span> при клиента
                </p>
                <p>
                    Сервизно обслужване (СО):
                    <span class="tick">{{ $response48 ? '✔' : '□' }}</span> 4-8
                    <span class="tick">{{ $response816 ? '✔' : '□' }}</span> 8-16
                    <span class="tick">{{ $response832 ? '✔' : '□' }}</span> 8-32
                </p>
                <p>Договорът (фактурата) и гаранционната карта се пазят от купувача до изтичане на гаранционния срок.
                </p>
                <p>Настоящата гаранционна карта дава право на безплатно сервизно обслужване съгласно вътрешните правила
                    на фирмата.</p>
                <p class="center"><strong>ОТСТРАНЯВАНЕТО НА ПОВРЕДИ ИЗВЪН ГАРАНЦИОННИТЕ УСЛОВИЯ СЕ ЗАПЛАЩА ОТ
                        КЛИЕНТА!</strong></p>

                <p class="right-align" style="margin-top: 24px;">
                    За Авалон:<br><br>
                    <em>/{{ auth()->user()?->name ?? 'Оператор' }}/</em>
                </p>
                <p class="small">ФОК 09-00-002, рев.08</p>
            </td>
        </tr>
    </table>
</body>

</html>