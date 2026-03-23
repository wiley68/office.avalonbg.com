<?php

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\NoteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('contacts', ContactController::class);
    Route::apiResource('notes', NoteController::class);
});
