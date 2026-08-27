<?php

use App\Enums\ProfitTypeKind;
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

test('profits api returns only current user entries for selected month', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $other = User::factory()->create();
    $other->assignRole('user');

    $incomeType = ProfitType::factory()->income()->create(['name' => 'Sales']);
    $expenseType = ProfitType::factory()->expense()->create(['name' => 'Rent']);

    ProfitEntry::factory()->for($user)->for($incomeType, 'profitType')->create([
        'date' => '2026-08-10',
        'amount' => 1000,
    ]);

    ProfitEntry::factory()->for($user)->for($expenseType, 'profitType')->create([
        'date' => '2026-08-12',
        'amount' => 250.25,
    ]);

    ProfitEntry::factory()->for($user)->for($incomeType, 'profitType')->create([
        'date' => '2026-07-31',
        'amount' => 500,
    ]);

    ProfitEntry::factory()->for($other)->for($incomeType, 'profitType')->create([
        'date' => '2026-08-05',
        'amount' => 999,
    ]);

    actingAs($user)
        ->getJson(route('internal.profits.index', ['month' => '2026-08']))
        ->assertOk()
        ->assertJsonPath('month', '2026-08')
        ->assertJsonCount(1, 'income')
        ->assertJsonCount(1, 'expense')
        ->assertJsonPath('totals.income', '1000.00')
        ->assertJsonPath('totals.expense', '250.25')
        ->assertJsonPath('totals.result', '749.75');
});

test('profit types api can filter by kind', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    ProfitType::factory()->income()->create(['name' => 'Income A']);
    ProfitType::factory()->expense()->create(['name' => 'Expense A']);

    actingAs($user)
        ->getJson(route('internal.profit-types.index', ['kind' => ProfitTypeKind::Income->value]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kind', 'income');
});
