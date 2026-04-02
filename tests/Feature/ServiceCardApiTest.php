<?php

use App\Ai\Tools\ManageServiceCardsTool;
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

    $projectId = (int) DB::connection('service')->table('projects')->insertGetId([
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

    DB::connection('service')->table('ceni')->insert([
        [
            'name' => 'Част А',
            'price' => 10.5,
            'project_id' => $projectId,
            'vat' => 'Yes',
            'broi' => 1,
            'ed_cena' => 10.5,
        ],
        [
            'name' => 'Част Б',
            'price' => 25,
            'project_id' => $projectId,
            'vat' => 'No',
            'broi' => 1,
            'ed_cena' => 25,
        ],
    ]);

    getJson('/api/service-cards')
        ->assertOk()
        ->assertJsonPath('data.0.product', 'Лаптоп')
        ->assertJsonPath('data.0.contact_label', 'Тест ООД — Иван Петров')
        ->assertJsonPath('data.0.serviseproblemtechnik_label', 'tech')
        ->assertJsonCount(2, 'data.0.sold_products')
        ->assertJsonPath('data.0.sold_products.0.price', '10.50')
        ->assertJsonPath('data.0.sold_products.1.price', '25.00');
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

test('authenticated user can manage sold products for service card', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $cityId = (int) DB::connection('service')->table('citi')->insertGetId([
        'name' => 'Varna',
        'postalcod' => '9000',
    ]);
    $contactId = (int) DB::connection('service')->table('contacts')->insertGetId([
        'citi_id' => $cityId,
        'name' => 'Георги',
        'last_name' => 'Стоянов',
    ]);
    $memberId = (int) DB::connection('service')->table('members')->insertGetId([
        'username' => 'member2',
        'email' => 'member2@example.com',
    ]);
    $cardId = (int) DB::connection('service')->table('projects')->insertGetId([
        'datecard' => '2026-03-20 09:00:00',
        'name' => $contactId,
        'special' => 'Нормална поръчка',
        'product' => 'Компютър',
        'varanty' => 'Извън гаранционен',
        'serviseproblemtechnik_id' => $memberId,
        'datepredavane' => '2026-03-22 13:00:00',
        'saobshtilclient_id' => $memberId,
        'etap' => 'Диагностика',
    ]);

    $product = postJson("/api/service-cards/{$cardId}/products", [
        'name' => 'RAM 8GB',
        'price' => 120,
        'vat' => 'Yes',
        'broi' => 2,
        'ed_cena' => 60,
    ])->assertCreated()->json('data');

    getJson("/api/service-cards/{$cardId}/products")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'RAM 8GB');

    putJson("/api/service-cards/{$cardId}/products/{$product['id']}", [
        'name' => 'RAM 16GB',
        'broi' => 1,
        'ed_cena' => 95,
        'price' => 95,
        'vat' => 'Yes',
    ])->assertOk()
        ->assertJsonPath('data.name', 'RAM 16GB');

    deleteJson("/api/service-cards/{$cardId}/products/{$product['id']}")
        ->assertNoContent();
});

test('manage service cards tool allows per_page above 200', function (): void {
    $user = User::factory()->create();
    actingAs($user);

    $cityId = (int) DB::connection('service')->table('citi')->insertGetId([
        'name' => 'Sofia',
        'postalcod' => '1000',
    ]);

    $contactId = (int) DB::connection('service')->table('contacts')->insertGetId([
        'citi_id' => $cityId,
        'name' => 'Ivan',
        'last_name' => 'Petrov',
    ]);

    $memberId = (int) DB::connection('service')->table('members')->insertGetId([
        'username' => 'm1',
        'email' => 'm1@example.com',
    ]);

    DB::connection('service')->table('projects')->insert([
        [
            'datecard' => '2026-03-20 09:00:00',
            'name' => $contactId,
            'special' => 'Нормална поръчка',
            'product' => 'Printer 1',
            'varanty' => 'Гаранционен',
            'serviseproblemtechnik_id' => $memberId,
            'datepredavane' => '2026-03-21 12:00:00',
            'saobshtilclient_id' => $memberId,
            'etap' => 'Диагностика',
        ],
        [
            'datecard' => '2026-03-20 10:00:00',
            'name' => $contactId,
            'special' => 'Нормална поръчка',
            'product' => 'Printer 2',
            'varanty' => 'Гаранционен',
            'serviseproblemtechnik_id' => $memberId,
            'datepredavane' => '2026-03-21 13:00:00',
            'saobshtilclient_id' => $memberId,
            'etap' => 'Диагностика',
        ],
        [
            'datecard' => '2026-03-20 11:00:00',
            'name' => $contactId,
            'special' => 'Нормална поръчка',
            'product' => 'Printer 3',
            'varanty' => 'Гаранционен',
            'serviseproblemtechnik_id' => $memberId,
            'datepredavane' => '2026-03-21 14:00:00',
            'saobshtilclient_id' => $memberId,
            'etap' => 'Диагностика',
        ],
    ]);

    $tool = new ManageServiceCardsTool;
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
