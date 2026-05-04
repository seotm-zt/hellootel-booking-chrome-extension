<?php

use App\Http\Controllers\Api\ExtensionController;
use App\Http\Middleware\ApiTokenAuth;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/extension')->group(function () {
    Route::post('login',        [ExtensionController::class, 'login']);
    Route::get('parsers',       [ExtensionController::class, 'parsersList']);
    Route::get('parser-rules',  [ExtensionController::class, 'parserRules']);
    Route::post('page-report',  [ExtensionController::class, 'pageReport']);

    Route::middleware(ApiTokenAuth::class)->group(function () {
        Route::get('bookings',          [ExtensionController::class, 'index']);
        Route::post('bookings',         [ExtensionController::class, 'store']);
        Route::delete('bookings/{id}',  [ExtensionController::class, 'destroy']);
    });
});
