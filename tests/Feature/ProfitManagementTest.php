<?php

use App\Enums\ProfitTypeKind;
use App\Models\ProfitEntry;
use App\Models\ProfitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('profiler', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('user', 'web');
});

test('office user can view profits index page', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->get(route('profits.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('profits/Index'));
});

test('admin cannot access profits pages', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin)
        ->get(route('profits.index'))
        ->assertForbidden();
});

test('office user can create update and delete own profit entries', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $incomeType = ProfitType::factory()->income()->create(['name' => 'Продажби']);
    $expenseType = ProfitType::factory()->expense()->create(['name' => 'Наем']);

    actingAs($user);

    post(route('profits.store'), [
        'profit_type_id' => $incomeType->id,
        'date' => '2026-08-15',
        'document_number' => 'INV-001',
        'amount' => 1500.50,
    ])->assertRedirect();

    $entry = ProfitEntry::query()->first();

    expect($entry)
        ->not->toBeNull()
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->profit_type_id)->toBe($incomeType->id)
        ->and($entry->document_number)->toBe('INV-001')
        ->and((float) $entry->amount)->toBe(1500.50);

    put(route('profits.update', $entry), [
        'profit_type_id' => $expenseType->id,
        'date' => '2026-08-20',
        'document_number' => 'EXP-002',
        'amount' => 200,
    ])->assertRedirect();

    expect($entry->fresh())
        ->profit_type_id->toBe($expenseType->id)
        ->document_number->toBe('EXP-002')
        ->and((float) $entry->fresh()->amount)->toBe(200.0);

    delete(route('profits.destroy', $entry))
        ->assertRedirect();

    expect(ProfitEntry::query()->count())->toBe(0);
});

test('office user cannot manage another users profit entry', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');

    $other = User::factory()->create();
    $other->assignRole('user');

    $type = ProfitType::factory()->income()->create();
    $entry = ProfitEntry::factory()->for($owner)->for($type, 'profitType')->create();

    actingAs($other);

    put(route('profits.update', $entry), [
        'profit_type_id' => $type->id,
        'date' => '2026-08-01',
        'document_number' => null,
        'amount' => 99,
    ])->assertNotFound();

    delete(route('profits.destroy', $entry))
        ->assertNotFound();
});

test('office user can create a global profit type', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    actingAs($user)
        ->post(route('profit-types.store'), [
            'name' => 'Консултации',
            'kind' => ProfitTypeKind::Income->value,
        ])
        ->assertRedirect();

    expect(ProfitType::query()->where('name', 'Консултации')->exists())->toBeTrue();
});
