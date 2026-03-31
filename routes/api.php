<?php

use App\Http\Controllers\Api\CitiController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DlazhnostController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\ServiceCardController;
use App\Http\Controllers\Api\ServiceCardProductController;
use App\Http\Controllers\Api\WarrantyCardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('citi', CitiController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('contacts/lookups', [ContactController::class, 'lookups']);
    Route::apiResource('contacts', ContactController::class);
    Route::apiResource('dlaznosti', DlazhnostController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('notes', NoteController::class);
    Route::get('service-cards/lookups', [ServiceCardController::class, 'lookups']);
    Route::apiResource('service-cards', ServiceCardController::class);
    Route::get('service-cards/{service_card}/products', [ServiceCardProductController::class, 'index']);
    Route::post('service-cards/{service_card}/products', [ServiceCardProductController::class, 'store']);
    Route::put('service-cards/{service_card}/products/{service_card_product}', [ServiceCardProductController::class, 'update']);
    Route::delete('service-cards/{service_card}/products/{service_card_product}', [ServiceCardProductController::class, 'destroy']);
    Route::apiResource('warranty-cards', WarrantyCardController::class)->only([
        'index',
        'store',
        'show',
        'update',
        'destroy',
    ]);
});
