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
        $table->string('gsm_2_g', 128)->nullable();
        $table->string('gsm_3_v', 128)->nullable();
        $table->string('tel1', 128)->nullable();
        $table->string('tel2', 128)->nullable();
        $table->string('fax', 128)->nullable();
        $table->string('email', 45)->nullable();
        $table->string('web', 45)->nullable();
        $table->string('address', 256)->nullable();
        $table->string('b_phone', 128)->nullable();
        $table->string('b_email', 45)->nullable();
        $table->string('b_im', 45)->nullable();
        $table->string('im', 45)->nullable();
        $table->text('note')->nullable();
        $table->string('firm', 256)->nullable();
    });
});

test('guest cannot list contacts', function () {
    getJson('/api/contacts')->assertUnauthorized();
});

test('authenticated user can create and list contacts with pagination', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $cityId = (int) DB::connection('service')->table('citi')->insertGetId([
        'name' => 'Sofia',
        'postalcod' => '1000',
    ]);

    postJson('/api/contacts', [
        'citi_id' => $cityId,
        'last_name' => 'Petrov',
        'name' => 'Ivan',
        'firm' => 'Avalon',
        'email' => 'ivan@example.com',
        'note' => 'Test note',
    ])->assertCreated()->assertJsonPath('data.last_name', 'Petrov');

    getJson('/api/contacts?per_page=10&page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.total', 1);
});

test('authenticated user can update and delete contact', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $cityId = (int) DB::connection('service')->table('citi')->insertGetId([
        'name' => 'Varna',
        'postalcod' => '9000',
    ]);

    $contactId = (int) DB::connection('service')->table('contacts')->insertGetId([
        'citi_id' => $cityId,
        'last_name' => 'Old',
    ]);

    putJson("/api/contacts/{$contactId}", [
        'last_name' => 'Updated',
        'name' => 'Maria',
    ])->assertOk()
        ->assertJsonPath('data.last_name', 'Updated');

    getJson("/api/contacts/{$contactId}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Maria');

    deleteJson("/api/contacts/{$contactId}")->assertNoContent();
    getJson("/api/contacts/{$contactId}")->assertNotFound();
});
