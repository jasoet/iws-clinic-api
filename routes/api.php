<?php

use App\Contract\Types;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RecordsController;
use Illuminate\Support\Facades\Route;

// Every route needs the bearer key (app/Http/Middleware/IwsAuth.php).
Route::middleware('iws.auth')->group(function () {
    Route::get('health', HealthController::class);
    Route::get('{type}', RecordsController::class)->whereIn('type', array_keys(Types::all()));
});
