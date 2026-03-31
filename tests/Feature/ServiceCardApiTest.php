<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

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
        $table->string('special', 32)->default('Нормална поръчка');
        $table->string('product', 128);
        $table->string('varanty', 32)->default('Извън гаранционен');
        $table->text('problem')->nullable();
        $table->text('serviseproblem')->nullable();
        $table->unsignedInteger('serviseproblemtechnik_id');
        $table->text('dopclient')->nullable();
        $table->dateTime('datepredavane');
        $table->unsignedInteger('saobshtilclient_id');
        $table->string('clientopisanie', 512)->nullable();
        $table->string('etap', 64);
    });
});

test('guest cannot list service cards', function (): void {
    getJson('/api/service-cards')->assertUnauthorized();
});

test('authenticated user can list service cards with labels', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $cityId = (int) DB::connection('service')->table('citi')->insertGetId([
        'name' => 'Sofia',
        'postalcod' => '1000',
    ]);
    $contactId = (int) DB::connection('service')->table('contacts')->insertGetId([
        'citi_id' => $cityId,
        'name' => 'Иван',
        'last_name' => 'Петров',
        'firm' => 'Тест ООД',
    ]);
    $memberId = (int) DB::connection('service')->table('members')->insertGetId([
        'username' => 'tech',
        'email' => 'tech@example.com',
    ]);

    DB::connection('service')->table('projects')->insert([
        'datecard' => '2026-03-20 10:00:00',
        'name' => $contactId,
        'special' => 'Нормална поръчка',
        'product' => 'Лаптоп',
        'varanty' => 'Извън гаранционен',
        'serviseproblemtechnik_id' => $memberId,
        'datepredavane' => '2026-03-22 10:00:00',
        'saobshtilclient_id' => $memberId,
        'etap' => 'Приета за сервиз',
    ]);

    getJson('/api/service-cards')
        ->assertOk()
        ->assertJsonPath('data.0.product', 'Лаптоп')
        ->assertJsonPath('data.0.contact_label', 'Тест ООД — Иван Петров')
        ->assertJsonPath('data.0.serviseproblemtechnik_label', 'tech');
});

test('authenticated user can create show update and delete service card', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $cityId = (int) DB::connection('service')->table('citi')->insertGetId([
        'name' => 'Plovdiv',
        'postalcod' => '4000',
    ]);
    $contactId = (int) DB::connection('service')->table('contacts')->insertGetId([
        'citi_id' => $cityId,
        'name' => 'Петър',
        'last_name' => 'Иванов',
    ]);
    $memberId = (int) DB::connection('service')->table('members')->insertGetId([
        'username' => 'member1',
        'email' => 'member1@example.com',
    ]);

    $created = postJson('/api/service-cards', [
        'datecard' => '2026-03-20 09:00:00',
        'name' => $contactId,
        'special' => 'Нормална поръчка',
        'product' => 'Принтер',
        'varanty' => 'Гаранционен',
        'serviseproblemtechnik_id' => $memberId,
        'datepredavane' => '2026-03-21 12:00:00',
        'saobshtilclient_id' => $memberId,
        'etap' => 'Диагностика',
    ])->assertCreated()->json('data');

    getJson('/api/service-cards/'.$created['id'])
        ->assertOk()
        ->assertJsonPath('data.product', 'Принтер');

    putJson('/api/service-cards/'.$created['id'], [
        'product' => 'Принтер Laser',
        'etap' => 'Извършва се ремонта',
    ])->assertOk()
        ->assertJsonPath('data.product', 'Принтер Laser');

    deleteJson('/api/service-cards/'.$created['id'])->assertNoContent();
    getJson('/api/service-cards/'.$created['id'])->assertNotFound();
});
