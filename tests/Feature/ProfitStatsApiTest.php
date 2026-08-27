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

test('profits stats api aggregates months for calendar year', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $other = User::factory()->create();
    $other->assignRole('user');

    $incomeType = ProfitType::factory()->income()->create();
    $expenseType = ProfitType::factory()->expense()->create();

    ProfitEntry::factory()->for($user)->for($incomeType, 'profitType')->create([
        'date' => '2026-01-15',
        'amount' => 1000,
    ]);

    ProfitEntry::factory()->for($user)->for($expenseType, 'profitType')->create([
        'date' => '2026-01-20',
        'amount' => 200,
    ]);

    ProfitEntry::factory()->for($user)->for($incomeType, 'profitType')->create([
        'date' => '2026-03-01',
        'amount' => 500,
    ]);

    ProfitEntry::factory()->for($other)->for($incomeType, 'profitType')->create([
        'date' => '2026-01-10',
        'amount' => 9999,
    ]);

    $response = actingAs($user)
        ->getJson(route('internal.profits.stats', ['year' => 2026]))
        ->assertOk()
        ->assertJsonPath('period.from', '2026-01-01')
        ->assertJsonPath('period.to', '2026-12-31')
        ->assertJsonCount(12, 'months')
        ->assertJsonPath('totals.income', '1500.00')
        ->assertJsonPath('totals.expense', '200.00')
        ->assertJsonPath('totals.result', '1300.00');

    $january = collect($response->json('months'))->firstWhere('month', '2026-01');
    $march = collect($response->json('months'))->firstWhere('month', '2026-03');
    $february = collect($response->json('months'))->firstWhere('month', '2026-02');

    expect($january['income'])->toBe('1000.00')
        ->and($january['expense'])->toBe('200.00')
        ->and($january['result'])->toBe('800.00')
        ->and($march['income'])->toBe('500.00')
        ->and($february['income'])->toBe('0.00')
        ->and($february['expense'])->toBe('0.00');
});

test('profits stats api supports custom date range by months', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $incomeType = ProfitType::factory()->income()->create();

    ProfitEntry::factory()->for($user)->for($incomeType, 'profitType')->create([
        'date' => '2026-02-10',
        'amount' => 300,
    ]);

    ProfitEntry::factory()->for($user)->for($incomeType, 'profitType')->create([
        'date' => '2026-04-10',
        'amount' => 700,
    ]);

    actingAs($user)
        ->getJson(route('internal.profits.stats', [
            'date_from' => '2026-02-01',
            'date_to' => '2026-04-30',
        ]))
        ->assertOk()
        ->assertJsonCount(3, 'months')
        ->assertJsonPath('totals.income', '1000.00')
        ->assertJsonPath('months.0.month', '2026-02')
        ->assertJsonPath('months.2.month', '2026-04');
});
