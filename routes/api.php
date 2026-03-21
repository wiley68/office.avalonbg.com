<?php

use App\Http\Controllers\Api\NoteController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('notes', NoteController::class);
});
