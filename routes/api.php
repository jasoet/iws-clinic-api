<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Every route needs the bearer key (app/Http/Middleware/IwsAuth.php).
Route::middleware('iws.auth')->group(function () {
    Route::get('health', HealthController::class);
});
