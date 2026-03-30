<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('database.connections.service', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    Schema::connection('service')->create('citi', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name', 45);
        $table->string('postalcod', 4)->nullable();
    });

    Schema::connection('service')->create('contacts', function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('citi_id');
        $table->string('name', 24)->nullable();
        $table->string('second_name', 24)->nullable();
        $table->string('last_name', 24);
        $table->string('firm', 256)->nullable();
    });

    Schema::connection('service')->create('varanty', function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('client_id');
        $table->string('product', 256)->nullable();
        $table->string('sernum', 128)->nullable();
        $table->dateTime('date_sell');
        $table->string('invoice', 45)->nullable();
        $table->string('varanty_period', 128)->nullable();
        $table->string('service', 32);
        $table->string('obsluzvane', 16);
        $table->foreign('client_id')->references('id')->on('contacts')->cascadeOnUpdate();
    });
});

test('authenticated user can open printable warranty card template', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    $cityId = (int) DB::connection('service')->table('citi')->insertGetId([
        'name' => 'Горна Оряховица',
        'postalcod' => '5100',
    ]);

    $contactId = (int) DB::connection('service')->table('contacts')->insertGetId([
        'citi_id' => $cityId,
        'name' => 'Иван',
        'second_name' => 'Иванов',
        'last_name' => 'Петров',
        'firm' => 'Тест ООД',
    ]);

    $warrantyId = (int) DB::connection('service')->table('varanty')->insertGetId([
        'client_id' => $contactId,
        'product' => 'UPS 1000VA',
        'sernum' => 'SER-1000',
        'date_sell' => '2026-03-20 10:30:00',
        'invoice' => 'INV-12345',
        'varanty_period' => '24 месеца',
        'service' => 'в сервиз',
        'obsluzvane' => '4-8',
    ]);

    get(route('dashboard.warranties.print', ['warranty' => $warrantyId]))
        ->assertOk()
        ->assertSee('Гаранционна карта №', false)
        ->assertSee('UPS 1000VA', false)
        ->assertSee('SER-1000', false)
        ->assertSee('Тест ООД', false);
});
