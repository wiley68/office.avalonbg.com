<?php

use App\Models\ProfitEntry;
use App\Models\ProfitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('HTTP XLSX export includes only the current group', function () {
    $user = User::factory()->officeUser()->create();

    $other = User::factory()->officeUser()->create();

    $incomeType = ProfitType::factory()->for($user->group)->income()->create(['name' => 'Sales']);
    $expenseType = ProfitType::factory()->for($user->group)->expense()->create(['name' => 'Rent']);

    ProfitEntry::factory()->for($user)->for($incomeType, 'profitType')->create([
        'date' => '2026-08-10',
        'amount' => 1000,
        'document_number' => 'INV-1',
    ]);

    ProfitEntry::factory()->for($user)->for($expenseType, 'profitType')->create([
        'date' => '2026-08-12',
        'amount' => 250,
    ]);

    ProfitEntry::factory()->for($other)->for(ProfitType::factory()->for($other->group)->state(['kind' => $incomeType->kind]), 'profitType')->create([
        'date' => '2026-08-11',
        'amount' => 9999,
    ]);

    $response = actingAs($user)
        ->postJson(route('profits.export'), [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'format' => 'xlsx',
        ])
        ->assertOk();

    $path = $response->baseResponse->getFile()->getPathname();
    $workbook = IOFactory::load($path);
    $cells = collect($workbook->getActiveSheet()->toArray())->flatten()->all();
    expect($cells)->toContain('INV-1');
    expect(array_map('floatval', array_filter($cells, 'is_numeric')))->toContain(1000.0)->toContain(250.0)->not->toContain(9999.0);
    expect($response->headers->get('content-disposition'))->toMatch('/profits_2026-08-01_2026-08-31_.*\.xlsx/');
    $workbook->disconnectWorksheets();
    unlink($path);
});

test('shared HTTP XLSX export serializes literal text without formulas and preserves numeric cells', function (string $text): void {
    $creator = User::factory()->officeUser()->create();
    $peer = User::factory()->officeUser($creator->group)->create();
    $outsider = User::factory()->officeUser()->create();
    $incomeType = ProfitType::factory()->for($creator->group)->income()->create(['name' => $text]);
    $expenseType = ProfitType::factory()->for($creator->group)->expense()->create(['name' => 'Expenses']);
    ProfitEntry::factory()->for($creator)->for($incomeType, 'profitType')->create([
        'date' => '2026-08-10', 'document_number' => $text, 'description' => $text, 'amount' => '12.34',
    ]);
    ProfitEntry::factory()->for($peer)->for($expenseType, 'profitType')->create([
        'date' => '2026-08-11', 'document_number' => 'EXP-1', 'description' => 'Expense fixture', 'amount' => '20.45',
    ]);
    ProfitEntry::factory()->for($outsider)->for(ProfitType::factory()->for($outsider->group)->income(), 'profitType')->create([
        'date' => '2026-08-10', 'document_number' => 'FOREIGN-GROUP-RECORD', 'description' => 'FOREIGN-GROUP-RECORD', 'amount' => '99999.99',
    ]);

    $path = null;
    $workbook = null;
    try {
        $response = actingAs($peer)->postJson(route('profits.export'), [
            'date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'format' => 'xlsx',
        ])->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $reader = IOFactory::createReader('Xlsx');
        $reader->setValueBinder((new DefaultValueBinder)->setPreserveCr(true));
        $workbook = $reader->load($path);
        $sheet = $workbook->getActiveSheet();

        expect($sheet->getHighestRow())->toBe(6)->and($sheet->getHighestColumn())->toBe('F')
            ->and($sheet->getCell('A2')->getValue())->toBe('2026-08-10')
            ->and($sheet->getCell('A3')->getValue())->toBe('2026-08-11')
            ->and($sheet->getCell('B3')->getValue())->toBe('EXP-1')
            ->and($sheet->getCell('C3')->getValue())->toBe('Expense fixture')
            ->and($sheet->getCell('E3')->getValue())->toBe('Expenses');
        foreach (['B2', 'C2', 'E2'] as $coordinate) {
            expect($sheet->getCell($coordinate)->getValue())->toBe($text)
                ->and($sheet->getCell($coordinate)->getDataType())->toBe(DataType::TYPE_STRING);
        }
        foreach (range('A', 'F') as $column) {
            expect($sheet->getCell($column.'1')->getDataType())->toBe(DataType::TYPE_STRING)
                ->and($sheet->getStyle($column.'1')->getFont()->getBold())->toBeTrue();
        }
        foreach (['F2' => 12.34, 'F3' => 20.45, 'F4' => 12.34, 'F5' => 20.45, 'F6' => -8.11] as $coordinate => $number) {
            expect($sheet->getCell($coordinate)->getDataType())->toBe(DataType::TYPE_NUMERIC)
                ->and($sheet->getCell($coordinate)->getValue())->toBe($number);
        }
        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            expect($sheet->getCell($coordinate)->getDataType())->not->toBe(DataType::TYPE_FORMULA);
        }
        expect($sheet->getStyle('A2')->getAlignment()->getVertical())->toBe(Alignment::VERTICAL_TOP)
            ->and($sheet->getStyle('A4')->getFont()->getBold())->toBeTrue()
            ->and($sheet->getStyle('F6')->getFont()->getBold())->toBeTrue();

        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue();
        try {
            $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
        } finally {
            $zip->close();
        }
        expect($worksheetXml)->toBeString()->and($stringsXml)->toBeString()->not->toContain('FOREIGN-GROUP-RECORD');
        $worksheet = simplexml_load_string($worksheetXml, SimpleXMLElement::class, LIBXML_NONET);
        $strings = simplexml_load_string($stringsXml, SimpleXMLElement::class, LIBXML_NONET);
        expect($worksheet)->not->toBeFalse()->and($strings)->not->toBeFalse();
        expect($worksheet->xpath('//*[local-name()="f"]'))->toBe([]);
        $sharedStrings = $strings->xpath('//*[local-name()="si"]');
        foreach (['B2', 'C2', 'E2'] as $coordinate) {
            $cell = $worksheet->xpath('//*[local-name()="c"][@r="'.$coordinate.'"]')[0];
            expect((string) $cell['t'])->toBe(DataType::TYPE_STRING)
                ->and(StringHelper::controlCharacterOOXML2PHP((string) $sharedStrings[(int) $cell->v]->t))->toBe($text);
        }
    } finally {
        $workbook?->disconnectWorksheets();
        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }
})->with([
    'equals' => '=1+1',
    'plus' => '+1+1',
    'minus' => '-1+1',
    'at' => '@SUM(A1:A2)',
    'leading spaces' => '  =1+1  ',
    'tab' => "\t=1+1",
    'line feed' => "\n=1+1",
    'carriage return' => "\r=1+1",
    'CRLF' => "\r\n=1+1",
    'vertical tab' => "\x0B=1+1",
    'form feed' => "\x0C=1+1",
    'NUL' => "\0=1+1",
    'normal unicode and XML text' => '  Текст & <данни> "quotes"  ',
    'numeric text' => '123.45',
    'leading zeroes' => '00123',
    'positive numeric text' => '+123.45',
    'negative numeric text' => '-123.45',
    'literal OOXML escape' => '_x000D_=1+1',
]);

test('empty HTTP XLSX export retains its headings and numeric zero totals', function (): void {
    $user = User::factory()->officeUser()->create();
    $path = null;
    $workbook = null;
    try {
        $response = actingAs($user)->postJson(route('profits.export'), [
            'date_from' => '2026-08-01', 'date_to' => '2026-08-31', 'format' => 'xlsx',
        ])->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $workbook = IOFactory::load($path);
        $sheet = $workbook->getActiveSheet();
        expect($sheet->getHighestRow())->toBe(4)->and($sheet->getHighestColumn())->toBe('F');
        foreach (['F2', 'F3', 'F4'] as $coordinate) {
            expect($sheet->getCell($coordinate)->getDataType())->toBe(DataType::TYPE_NUMERIC)
                ->and($sheet->getCell($coordinate)->getValue())->toBe(0.0);
        }
        expect($sheet->getCell('F1')->getDataType())->toBe(DataType::TYPE_STRING);
    } finally {
        $workbook?->disconnectWorksheets();
        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }
});

test('office user can export profits as pdf for selected period', function () {
    $user = User::factory()->officeUser()->create();

    $incomeType = ProfitType::factory()->for($user->group)->income()->create(['name' => 'Sales']);

    ProfitEntry::factory()->for($user)->for($incomeType, 'profitType')->create([
        'date' => '2026-08-10',
        'amount' => 500,
    ]);

    $response = actingAs($user)
        ->post(route('profits.export'), [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'format' => 'pdf',
        ])
        ->assertOk();

    expect($response->headers->get('content-disposition'))
        ->toMatch('/profits_2026-08-01_2026-08-31_.*\.pdf/')
        ->and($response->headers->get('content-type'))
        ->toContain('pdf');
});

test('admin cannot export profits', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin)
        ->post(route('profits.export'), [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'format' => 'xlsx',
        ])
        ->assertForbidden();
});

test('export validates date range and format', function () {
    $user = User::factory()->officeUser()->create();

    actingAs($user)
        ->postJson(route('profits.export'), [
            'date_from' => '2026-08-31',
            'date_to' => '2026-08-01',
            'format' => 'csv',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_to', 'format']);
});
