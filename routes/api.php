<?php

use App\Http\Controllers\Api\CitiController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DlazhnostController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\WarrantyCardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('citi', CitiController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('contacts/lookups', [ContactController::class, 'lookups']);
    Route::apiResource('contacts', ContactController::class);
    Route::apiResource('dlaznosti', DlazhnostController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('notes', NoteController::class);
    Route::get('warranty-cards', [WarrantyCardController::class, 'index']);
});
