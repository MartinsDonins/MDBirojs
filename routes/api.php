<?php

use App\Http\Controllers\Api\CoreDigifyController;
use Illuminate\Support\Facades\Route;

Route::middleware('coredigify.auth')->group(function () {
    Route::post('/coredigify/transactions/search', [CoreDigifyController::class, 'search']);
    Route::get('/coredigify/transactions/{id}', [CoreDigifyController::class, 'show']);
});
