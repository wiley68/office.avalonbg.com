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
    });

    Schema::connection('service')->create('contacts', function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('citi_id');
        $table->string('name', 24)->nullable();
        $table->string('last_name', 24);
        $table->string('firm', 256)->nullable();
    });

    Schema::connection('service')->create('members', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('username', 30);
        $table->string('email', 50);
        $table->char('password', 128)->default(str_repeat('x', 128));
        $table->char('salt', 128)->default(str_repeat('y', 128));
        $table->string('admin', 3)->default('No');
        $table->string('hidden', 3)->default('No');
    });

    Schema::connection('service')->create('projects', function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('rakovoditel_id')->nullable();
        $table->dateTime('datecard');
        $table->unsignedInteger('name');
        $table->string('special', 32);
        $table->string('product', 128);
        $table->string('varanty', 32);
        $table->text('problem')->nullable();
        $table->text('serviseproblem')->nullable();
        $table->unsignedInteger('serviseproblemtechnik_id');
        $table->text('dopclient')->nullable();
        $table->dateTime('datepredavane');
        $table->unsignedInteger('saobshtilclient_id');
        $table->string('clientopisanie', 512)->nullable();
        $table->string('etap', 64);
    });

    Schema::connection('service')->create('ceni', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name', 256);
        $table->decimal('price', 10, 2)->unsigned();
        $table->unsignedInteger('project_id');
        $table->string('vat', 3)->default('Yes');
        $table->unsignedInteger('broi')->default(1);
        $table->decimal('ed_cena', 10, 2)->unsigned();
    });
});

test('authenticated user can open printable service card', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    $cityId = (int) DB::connection('service')->table('citi')->insertGetId(['name' => 'GO']);
    $contactId = (int) DB::connection('service')->table('contacts')->insertGetId([
        'citi_id' => $cityId,
        'name' => 'Иван',
        'last_name' => 'Иванов',
        'firm' => 'Фирма',
    ]);
    $memberId = (int) DB::connection('service')->table('members')->insertGetId([
        'username' => 'tech',
        'email' => 'tech@example.com',
    ]);
    $serviceCardId = (int) DB::connection('service')->table('projects')->insertGetId([
        'datecard' => '2026-03-20 09:00:00',
        'name' => $contactId,
        'special' => 'Нормална поръчка',
        'product' => 'UPS',
        'varanty' => 'Гаранционен',
        'serviseproblemtechnik_id' => $memberId,
        'datepredavane' => '2026-03-20 12:00:00',
        'saobshtilclient_id' => $memberId,
        'etap' => 'Приета за сервиз',
    ]);

    get(route('dashboard.service-cards.print', ['serviceCard' => $serviceCardId]))
        ->assertOk()
        ->assertSee('Сервизна карта №', false)
        ->assertSee('UPS', false)
        ->assertSee('Иван Иванов', false);
});
