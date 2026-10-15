<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Autenticación. Limitado a 5 intentos por minuto para frenar fuerza bruta.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
