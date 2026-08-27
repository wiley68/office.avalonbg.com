<?php

use App\Models\ProfitEntry;
use App\Models\ProfitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('office user can export profits as xlsx for selected period', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $other = User::factory()->create();
    $other->assignRole('user');

    $incomeType = ProfitType::factory()->income()->create(['name' => 'Sales']);
    $expenseType = ProfitType::factory()->expense()->create(['name' => 'Rent']);

    ProfitEntry::factory()->for($user)->for($incomeType, 'profitType')->create([
        'date' => '2026-08-10',
        'amount' => 1000,
        'document_number' => 'INV-1',
    ]);

    ProfitEntry::factory()->for($user)->for($expenseType, 'profitType')->create([
        'date' => '2026-08-12',
        'amount' => 250,
    ]);

    ProfitEntry::factory()->for($other)->for($incomeType, 'profitType')->create([
        'date' => '2026-08-11',
        'amount' => 9999,
    ]);

    $response = actingAs($user)
        ->post(route('profits.export'), [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'format' => 'xlsx',
        ])
        ->assertOk()
        ->assertDownload();

    expect($response->headers->get('content-disposition'))
        ->toMatch('/profits_2026-08-01_2026-08-31_.*\.xlsx/');
});

test('office user can export profits as pdf for selected period', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $incomeType = ProfitType::factory()->income()->create(['name' => 'Sales']);

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
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->postJson(route('profits.export'), [
            'date_from' => '2026-08-31',
            'date_to' => '2026-08-01',
            'format' => 'csv',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date_to', 'format']);
});
