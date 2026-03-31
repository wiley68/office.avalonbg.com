<!DOCTYPE html>
<html lang="bg">

<head>
    <meta charset="utf-8">
    <title>Сервизна карта #{{ $card->id }}</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            margin: 24px;
            color: #111;
            font-size: 13px;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 22px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 16px;
        }

        .label {
            font-weight: bold;
        }

        .full {
            grid-column: 1 / -1;
        }
    </style>
</head>

<body>
    <h1>Сервизна карта № {{ $card->id }}</h1>
    <div class="grid">
        <div><span class="label">Дата:</span> {{ $card->datecard?->format('d.m.Y H:i') ?? '—' }}</div>
        <div><span class="label">Етап:</span> {{ $card->etap }}</div>
        <div><span class="label">Клиент:</span> {{ $fullName !== '' ? $fullName : '—' }}</div>
        <div><span class="label">Фирма:</span> {{ $contact?->firm ?? '—' }}</div>
        <div><span class="label">Продукт:</span> {{ $card->product }}</div>
        <div><span class="label">Гаранция:</span> {{ $card->varanty }}</div>
        <div><span class="label">Спешност:</span> {{ $card->special }}</div>
        <div><span class="label">Дата предаване:</span> {{ $card->datepredavane?->format('d.m.Y H:i') ?? '—' }}</div>
        <div class="full"><span class="label">Проблем:</span> {{ $card->problem ?? '—' }}</div>
        <div class="full"><span class="label">Сервизен проблем:</span> {{ $card->serviseproblem ?? '—' }}</div>
        <div class="full"><span class="label">Допълнително към клиента:</span> {{ $card->dopclient ?? '—' }}</div>
        <div class="full"><span class="label">Клиентско описание:</span> {{ $card->clientopisanie ?? '—' }}</div>
        <div><span class="label">Ръководител:</span> {{ $card->rakovoditel?->username ?? '—' }}</div>
        <div><span class="label">Техник:</span> {{ $card->serviseproblemtechnik?->username ?? '—' }}</div>
        <div><span class="label">Съобщил клиента:</span> {{ $card->saobshtilclient?->username ?? '—' }}</div>
    </div>
</body>

</html>