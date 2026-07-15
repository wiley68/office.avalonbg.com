<?php

use App\Support\EmailBodyEncoding;

test('email body encoding converts windows-1251 text to utf-8', function () {
    $encoding = new EmailBodyEncoding;

    $windows1251 = iconv('UTF-8', 'Windows-1251//IGNORE', 'Здравейте, това е тестово съобщение.');

    expect($encoding->toUtf8($windows1251, 'windows-1251'))
        ->toBe('Здравейте, това е тестово съобщение.');
});

test('email body encoding falls back to cp1251 when charset is missing', function () {
    $encoding = new EmailBodyEncoding;

    $windows1251 = iconv('UTF-8', 'CP1251//IGNORE', 'Кореспонденция по DEM-18992');

    expect($encoding->toUtf8($windows1251))
        ->toBe('Кореспонденция по DEM-18992');
});

test('email body encoding keeps valid utf-8 unchanged', function () {
    $encoding = new EmailBodyEncoding;

    $utf8 = 'Вече е UTF-8 текст.';

    expect($encoding->toUtf8($utf8, 'windows-1251'))->toBe($utf8);
});

test('email body encoding reads charset from mime structure parameters', function () {
    $encoding = new EmailBodyEncoding;

    $structure = (object) [
        'parameters' => [
            (object) ['attribute' => 'CHARSET', 'value' => 'windows-1251'],
        ],
    ];

    expect($encoding->charsetFromStructure($structure))->toBe('windows-1251');
});
