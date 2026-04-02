<?php

use App\Ai\Tools\ManageWarrantiesTool;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Tools\Request as AiToolRequest;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
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

    Schema::connection('service')->create('dlaznosti', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name', 45);
    });

    Schema::connection('service')->create('contacts', function (Blueprint $table): void {
        $table->increments('id');
        $table->unsignedInteger('citi_id');
        $table->string('eik', 9)->nullable();
        $table->string('info', 128)->nullable();
        $table->string('name', 24)->nullable();
        $table->string('second_name', 24)->nullable();
        $table->string('last_name', 24);
        $table->unsignedInteger('dlaznosti_id')->nullable();
        $table->string('gsm_1_m', 128)->nullable();
        $table->string('email', 45)->nullable();
        $table->string('firm', 256)->nullable();
        $table->text('note')->nullable();
    });

    Schema::connection('service')->create('varanty', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('product', 256)->nullable();
        $table->string('sernum', 128)->nullable();
        $table->unsignedInteger('client_id');
        $table->dateTime('date_sell');
        $table->string('invoice', 45)->nullable();
        $table->string('varanty_period', 128)->nullable();
        $table->string('service', 32);
        $table->string('obsluzvane', 16);
        $table->text('note')->nullable();
        $table->string('motherboard', 128)->nullable();
        $table->string('processor', 128)->nullable();
        $table->string('ram', 128)->nullable();
        $table->string('psu', 128)->nullable();
        $table->string('hdd1', 128)->nullable();
        $table->string('hdd2', 128)->nullable();
        $table->string('dvd', 128)->nullable();
        $table->string('vga', 128)->nullable();
        $table->string('lan', 128)->nullable();
        $table->string('speackers', 128)->nullable();
        $table->string('printer', 128)->nullable();
        $table->string('monitor', 128)->nullable();
        $table->string('kbd', 128)->nullable();
        $table->string('mouse', 128)->nullable();
        $table->string('other', 128)->nullable();
        $table->string('iscomp', 8)->default('No');
        $table->string('motherboardsn', 45)->nullable();
        $table->string('processorsn', 45)->nullable();
        $table->string('ramsn', 45)->nullable();
        $table->string('psusn', 45)->nullable();
        $table->string('hdd1sn', 45)->nullable();
        $table->string('hdd2sn', 45)->nullable();
        $table->string('dvdsn', 45)->nullable();
        $table->string('vgasn', 45)->nullable();
        $table->string('lansn', 45)->nullable();
        $table->string('speackerssn', 45)->nullable();
        $table->string('printersn', 45)->nullable();
        $table->string('monitorsn', 45)->nullable();
        $table->string('kbdsn', 45)->nullable();
        $table->string('mousesn', 45)->nullable();
        $table->string('othersn', 45)->nullable();
        $table->foreign('client_id')->references('id')->on('contacts')->cascadeOnUpdate();
    });
});

test('guest cannot list warranty cards', function () {
    getJson('/api/warranty-cards')->assertUnauthorized();
});

test('guest cannot create warranty card', function () {
    postJson('/api/warranty-cards', [])->assertUnauthorized();
});

test('authenticated user can list warranty cards with client label', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $citiId = (int) DB::connection('service')->table('citi')->insertGetId([
        'name' => 'Sofia',
        'postalcod' => '1000',
    ]);

    $contactId = (int) DB::connection('service')->table('contacts')->insertGetId([
        'citi_id' => $citiId,
        'last_name' => 'Иванов',
        'name' => 'Иван',
        'firm' => 'Тест ООД',
    ]);

    DB::connection('service')->table('varanty')->insert([
        'product' => 'Лаптоп X',
        'sernum' => 'SN-001',
        'client_id' => $contactId,
        'date_sell' => '2025-01-15 10:00:00',
        'service' => 'в сервиз',
        'obsluzvane' => '4-8',
        'iscomp' => 'No',
    ]);

    getJson('/api/warranty-cards')
        ->assertOk()
        ->assertJsonPath('data.0.product', 'Лаптоп X')
        ->assertJsonPath('data.0.sernum', 'SN-001')
        ->assertJsonFragment(['client_label' => 'Тест ООД — Иван Иванов']);
});

test('authenticated user can create show update and delete warranty card', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $citiId = (int) DB::connection('service')->table('citi')->insertGetId([
        'name' => 'Plovdiv',
        'postalcod' => '4000',
    ]);

    $contactId = (int) DB::connection('service')->table('contacts')->insertGetId([
        'citi_id' => $citiId,
        'last_name' => 'Петров',
        'name' => 'Петър',
        'firm' => null,
    ]);

    $created = postJson('/api/warranty-cards', [
        'client_id' => $contactId,
        'date_sell' => '2025-06-01 12:00:00',
        'service' => 'при клиента',
        'obsluzvane' => '8-16',
        'product' => 'Монитор',
        'sernum' => 'MON-99',
        'iscomp' => 'No',
    ])->assertCreated()
        ->json('data');

    expect($created['id'])->toBeInt();

    getJson('/api/warranty-cards/'.$created['id'])
        ->assertOk()
        ->assertJsonPath('data.product', 'Монитор')
        ->assertJsonPath('data.sernum', 'MON-99');

    putJson('/api/warranty-cards/'.$created['id'], [
        'product' => 'Монитор 27"',
        'sernum' => 'MON-100',
    ])->assertOk()
        ->assertJsonPath('data.product', 'Монитор 27"');

    deleteJson('/api/warranty-cards/'.$created['id'])->assertNoContent();

    getJson('/api/warranty-cards/'.$created['id'])->assertNotFound();
});

test('manage warranties tool allows per_page above 200', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    $cityId = (int) DB::connection('service')->table('citi')->insertGetId([
        'name' => 'Sofia',
        'postalcod' => '1000',
    ]);

    $contactId = (int) DB::connection('service')->table('contacts')->insertGetId([
        'citi_id' => $cityId,
        'last_name' => 'Client',
        'name' => 'One',
    ]);

    DB::connection('service')->table('varanty')->insert([
        [
            'product' => 'P1',
            'client_id' => $contactId,
            'date_sell' => '2025-01-01 10:00:00',
            'service' => 'в сервиз',
            'obsluzvane' => '4-8',
            'iscomp' => 'No',
        ],
        [
            'product' => 'P2',
            'client_id' => $contactId,
            'date_sell' => '2025-01-02 10:00:00',
            'service' => 'в сервиз',
            'obsluzvane' => '4-8',
            'iscomp' => 'No',
        ],
        [
            'product' => 'P3',
            'client_id' => $contactId,
            'date_sell' => '2025-01-03 10:00:00',
            'service' => 'в сервиз',
            'obsluzvane' => '4-8',
            'iscomp' => 'No',
        ],
    ]);

    $tool = new ManageWarrantiesTool;
    $result = $tool->handle(new AiToolRequest([
        'action' => 'list',
        'per_page' => 500,
        'page' => 1,
    ]));

    $decoded = json_decode((string) $result, true);

    expect($decoded)->toBeArray()
        ->and($decoded['per_page'] ?? null)->toBe(500)
        ->and($decoded['returned'] ?? null)->toBe(3);
});
